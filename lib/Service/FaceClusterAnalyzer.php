<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\Recognize\Clustering\HDBSCAN;
use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Kernels\Distance\Euclidean;

class FaceClusterAnalyzer {
	public const MIN_DATASET_SIZE = 30;
	public const MIN_SAMPLE_SIZE = 4; // Conservative value: 10
	public const MIN_CLUSTER_SIZE = 5; // Conservative value: 10
	public const MIN_DETECTION_SIZE = 0.03;
	public const DIMENSIONS = 128;
	public const SAMPLE_SIZE_EXISTING_CLUSTERS = 42;

	private FaceDetectionMapper $faceDetections;
	private FaceClusterMapper $faceClusters;
	private Logger $logger;

	public function __construct(FaceDetectionMapper $faceDetections, FaceClusterMapper $faceClusters, Logger $logger) {
		$this->faceDetections = $faceDetections;
		$this->faceClusters = $faceClusters;
		$this->logger = $logger;
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @throws \JsonException
	 */
	public function calculateClusters(string $userId): void {
		$this->logger->debug('ClusterDebug: Retrieving face detections for user ' . $userId);

		$unclusteredDetections = $this->faceDetections->findUnclusteredByUserId($userId);

		$unclusteredDetections = array_values(array_filter($unclusteredDetections, fn ($detection) =>
			$detection->getHeight() > self::MIN_DETECTION_SIZE && $detection->getWidth() > self::MIN_DETECTION_SIZE
		));

		if (count($unclusteredDetections) < self::MIN_DATASET_SIZE) {
			$this->logger->debug('ClusterDebug: Not enough face detections found');
			return;
		}

		$this->logger->debug('ClusterDebug: Found ' . count($unclusteredDetections) . " unclustered detections. Calculating clusters.");

		$sampledDetections = [];

		$existingClusters = $this->faceClusters->findByUserId($userId);
		foreach ($existingClusters as $existingCluster) {
			$sampled = $this->faceDetections->findClusterSample($existingCluster->getId(), self::SAMPLE_SIZE_EXISTING_CLUSTERS);
			$sampledDetections = array_merge($sampledDetections, $sampled);
		}

		$detections = array_merge($unclusteredDetections, $sampledDetections);

		$dataset = new Labeled(array_map(static function (FaceDetection $detection): array {
			return $detection->getVector();
		}, $detections), array_combine(array_keys($detections), array_keys($detections)), false);

		$hdbscan = new HDBSCAN($dataset, self::MIN_CLUSTER_SIZE, self::MIN_SAMPLE_SIZE);

		$numberOfClusteredDetections = 0;
		$clusters = $hdbscan->predict();

		foreach ($clusters as $flatCluster) {
			$detectionKeys = array_keys($flatCluster->getClusterVertices());
			$clusterCentroid = self::calculateCentroidOfDetections(array_map(static fn ($key) => $detections[$key], $detectionKeys));

			/**
			 * @var FaceDetection
			 */
			$detection = current(array_filter($detectionKeys, fn ($key) => $detections[$key]->getClusterId() !== null));
			$clusterId = $detection->getClusterId();

			if ($clusterId !== null) {
				$cluster = $this->faceClusters->find($clusterId);
			} else {
				$cluster = new FaceCluster();
				$cluster->setTitle('');
				$cluster->setUserId($userId);
				$this->faceClusters->insert($cluster);
			}

			foreach ($detectionKeys as $detectionKey) {
				if ($detectionKey >= count($unclusteredDetections)) {
					// This is a sampled, already clustered detection, ignore.
					continue;
				}

				// If threshold is larger than 0 and $clusterCentroid is not the null vector
				if ($unclusteredDetections[$detectionKey]->getThreshold() > 0.0 && count(array_filter($clusterCentroid, fn ($el) => $el !== 0.0)) > 0) {
					// If a threshold is set for this detection and its vector is farther away from the centroid
					// than the threshold, skip assigning this detection to the cluster
					$distanceValue = self::distance($clusterCentroid, $unclusteredDetections[$detectionKey]->getVector());
					if ($distanceValue >= $unclusteredDetections[$detectionKey]->getThreshold()) {
						continue;
					}
				}

				$this->faceDetections->assocWithCluster($unclusteredDetections[$detectionKey], $cluster);
				$numberOfClusteredDetections += 1;
			}
		}

		$this->logger->debug('ClusterDebug: Clustering complete. Total num of clustered detections: ' . $numberOfClusteredDetections);
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
			$sum = array_map(static function ($el, $el2) {
				return $el + $el2;
			}, $detection->getVector(), $sum);
		}

		$centroid = array_map(static function ($el) use ($detections) {
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
		$filesWithDuplicateFaces = array_filter($files, static function ($detections) {
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
			if ($detection->getClusterId() !== null) {
				continue;
			}
			foreach ($clusters as $cluster) {
				$clusterDetections = $this->faceDetections->findByClusterId($cluster->getId());
				if (count($clusterDetections) > 50) {
					$clusterDetections = array_map(fn ($key) => $clusterDetections[$key], array_rand($clusterDetections, 50));
				}
				$clusterCentroid = self::calculateCentroidOfDetections($clusterDetections);
				if ($detection->getThreshold() > 0 && self::distance($clusterCentroid, $detection->getVector()) >= $detection->getThreshold()) {
					continue;
				}
				foreach ($clusterDetections as $clusterDetection) {
					$distance = self::distance($clusterDetection->getVector(), $detection->getVector());
					if (
						$distance <= self::MAX_INNER_CLUSTER_RADIUS
						&& (!isset($bestCluster) || $distance < $bestClusterDistance)
					) {
						$bestCluster = $cluster;
						$bestClusterDistance = self::distance($clusterDetection->getVector(), $detection->getVector());
						break;
					}
				}
			}
			if ($bestCluster !== null) {
				$this->faceDetections->assocWithCluster($detection, $bestCluster);
				continue;
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
