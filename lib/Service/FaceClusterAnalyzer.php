<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\Recognize\ChineseWhispersClusterer;
use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Kernels\Distance\Euclidean;

class FaceClusterAnalyzer {
	public const NEIGHBOR_RADIUS = 0.4;
	public const MIN_NEIGHBOR_COUNT = 5;
	public const ITERATIONS = 30;

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
		$this->logger->debug('ClusterDebug: Retrieving face detections for user ' . $userId);

		$detections = $this->faceDetections->findByUserId($userId);

		if (count($detections) < self::MIN_NEIGHBOR_COUNT) {
			$this->logger->debug('ClusterDebug: Not enough face detections found');
			return;
		}

		$this->logger->debug('ClusterDebug: Found ' . count($detections) . " detections. Calculating clusters.");

		$dataset = new Labeled(array_map(function (FaceDetection $detection): array {
			return $detection->getVector();
		}, $detections), array_combine(array_keys($detections), array_keys($detections)), false);

		$cwClusterer = new ChineseWhispersClusterer($dataset, new Euclidean(), self::NEIGHBOR_RADIUS, self::MIN_NEIGHBOR_COUNT);

		$cwClusterer->iterate(self::ITERATIONS);

		$detectionClusters = $cwClusterer->getClusterIds();

		// Write clusters to db
		// TODO: For now just discard all previous clusters.
		if (count($this->faceClusters->findByUserId($userId)) > 0) {
			$this->faceClusters->deleteAll();
		}

		foreach (array_unique($detectionClusters) as $clusterId) {
			$clusterDetectionKeys = array_keys($detectionClusters, $clusterId);

			if (is_null($clusterId)) {
				foreach ($clusterDetectionKeys as $detectionKey) {
					$detections[$detectionKey]->setClusterId(null);
					$this->faceDetections->update($detections[$detectionKey]);
				}
			} else {
				$cluster = new FaceCluster();
				$cluster->setTitle('');
				$cluster->setUserId($userId);
				$this->faceClusters->insert($cluster);

				foreach ($clusterDetectionKeys as $detectionKey) {
					$this->faceDetections->assocWithCluster($detections[$detectionKey], $cluster);
				}
			}
		}

		$this->logger->debug('ClusterDebug: Clustering complete. ');
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

	/**
	 * @throws \OCP\DB\Exception
	 * @param array<int> $clusterIdsToMerge
	 * @param int $parentClusterId
	 * @return void
	 */
	private function mergeClusters(array $clusterIdsToMerge, int $parentClusterId): void {
		$clusterIdsToMerge = array_unique($clusterIdsToMerge);
		foreach ($clusterIdsToMerge as $childClusterId) {
			if ($childClusterId == $parentClusterId) {
				continue;
			}

			$detections = $this->faceDetections->findByClusterId($childClusterId);
			$parentCluster = $this->faceClusters->find($parentClusterId);


			try {
				$childCluster = $this->faceClusters->find($childClusterId);
			} catch (\Exception $e) {
				$this->logger->debug('ExtraDebug: Child cluster already deleted: ' . $childClusterId);
				continue;
			}

			foreach ($detections as $detection) {
				$this->faceDetections->assocWithCluster($detection, $parentCluster);
			}

			$this->faceClusters->delete($childCluster);
		}
	}
}
