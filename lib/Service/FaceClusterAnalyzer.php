<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use Rubix\ML\Clusterers\DBSCAN;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Graph\Trees\BallTree;
use Rubix\ML\Kernels\Distance\Euclidean;

class FaceClusterAnalyzer {
	public const MIN_CLUSTER_DENSITY = 2;
	public const MAX_INNER_CLUSTER_RADIUS = 0.44;
	public const MAX_INNER_CLUSTER_DISTANCE = 0.5;
	public const DIMENSIONS = 128;

	private FaceDetectionMapper $faceDetections;
	private FaceClusterMapper $faceClusters;
	private TagManager $tagManager;
	private Logger $logger;

	public function __construct(FaceDetectionMapper $faceDetections, FaceClusterMapper $faceClusters, TagManager $tagManager, Logger $logger) {
		$this->faceDetections = $faceDetections;
		$this->faceClusters = $faceClusters;
		$this->tagManager = $tagManager;
		$this->logger = $logger;
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @throws \JsonException
	 */
	public function calculateClusters(string $userId): void {
		$this->logger->debug('Find face detection for use '.$userId);
		$detections = $this->faceDetections->findByUserId($userId);

		if (count($detections) === 0) {
			$this->logger->debug('No face detections found');
			return;
		}

		// Here we use RubixMLs DBSCAN clustering algorithm
		$dataset = new Unlabeled(array_map(function (FaceDetection $detection) : array {
			return $detection->getVector();
		}, $detections));

		$clusterer = new DBSCAN(self::MAX_INNER_CLUSTER_RADIUS, self::MIN_CLUSTER_DENSITY, new BallTree(100, new Euclidean()));
		$this->logger->debug('Calculate clusters for '.count($detections).' faces');
		$results = $clusterer->predict($dataset);
		$numClusters = max($results);

		$this->logger->debug('Found '.$numClusters.' face clusters');

		for ($i = 0; $i <= $numClusters; $i++) {
			$keys = array_keys($results, $i);
			$clusterDetections = array_map(function ($key) use ($detections) : FaceDetection {
				return $detections[$key];
			}, $keys);
			$detectionClusters = array_map(function ($detection) : array {
				return $this->faceClusters->findByDetectionId($detection->getId());
			}, $clusterDetections);

			$distance = new Euclidean();
			$distances = array_merge(...array_map(fn ($detection) => array_map(fn ($detection2) => $distance->compute($detection->getVector(), $detection2->getVector()), $clusterDetections), $clusterDetections));
			sort($distances);

			// if this cluster is larger than what could possibly be the same face we ignore it
			if ($distances[count($distances) - 1] > self::MAX_INNER_CLUSTER_DISTANCE) {
				$this->logger->debug('Inner cluster distance for cluster '.$i.' is '.$distances[count($distances) - 1].' which is too large. Ignoring cluster.');
				continue;
			}

			// Since recognize works incrementally, we need to check if some of these face
			// detections have been added to an existing cluster already
			$alreadyClustered = array_values(array_filter($clusterDetections, function ($item, int $i) use ($detectionClusters) : bool {
				return count($detectionClusters[$i]) >= 1;
			}, ARRAY_FILTER_USE_BOTH));

			$notYetClustered = array_values(array_filter($clusterDetections, function ($item, int $i) use ($detectionClusters) : bool {
				return count($detectionClusters[$i]) === 0;
			}, ARRAY_FILTER_USE_BOTH));

			if (count($alreadyClustered) > 0) {
				$uniqueOldClusterIds = array_unique(array_map(function ($item) {
					return $item->getClusterId();
				}, $alreadyClustered));
				if (count($uniqueOldClusterIds) === 1) {
					// There's only one old cluster for all already clustered detections
					// in this new cluster, so we'll use that
					$cluster = array_values(array_filter($detectionClusters, function ($clusters) : bool {
						return count($clusters) >= 1;
					}))[0][0];
					$clusterCentroid = self::calculateCentroidOfDetections($alreadyClustered);
				} else {
					// This new cluster contains detections from different existing clusters
					// we need a completely new cluster for the not yet assigned detections
					$cluster = new FaceCluster();
					$cluster->setTitle('');
					$cluster->setUserId($userId);
					$this->faceClusters->insert($cluster);
					$clusterCentroid = self::calculateCentroidOfDetections($notYetClustered);
				}
			} else {
				// we need a completely new cluster since none of the detections
				// in this new cluster have been assigned
				$cluster = new FaceCluster();
				$cluster->setTitle('');
				$cluster->setUserId($userId);
				$this->faceClusters->insert($cluster);

				$clusterCentroid = self::calculateCentroidOfDetections($clusterDetections);
			}
			foreach ($notYetClustered as $detection) {
				$distance = new Euclidean();
				// If threshold is larger than 0 and $clusterCentroid is not the null vector
				if ($detection->getThreshold() > 0.0 && count(array_filter($clusterCentroid, fn ($el) => $el !== 0.0)) > 0) {
					// If a threshold is set for this detection and its vector is farther away from the centroid
					// than the threshold, skip assigning this detection to the cluster
					$distanceValue = $distance->compute($clusterCentroid, $detection->getVector());
					if ($distanceValue >= $detection->getThreshold()) {
						continue;
					}
				}

				$this->faceDetections->assocWithCluster($detection, $cluster);
			}
		}

		$this->pruneClusters($userId);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function pruneClusters(string $userId): void {
		$clusters = $this->faceClusters->findByUserId($userId);

		if (count($clusters) === 0) {
			$this->logger->debug('No face clusters found');
			return;
		}

		foreach ($clusters as $cluster) {
			$detections = $this->faceDetections->findByClusterId($cluster->getId());

			$filesWithDuplicateFaces = $this->findFilesWithDuplicateFaces($detections);
			if (count($filesWithDuplicateFaces) === 0) {
				continue;
			}

			$centroid = self::calculateCentroidOfDetections($detections);

			foreach ($filesWithDuplicateFaces as $fileDetections) {
				$detectionsByDistance = [];
				foreach ($fileDetections as $detection) {
					$distance = new Euclidean();
					$detectionsByDistance[$detection->getId()] = $distance->compute($centroid, $detection->getVector());
				}
				asort($detectionsByDistance);
				$bestMatchingDetectionId = array_keys($detectionsByDistance)[0];

				foreach ($fileDetections as $detection) {
					if ($detection->getId() === $bestMatchingDetectionId) {
						continue;
					}
					$detection->setClusterId(null);
					$this->faceDetections->update($detection);
				}
			}
		}
	}

	/**
	 * @param FaceDetection[] $detections
	 * @return list<float>
	 */
	public static function calculateCentroidOfDetections(array $detections): array {
		// init 128 dimensional vector
		/** @var list<float> $sum */
		$sum = [];
		for ($i = 0; $i < self::DIMENSIONS; $i++) {
			$sum[] = 0;
		}

		if (count($detections) === 0) {
			return $sum;
		}

		foreach ($detections as $detection) {
			$sum = array_map(function ($el, $el2) {
				return $el + $el2;
			}, $detection->getVector(), $sum);
		}

		$centroid = array_map(function ($el) use ($detections) {
			return $el / count($detections);
		}, $sum);

		return $centroid;
	}

	/**
	 * @param array<FaceDetection> $detections
	 * @return array<int,FaceDetection[]>
	 */
	private function findFilesWithDuplicateFaces(array $detections): array {
		$files = [];
		foreach ($detections as $detection) {
			if (!isset($files[$detection->getFileId()])) {
				$files[$detection->getFileId()] = [];
			}
			$files[$detection->getFileId()][] = $detection;
		}

		/** @var array<int,FaceDetection[]> $filesWithDuplicateFaces */
		$filesWithDuplicateFaces = array_filter($files, function ($detections) {
			return count($detections) > 1;
		});

		return $filesWithDuplicateFaces;
	}
}
