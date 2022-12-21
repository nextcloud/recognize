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
	public const MIN_CLUSTER_DENSITY = 6;
	public const MAX_INNER_CLUSTER_RADIUS = 0.44;
	public const DIMENSIONS = 128;
	public const MIN_DETECTION_SIZE = 0.09;

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

		$detections = array_values(array_filter($detections, fn ($detection) =>
			$detection->getHeight() > self::MIN_DETECTION_SIZE && $detection->getWidth() > self::MIN_DETECTION_SIZE
		));

		if (count($detections) === 0) {
			$this->logger->debug('No face detections found');
			return;
		}

		$unclusteredDetections = $this->assignToExistingClusters($userId, $detections);

		// Here we use RubixMLs DBSCAN clustering algorithm
		$dataset = new Unlabeled(array_map(function (FaceDetection $detection) : array {
			return $detection->getVector();
		}, $unclusteredDetections));

		$clusterer = new DBSCAN(self::MAX_INNER_CLUSTER_RADIUS, self::MIN_CLUSTER_DENSITY, new BallTree(100, new Euclidean()));
		$this->logger->debug('Calculate clusters for '.count($detections).' faces');
		$results = $clusterer->predict($dataset);
		$numClusters = max($results);

		$this->logger->debug('Found '.$numClusters.' new face clusters');

		for ($i = 0; $i <= $numClusters; $i++) {
			$keys = array_keys($results, $i);
			$clusterDetections = array_map(function ($key) use ($detections) : FaceDetection {
				return $detections[$key];
			}, $keys);

			$cluster = new FaceCluster();
			$cluster->setTitle('');
			$cluster->setUserId($userId);
			$this->faceClusters->insert($cluster);

			$clusterCentroid = self::calculateCentroidOfDetections($clusterDetections);

			foreach ($clusterDetections as $detection) {
				// If threshold is larger than 0 and $clusterCentroid is not the null vector
				if ($detection->getThreshold() > 0.0 && count(array_filter($clusterCentroid, fn ($el) => $el !== 0.0)) > 0) {
					// If a threshold is set for this detection and its vector is farther away from the centroid
					// than the threshold, skip assigning this detection to the cluster
					$distanceValue = self::distance($clusterCentroid, $detection->getVector());
					if ($distanceValue >= $detection->getThreshold()) {
						continue;
					}
				}

				$this->faceDetections->assocWithCluster($detection, $cluster);
			}
		}

		$this->pruneDuplicateFilesFromClusters($userId);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function pruneDuplicateFilesFromClusters(string $userId): void {
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
					$detectionsByDistance[$detection->getId()] = self::distance($centroid, $detection->getVector());
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

	/**
	 * @param string $userId
	 * @param list<FaceDetection> $detections
	 * @return list<FaceDetection>
	 * @throws \OCP\DB\Exception
	 */
	private function assignToExistingClusters(string $userId, array $detections): array {
		$clusters = $this->faceClusters->findByUserId($userId);

		if (count($clusters) === 0) {
			return $detections;
		}

		$unclusteredDetections = [];

		foreach ($detections as $detection) {
			$bestCluster = null;
			$bestClusterDistance = 999;
			foreach ($clusters as $cluster) {
				$clusterDetections = $this->faceDetections->findByClusterId($cluster->getId());
				$clusterCentroid = self::calculateCentroidOfDetections($clusterDetections);
				if (count($clusterDetections) > 50) {
					$clusterDetections = array_map(fn ($key) => $clusterDetections[$key], array_rand($clusterDetections, 50));
				}
				foreach ($clusterDetections as $clusterDetection) {
					if (
						self::distance($clusterDetection->getVector(), $detection->getVector()) <= self::MAX_INNER_CLUSTER_RADIUS
						&& self::distance($clusterCentroid, $detection->getVector()) >= $detection->getThreshold()
						&& (!isset($bestCluster) || self::distance($clusterDetection->getVector(), $detection->getVector()) < $bestClusterDistance)
					) {
						$bestCluster = $cluster;
						$bestClusterDistance = self::distance($clusterDetection->getVector(), $detection->getVector());
						break;
					}
				}
			}
			$unclusteredDetections[] = $detection;
		}
		return $unclusteredDetections;
	}

	private static ?Euclidean $distance;

	/**
	 * @param list<int|float> $v1
	 * @param list<int|float> $v2
	 * @return float
	 */
	private static function distance(array $v1, array $v2): float {
		if (!isset(self::$distance)) {
			self::$distance = new Euclidean();
		}
		return self::$distance->compute($v1, $v2);
	}
}
