<?php
/*
 * Copyright (c) 2023 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Clustering;

// TODO: core edges are not always stored properly (if two halves of the remaining clusters are both pruned at the same time)
// TODO: store vertex lambda length (relative to cluster lambda length) for all vertices for soft clustering.
class MstClusterer {
	/**
	 * @var array<int, array{int, float}>
	 */
	private array $edges;
	/**
	 * @var array<int, array{int, float}>
	 */
	private array $remainingEdges;
	private float $startingLambda;
	private float $clusterWeight;
	private int $minimumClusterSize;
	private array $coreEdges;
	private bool $isRoot;
	private float $maxEdgeLength;
	private float $minClusterSeparation;

	/**
	 * @param array<int, array{int, float}>  $edges
	 * @param int $minimumClusterSize
	 * @param float|null $startingLambda
	 * @param float $maxEdgeLength
	 * @param float $minClusterSeparation
	 */
	public function __construct(array $edges, int $minimumClusterSize, ?float $startingLambda = null, float $maxEdgeLength = 0.5, float $minClusterSeparation = 0.1) {
		//Ascending sort of edges while perserving original keys.
		$this->edges = $edges;

		uasort($this->edges, static function ($a, $b) {
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
				// of clusters that weigh the most (i.e. have most (excess of) mass). Always discard the root cluster.
				$finalLambda = $currentLambda;

				$childCluster1 = new MstClusterer($childClusterEdges1, $this->minimumClusterSize, $finalLambda, $this->maxEdgeLength, $this->minClusterSeparation);
				$childCluster2 = new MstClusterer($childClusterEdges2, $this->minimumClusterSize, $finalLambda, $this->maxEdgeLength, $this->minClusterSeparation);

				// Resolve all chosen child clusters recursively
				$childClusters = array_merge($childCluster1->processCluster(), $childCluster2->processCluster());

				$childrenWeight = 0.0;
				foreach ($childClusters as $childCluster) {
					$childrenWeight += $childCluster->getClusterWeight();
					$this->coreEdges = array_merge($this->coreEdges, $childCluster->getCoreEdges());
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


	/**
	 * @returns list<int>
	 */
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
