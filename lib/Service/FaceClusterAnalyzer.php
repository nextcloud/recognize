<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\Recognize\Clustering\MRDistance;
use OCA\Recognize\Clustering\MstClusterer;
use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Kernels\Distance\Euclidean;

class FaceClusterAnalyzer {
	public const MIN_SAMPLE_SIZE = 4; // Conservative value: 10
	public const MIN_CLUSTER_SIZE = 6; // Conservative value: 10
	public const MAX_CLUSTER_EDGE_LENGHT = 99.0;
	public const MIN_CLUSTER_SEPARATION = 0.0;
	// For incremental clustering
	public const MAX_INNER_CLUSTER_RADIUS = 0.44;
	public const MIN_DETECTION_SIZE = 0.03;

	public const DIMENSIONS = 128;

	private FaceDetectionMapper $faceDetections;
	private FaceClusterMapper $faceClusters;
	private TagManager $tagManager;
	private Logger $logger;

	private array $edges;
	private Labeled $dataset;

	private MRDistance $distanceKernel;

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
		$this->logger->debug('ClusterDebug: Retrieving face detections for user ' . $userId);

		$detections = $this->faceDetections->findByUserId($userId);

		$detections = array_values(array_filter($detections, fn ($detection) =>
			$detection->getHeight() > self::MIN_DETECTION_SIZE && $detection->getWidth() > self::MIN_DETECTION_SIZE
		));

		$unclusteredDetections = $this->assignToExistingClusters($userId, $detections);

		if (count($unclusteredDetections) < max(self::MIN_SAMPLE_SIZE, self::MIN_CLUSTER_SIZE)) {
			$this->logger->debug('ClusterDebug: Not enough face detections found');
			return;
		}

		$this->logger->debug('ClusterDebug: Found ' . count($unclusteredDetections) . " unclustered detections. Calculating clusters.");

		$this->dataset = new Labeled(array_map(function (FaceDetection $detection): array {
			return $detection->getVector();
		}, $detections), array_combine(array_keys($detections), array_keys($detections)), false);

		$this->distanceKernel = new MRDistance(self::MIN_SAMPLE_SIZE, $this->dataset, new Euclidean());

		$primsStartTime = microtime(true);// DEBUG

		// Prim's algorithm:

		$this->unconnectedVertices = array_combine(array_keys($detections), array_keys($detections));

		$firstVertex = current($this->unconnectedVertices);
		$firstVertexVector = $this->dataset->sample($firstVertex);
		unset($this->unconnectedVertices[$firstVertex]);

		$this->edges = [];
		foreach ($this->unconnectedVertices as $vertex) {
			$this->edges[$vertex] = [$firstVertex, $this->distanceKernel->distance($firstVertex, $firstVertexVector, $vertex, $this->dataset->sample($vertex))];
		}

		while (count($this->unconnectedVertices) > 0) {
			$minDistance = INF;
			$minVertex = null;

			foreach ($this->unconnectedVertices as $vertex) {
				$distance = $this->edges[$vertex][1];
				if ($distance < $minDistance) {
					$minDistance = $distance;
					$minVertex = $vertex;
				}
			}

			unset($this->unconnectedVertices[$minVertex]);
			$minVertexVector = $this->dataset->sample($minVertex);

			foreach ($this->unconnectedVertices as $vertex) {
				$distance = $this->distanceKernel->distance($minVertex, $minVertexVector, $vertex, $this->dataset->sample($vertex));
				if ($this->edges[$vertex][1] > $distance) {
					$this->edges[$vertex] = [$minVertex,$distance];
				}
			}
		}

		$executionTime = (microtime(true) - $primsStartTime);// DEBUG
		$this->logger->debug('ClusterDebug: Prims algo took '.$executionTime." secs.");// DEBUG

		// Calculate the face clusters based on the minimum spanning tree.

		$mstClusterer = new MstClusterer($this->edges, self::MIN_CLUSTER_SIZE, null, self::MAX_CLUSTER_EDGE_LENGHT, self::MIN_CLUSTER_SEPARATION);
		$flatClusters = $mstClusterer->processCluster();

		$numberOfClusteredDetections = 0;

		foreach ($flatClusters as $flatCluster) {
			$cluster = new FaceCluster();
			$cluster->setTitle('');
			$cluster->setUserId($userId);
			$this->faceClusters->insert($cluster);

			$detectionKeys = $flatCluster->getVertexKeys();
			$clusterCentroid = self::calculateCentroidOfDetections(array_map(static fn ($key) => $unclusteredDetections[$key], $detectionKeys));


			foreach ($detectionKeys as $detectionKey) {
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
