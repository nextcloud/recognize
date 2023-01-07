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
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Graph\Trees\BallTree;
use Rubix\ML\Kernels\Distance\Euclidean;
use Rubix\ML\Kernels\Distance\Distance;
use Rubix\ML\Graph\Nodes\Hypersphere;
use Rubix\ML\Graph\Nodes\Clique;
use Rubix\ML\Graph\Nodes\Ball;
use Rubix\ML\Exceptions\InvalidArgumentException;
use SplObjectStorage;

class MrdBallTree extends BallTree {
	private array $coreDistances;
	private int $coreDistSampleSize;
	private Labeled $dataset;

	/**
	 * @param int $maxLeafSize
	 * @param int $coreDistSampleSize
	 * @param \Rubix\ML\Kernels\Distance\Distance|null $kernel
	 * @throws \Rubix\ML\Exceptions\InvalidArgumentException
	 */
	public function __construct(int $maxLeafSize = 30, int $coreDistSampleSize = 30, ? Distance $kernel = null) {
		if ($maxLeafSize < 1) {
			throw new InvalidArgumentException('At least one sample is required'
				. " to form a leaf node, $maxLeafSize given.");
		}

		if ($coreDistSampleSize < 2) {
			throw new InvalidArgumentException('At least two samples are required'
				. " to calculate core distance, $coreDistSampleSize given.");
		}

		$this->maxLeafSize = $maxLeafSize;
		$this->coreDistSampleSize = $coreDistSampleSize;
		$this->kernel = $kernel ?? new Euclidean();
	}

	/**
	 * Run a k nearest neighbors search excluding the provided group of samples and return the labels, and distances for the nn in a tuple.
	 *
	 * This is essentially a stripped down version of nearest() in the parent class provided by Rubix.
	 *
	 * TODO:    Implement last minimum distance filter for the hyperspheres to accelerate the search.
	 *                  Hyperspheres with centroid distance+radius smaller than the previous nearest neighbor cannot contain
	 *                  the next nearest neighbor.
	 *
	 * @internal
	 *
	 * @param int $sampleKey
	 * @param list<string|int|float> $sample
	 * @param list<mixed> $groupLabels
	 * @param int $k
	 * @throws \Rubix\ML\Exceptions\InvalidArgumentException
	 * @return array{list<mixed>,list<float>}
	 */
	public function nearestNotInGroup(int $sampleKey, array $sample, array $groupLabels, int $k = 1): array {
		$visited = new SplObjectStorage();

		$stack = $this->path($sample);

		/*$samples =*/$labels = $distances = [];

		while ($current = array_pop($stack)) {
			if ($current instanceof Ball) {
				$radius = $distances[$k - 1] ?? INF;

				foreach ($current->children() as $child) {
					if (!$visited->contains($child)) {
						if ($child instanceof Hypersphere) {
							$distance = $this->kernel->compute($sample, $child->center());

							if ($distance - $child->radius() < $radius) {
								$stack[] = $child;

								continue;
							}
						}

						$visited->attach($child);
					}
				}

				$visited->attach($current);

				continue;
			}

			if ($current instanceof Clique) {
				$dataset = $current->dataset();
				$neighborLabels = $dataset->labels();

				foreach ($dataset->samples() as $key => $neighbor) {
					if (!in_array($neighborLabels[$key], $groupLabels)) {
						$distances[] = $this->computeMrd($sampleKey, $sample, $neighborLabels[$key], $neighbor);
						$labels[] = $neighborLabels[$key];
					}
				}

				//$samples = array_merge($samples, $dataset->samples());
				//$labels = array_merge($labels, $dataset->labels());

				array_multisort($distances, /*$samples,*/$labels);

				if (count($labels) > $k) {
					//$samples = array_slice($samples, 0, $k);
					$labels = array_slice($labels, 0, $k);
					$distances = array_slice($distances, 0, $k);
				}

				$visited->attach($current);
			}
		}

		return [ /*$samples,*/$labels, $distances];
	}

	private function getCoreDistance(int $index): float {
		if (!isset($this->coreDistances[$index])) {
			[$_1, $_2, $distances] = $this->nearest($this->dataset->sample($index), $this->coreDistSampleSize);
			$this->coreDistances[$index] = max($distances);
		}

		return $this->coreDistances[$index];
	}

	/**
	 * Compute the mutual reachability distance between two vectors.
	 *
	 * @internal
	 *
	 * @param int $a
	 * @param array $a_vector
	 * @param int $b
	 * @param array $b_vector
	 * @return float
	 */
	private function computeMrd(int $a, array $a_vector, int $b, array $b_vector): float {
		$distance = $this->kernel->compute($a_vector, $b_vector);

		return max($distance, $this->getCoreDistance($a), $this->getCoreDistance($b));
	}

	/**
	 * Insert a root node and recursively split the dataset until a terminating
	 * condition is met.
	 *
	 * @internal
	 *
	 * @param \Rubix\ML\Datasets\Labeled $dataset
	 * @throws \Rubix\ML\Exceptions\InvalidArgumentException
	 */
	public function grow(Labeled $dataset): void {
		$this->dataset = $dataset;
		$this->root = Ball::split($dataset, $this->kernel);

		$stack = [$this->root];

		while ($current = array_pop($stack)) {
			[$left, $right] = $current->subsets();

			$current->cleanup();

			if ($left->numSamples() > $this->maxLeafSize) {
				$node = Ball::split($left, $this->kernel);

				$current->attachLeft($node);

				$stack[] = $node;
			} elseif (!$left->empty()) {
				$current->attachLeft(Clique::terminate($left, $this->kernel));
			}

			if ($right->numSamples() > $this->maxLeafSize) {
				$node = Ball::split($right, $this->kernel);

				if ($node->isPoint()) {
					$current->attachRight(Clique::terminate($right, $this->kernel));
				} else {
					$current->attachRight($node);

					$stack[] = $node;
				}
			} elseif (!$right->empty()) {
				$current->attachRight(Clique::terminate($right, $this->kernel));
			}
		}
	}
}

class MstFaceCluster {
	private array $edges;
	private array $remainingEdges;
	private float $startingLambda;
	private float $finalLambda;
	private float $clusterWeight;
	private int $minimumClusterSize;
	private array $coreEdges;
	private bool $isRoot;
	private float $maxEdgeLength;
	private float $minClusterSeparation;




	public function __construct(array $edges, int $minimumClusterSize, ?float $startingLambda = null, float $maxEdgeLength = 0.5, float $minClusterSeparation = 0.1) {
		//Ascending sort of edges while perserving original keys.
		$this->edges = $edges;

		uasort($this->edges, function ($a, $b) {
			if ($a[1] > $b[1]) {
				return 1;
			}
			if ($a[1] < $b[1]) {
				return -1;
			}
			return 0;
		});

		$this->remainingEdges = $this->edges;

		if (is_null($startingLambda)) {
			$this->isRoot = true;
			$this->startingLambda = 0.0;
		} else {
			$this->isRoot = false;
			$this->startingLambda = $startingLambda;
		}

		$this->minimumClusterSize = $minimumClusterSize;

		$this->coreEdges = [];

		$this->clusterWeight = 0.0;

		$this->maxEdgeLength = $maxEdgeLength;
		$this->minClusterSeparation = $minClusterSeparation;
	}

	public function processCluster(): array {
		$currentLambda = $lastLambda = $this->startingLambda;
		$edgeLength = INF;

		while (true) {
			$edgeCount = count($this->remainingEdges);

			if ($edgeCount < ($this->minimumClusterSize - 1)) {
				if ($edgeLength > $this->maxEdgeLength) {
					return [];
				}

				$this->finalLambda = $currentLambda;
				$this->coreEdges = $this->remainingEdges;

				return [$this];
			}

			$vertexConnectedTo = array_key_last($this->remainingEdges);
			$currentLongestEdge = array_pop($this->remainingEdges);
			$vertexConnectedFrom = $currentLongestEdge[0];

			$edgeLength = $currentLongestEdge[1];

			if ($edgeLength > $this->maxEdgeLength) {
				// Prevent formation of clusters with edges longer than the maximum edge length
				$currentLambda = $lastLambda = 1 / $edgeLength;
			} elseif ($edgeLength > 0.0) {
				$currentLambda = 1 / $edgeLength;
			}

			$this->clusterWeight += ($currentLambda - $lastLambda) * $edgeCount;
			$lastLambda = $currentLambda;

			if (!$this->pruneFromCluster($vertexConnectedTo) && !$this->pruneFromCluster($vertexConnectedFrom)) {
				// This cluster will (probably) split into two child clusters:

				$childClusterEdges1 = $this->getChildClusterEdges($vertexConnectedTo);
				$childClusterEdges2 = $this->getChildClusterEdges($vertexConnectedFrom);

				if ($edgeLength < $this->minClusterSeparation) {
					$this->remainingEdges = count($childClusterEdges1) > count($childClusterEdges2) ? $childClusterEdges1 : $childClusterEdges2;
					continue;
				}

				// Choose clusters using excess of mass method:
				// Return a list of children if the weight of all children is more than $this->clusterWeight.
				// Otherwise return the current cluster and discard the children. This way we "choose" a combination
				// of cluster that has weighs the most (i.e. has most excess of mass). Always discard the root cluster.
				$this->finalLambda = $currentLambda;

				$childCluster1 = new MstFaceCluster($childClusterEdges1, $this->minimumClusterSize, $this->finalLambda, $this->maxEdgeLength, $this->minClusterSeparation);
				$childCluster2 = new MstFaceCluster($childClusterEdges2, $this->minimumClusterSize, $this->finalLambda, $this->maxEdgeLength, $this->minClusterSeparation);

				// Resolve all chosen child clusters recursively
				$childClusters = array_merge($childCluster1->processCluster(), $childCluster2->processCluster());

				$childrenWeight = 0.0;
				foreach ($childClusters as $childCluster) {
					$childrenWeight += $childCluster->getClusterWeight();
					array_merge($this->coreEdges, $childCluster->getCoreEdges());
				}

				if (($childrenWeight > $this->clusterWeight) || $this->isRoot) {
					return $childClusters;
				}

				return [$this];
			}

			if ($edgeLength > $this->maxEdgeLength) {
				$this->edges = $this->remainingEdges;
			}
		}
	}

	private function pruneFromCluster(int $vertexId): bool {
		$edgeIndicesToPrune = [];
		$vertexStack = [$vertexId];

		while (!empty($vertexStack)) {
			$currentVertex = array_pop($vertexStack);

			if (count($edgeIndicesToPrune) >= ($this->minimumClusterSize - 1)) {
				return false;
			}

			// Traverse the MST edges backward
			if (isset($this->remainingEdges[$currentVertex]) && !in_array($currentVertex, $edgeIndicesToPrune)) {
				$incomingEdge = $this->remainingEdges[$currentVertex];
				$edgeIndicesToPrune[] = $currentVertex;

				$vertexStack[] = $incomingEdge[0];
			}

			// Traverse the MST edges forward
			foreach ($this->remainingEdges as $key => $edge) {
				if (($edge[0] == $currentVertex) && !in_array($key, $edgeIndicesToPrune)) {
					$vertexStack[] = $key;
					$edgeIndicesToPrune[] = $key;
				}
			}
		}

		// Prune edges
		foreach ($edgeIndicesToPrune as $edgeToPrune) {
			unset($this->remainingEdges[$edgeToPrune]);
		}

		return true;
	}

	private function getChildClusterEdges(int $vertexId): array {
		$vertexStack = [$vertexId];
		$edgesInCluster = [];

		while (!empty($vertexStack)) {
			$currentVertex = array_pop($vertexStack);

			// Traverse the MST edges backward
			if (isset($this->remainingEdges[$currentVertex]) && !isset($edgesInCluster[$currentVertex])) {
				$incomingEdge = $this->remainingEdges[$currentVertex];

				//Edges are indexed by the vertex they're connected to
				$edgesInCluster[$currentVertex] = $incomingEdge;

				$vertexStack[] = $incomingEdge[0];
			}

			// Traverse the MST edges forward
			foreach ($this->remainingEdges as $key => $edge) {
				if ($edge[0] == $currentVertex && !isset($edgesInCluster[$key])) {
					$vertexStack[] = $key;
					$edgesInCluster[$key] = $edge;
				}
			}
		}

		return $edgesInCluster;
	}

	public function getClusterWeight(): float {
		return $this->clusterWeight;
	}

	public function getVertexKeys(): array {
		$vertexKeys = [];

		foreach ($this->edges as $key => $edge) {
			$vertexKeys[] = $key;
			$vertexKeys[] = $edge[0];
		}

		return array_unique($vertexKeys);
	}

	public function getCoreEdges(): array {
		return $this->coreEdges;
	}
}


class FaceClusterAnalyzer {
	public const MIN_SAMPLE_SIZE = 4; // Conservative value: 10
	public const MIN_CLUSTER_SIZE = 6; // Conservative value: 10
	public const MAX_CLUSTER_EDGE_LENGHT = 99.0;
	public const MIN_CLUSTER_SEPARATION = 0.0;
	public const DIMENSIONS = 128;
	public const ROOT_VERTEX = null;

	private FaceDetectionMapper $faceDetections;
	private FaceClusterMapper $faceClusters;
	private TagManager $tagManager;
	private Logger $logger;

	private array $connectedVertices;
	private array $distanceHeap;
	private array $staleEdgeHeap;
	private array $edges;
	private float $shortestStaleDistance;
	private float $shortestDistance;
	private Labeled $dataset;
	private MrdBallTree $detectionsTree;

	public function __construct(FaceDetectionMapper $faceDetections, FaceClusterMapper $faceClusters, TagManager $tagManager, Logger $logger) {
		$this->faceDetections = $faceDetections;
		$this->faceClusters = $faceClusters;
		$this->tagManager = $tagManager;
		$this->logger = $logger;
	}

	private function updateNnForVertex($vertexId): void {
		if (is_null($vertexId)) {
			return;
		}

		[$nearestLabel, $nearestDistance] = $this->detectionsTree->nearestNotInGroup($vertexId, $this->dataset->sample($vertexId), $this->connectedVertices);

		// Two possibilities here: First, it's possibe that the distance to the nearest neighbor is less than
		// the previous distance established for this vertex. Then we can remove this
		// stale key from the $staleEdgeHeap. The second possibility is that the new distance is not the shortest
		// available to the nearest neighbor in which case we just push the current $staleDetectionKey
		// back into stale heap with the new longer distance.
		if (($this->distanceHeap[$nearestLabel[0]] ?? INF) > $nearestDistance[0]) {
			$this->distanceHeap[$nearestLabel[0]] = $nearestDistance[0];
			// If the nearest neighbor vertex already had an edge connected to it
			// (with a longer distance) set the existing edge as stale.
			if (isset($this->edges[$nearestLabel[0]])) {
				$this->staleEdgeHeap[] = $this->edges[$nearestLabel[0]];
			}

			$this->edges[$nearestLabel[0]] = [$vertexId, $nearestDistance[0]];
			arsort($this->distanceHeap);
			$this->shortestDistance = end($this->distanceHeap);
		} else {
			$this->staleEdgeHeap[] = [$vertexId, $nearestDistance[0]];
		}

		$distanceColumn = array_column($this->staleEdgeHeap, 1);
		array_multisort($distanceColumn, SORT_DESC, $this->staleEdgeHeap);
		$this->shortestStaleDistance = end($this->staleEdgeHeap)[1];

		return;
	}



	/**
	 * @throws \OCP\DB\Exception
	 * @throws \JsonException
	 */
	public function calculateClusters(string $userId): void {
		$this->logger->debug('ClusterDebug: Retrieving face detections for user ' . $userId);

		$detections = $this->faceDetections->findByUserId($userId);

		if (count($detections) < max(self::MIN_SAMPLE_SIZE, self::MIN_CLUSTER_SIZE)) {
			$this->logger->debug('ClusterDebug: Not enough face detections found');
			return;
		}

		$this->logger->debug('ClusterDebug: Found ' . count($detections) . " detections. Calculating clusters.");

		$this->dataset = new Labeled(array_map(function (FaceDetection $detection): array {
			return $detection->getVector();
		}, $detections), array_combine(array_keys($detections), array_keys($detections)), false);

		$this->detectionsTree = new MrdBallTree(10, self::MIN_SAMPLE_SIZE, new Euclidean());
		$this->detectionsTree->grow($this->dataset);

		// A quick and dirty Prim's algorithm:
		//TODO: Slight performance increase could perhaps be gained by replacing arsort/array_multisort in the $this->updateNnForVertex with a function that would
		//              insert all new distances into the corresponding arrays perserving the descending order.
		//TODO: MrdBallTree->nearestNotInGroup requires optimization (see definition of MrdBallTree)

		$this->connectedVertices = [];
		$this->distanceHeap = []; //array_fill_keys(array_keys($detections), INF);
		$this->distanceHeap[array_key_first($detections)] = 0; // [*distance*,]
		$this->shortestDistance = 0.0;

		// Updating nearest neighbor distance for all points is unnecessary, so we
		// keep track of "stale" nearest neighbor distances. These stale distances
		// will only be updated if the current shortest distance in $distanceHeap exceeds
		// the shortest stale distance.
		$this->staleEdgeHeap = []; // Will contain tuples of detection keys and corresponding (stale) nearest neighbor distances.
		$this->shortestStaleDistance = INF;

		// Key values of $edges[] will correspond to the vertex the edge connects to while the array at each row
		// will be a tuple containing the vertex connected from and the connection cost/distance
		$this->edges[array_key_first($detections)] = [self::ROOT_VERTEX, INF];
		//$this->edges = [];

		$numVertices = count($this->dataset->labels()) - 1; // No need to loop through the last vertex.

		while (count($this->connectedVertices) < $numVertices) {
			// If necessary, update the distances in the stale heap
			while ($this->shortestStaleDistance < $this->shortestDistance) {
				$staleDetectionKey = array_pop($this->staleEdgeHeap)[0];
				$this->updateNnForVertex($staleDetectionKey);
			}

			// Get the next edge with the smallest cost
			$addedVertex = array_key_last($this->distanceHeap); // Technically it'd be equivalent to do key($distanceHeap) here...
			unset($this->distanceHeap[$addedVertex]);
			$this->connectedVertices[] = $addedVertex;

			$this->staleEdgeHeap[] = $this->edges[$addedVertex];
			$this->updateNnForVertex($addedVertex);
		}

		unset($this->edges[array_key_first($detections)]);

		$mstClusterer = new MstFaceCluster($this->edges, self::MIN_CLUSTER_SIZE, null, self::MAX_CLUSTER_EDGE_LENGHT, self::MIN_CLUSTER_SEPARATION);
		$flatClusters = $mstClusterer->processCluster();

		// Write clusters to db
		// TODO: For now just discard all previous clusters.
		if (count($this->faceClusters->findByUserId($userId)) > 0) {
			$this->faceClusters->deleteAll();
		}

		$numberOfClusteredDetections = 0;

		foreach ($flatClusters as $flatCluster) {
			$cluster = new FaceCluster();
			$cluster->setTitle('');
			$cluster->setUserId($userId);
			$this->faceClusters->insert($cluster);

			$detectionKeys = $flatCluster->getVertexKeys();

			foreach ($detectionKeys as $detectionKey) {
				$this->faceDetections->assocWithCluster($detections[$detectionKey], $cluster);
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


		return;
	}
}
