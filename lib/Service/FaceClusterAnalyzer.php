<?php

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
	public const MAX_INNER_CLUSTER_RADIUS = 0.42;

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
	public function calculateClusters(string $userId) {
		/**
		 * @var $detections FaceDetection[]
		 */
		$detections = $this->faceDetections->findByUserId($userId);

		if (count($detections) === 0) {
			$this->logger->debug('No face detections found');
			return;
		}

		// Here we use RubixMLs DBSCAN clustering algorithm
		$dataset = new Unlabeled(array_map(function (FaceDetection $detection) : array {
			return $detection->getVector();
		}, $detections));
		$clusterer = new DBSCAN(self::MAX_INNER_CLUSTER_RADIUS, self::MIN_CLUSTER_DENSITY, new BallTree(20, new Euclidean()));
		$results = $clusterer->predict($dataset);
		$numClusters = max($results);

		$this->logger->debug('Found '.$numClusters.' face clusters');

		for ($i = 0; $i <= $numClusters; $i++) {
			$keys = array_keys($results, $i);
			$clusterDetections = array_map(function ($key) use ($detections) : FaceDetection {
				return $detections[$key];
			}, $keys);
			$detectionsWithClusters = array_map(function ($detection) : array {
				return [$detection, $this->faceClusters->findByDetectionId($detection->getId())];
			}, $clusterDetections);

			// Since recognize works incrementally, we need to check if some of these face
			// detections have been added to an existing cluster already
			$alreadyClustered = array_values(array_filter($detectionsWithClusters, function ($item) : bool {
				return count($item[1]) >= 1;
			}));

			$notYetClustered = array_filter($detectionsWithClusters, function ($item) : bool {
				return count($item[1]) === 0;
			});

			if (count($alreadyClustered) > 0) {
				$uniqueOldClusterIds = array_unique(array_map(function ($item) {
					return $item[1][0]->getId();
				}, $alreadyClustered));
				if (count($uniqueOldClusterIds) === 1) {
					// There's only one old cluster for all already clustered detections
					// in this new cluster, so we'll use that
					$cluster = $alreadyClustered[0][1][0];
					$clusterCentroid = self::calculateCentroidOfDetections(array_map(fn ($item) => $item[0], $alreadyClustered));
				} else {
					// This new cluster contains detections from different existing clusters
					// we need a completely new cluster for the not yet assigned detections
					$cluster = new FaceCluster();
					$cluster->setTitle('');
					$cluster->setUserId($userId);
					$cluster = $this->faceClusters->insert($cluster);
					$clusterCentroid = self::calculateCentroidOfDetections(array_map(fn ($item) => $item[0], $notYetClustered));
				}
			} else {
				// we need a completely new cluster since none of the detections
				// in this new cluster have been assigned
				$cluster = new FaceCluster();
				$cluster->setTitle('');
				$cluster->setUserId($userId);

				/**
				 * @var FaceCluster $cluster
				 */
				$cluster = $this->faceClusters->insert($cluster);

				$clusterCentroid = self::calculateCentroidOfDetections($clusterDetections);
			}
			foreach ($notYetClustered as $item) {
				/** @var FaceDetection $detection */
				$detection = $item[0];
				$distance = new Euclidean();
				if ($detection->getThreshold() > 0.0) {
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
		/**
		 * @var $clusters FaceCluster[]
		 */
		$clusters = $this->faceClusters->findByUserId($userId);

		if (count($clusters) === 0) {
			$this->logger->debug('No face clusters found');
			return;
		}

		foreach ($clusters as $cluster) {
			/**
			 * @var $detections FaceDetection[]
			 */
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
	 * @return array<float>
	 */
	public static function calculateCentroidOfDetections(array $detections): array {
		// init 128 dimensional vector
		$sum = [];
		for ($i = 0; $i < 128; $i++) {
			$sum[] = 0;
		}

		foreach ($detections as $detection) {
			$sum = array_map(function ($el, $i) use ($sum) {
				return $el + $sum[$i];
			}, $detection->getVector(), array_keys($sum));
		}
		$centroid = array_map(function ($el) use ($detections) {
			return $el / count($detections);
		}, $sum);
		return $centroid;
	}

	/**
	 * @param array $detections
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
