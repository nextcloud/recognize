<?php
/*
 * Copyright (c) 2023 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Clustering;

// TODO: store vertex lambda length (relative to cluster lambda length) for all vertices for improved soft clustering (see https://hdbscan.readthedocs.io/en/latest/soft_clustering.html)
class MstClusterer {
	private array $edges;

	/**
	 * @var list<array{vertexFrom: int, vertexTo:int, distance:float}>
	 */
	private array $remainingEdges;
	private float $startingLambda;
	private float $clusterWeight;
	private int $minimumClusterSize;
	private array $coreEdges;
	private bool $isRoot;
	private array $mapVerticesToEdges;
	private float $minClusterSeparation;
	private float $maxEdgeLength;

	public function __construct(array $edges, ?array $mapVerticesToEdges, int $minimumClusterSize, ?float $startingLambda = null, float $minClusterSeparation = 0.1, float $maxEdgeLength = 0.5) {
		//Ascending sort of edges while perserving original keys.
		$this->edges = $edges;

		uasort($this->edges, function ($a, $b) {
			if ($a["distance"] > $b["distance"]) {
				return 1;
			}
			if ($a["distance"] < $b["distance"]) {
				return -1;
			}
			return 0;
		});

		$this->remainingEdges = $this->edges;

		if ($mapVerticesToEdges === null) {
			$mapVerticesToEdges = [];
			foreach ($this->edges as $edgeIndex => $edge) {
				$mapVerticesToEdges[$edge['vertexFrom']][$edgeIndex] = true;
				$mapVerticesToEdges[$edge['vertexTo']][$edgeIndex] = true;
			}
		}

		$this->mapVerticesToEdges = $mapVerticesToEdges;

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
					// This cluster is too sparse and probably just noise
                 		   	return [];
                		}
				
				
				foreach ($this->coreEdges as &$edge) {
					$edge['finalLambda'] = $currentLambda;
				}
				unset($edge);

				foreach (array_keys($this->remainingEdges) as $edgeKey) {
					$this->edges[$edgeKey]['finalLambda'] = $currentLambda;
				}

				return [$this];
			}

			if ($edgeCount < (2 * $this->minimumClusterSize - 1)) {
				// The end is near; this cluster cannot be split into two anymore
				$this->coreEdges = $this->remainingEdges;
			}

			$currentLongestEdgeKey = array_key_last($this->remainingEdges);
			$currentLongestEdge = array_pop($this->remainingEdges);

			$vertexConnectedFrom = $currentLongestEdge["vertexFrom"];
			$vertexConnectedTo = $currentLongestEdge["vertexTo"];
			$edgeLength = $currentLongestEdge["distance"];

			unset($this->mapVerticesToEdges[$vertexConnectedFrom][$currentLongestEdgeKey]);
			unset($this->mapVerticesToEdges[$vertexConnectedTo][$currentLongestEdgeKey]);

			if ($edgeLength > $this->maxEdgeLength) {
                		// Prevent formation of clusters with edges longer than the maximum edge length
				// This is done by forcing the weight of the current cluster to zero
                		$lastLambda = $currentLambda = 1 / $edgeLength;				
            		} else if ($edgeLength > 0.0) {
				$currentLambda = 1 / $edgeLength;
			}

			$this->clusterWeight += ($currentLambda - $lastLambda) * $edgeCount;
			$lastLambda = $currentLambda;

			$this->edges[$currentLongestEdgeKey]["finalLambda"] = $currentLambda;

			if (!$this->pruneFromCluster($vertexConnectedTo, $currentLambda) && !$this->pruneFromCluster($vertexConnectedFrom, $currentLambda)) {
				// This cluster will (probably) split into two child clusters:

				[$childClusterEdges1, $childClusterVerticesToEdges1] = $this->getChildClusterComponents($vertexConnectedTo);
				[$childClusterEdges2, $childClusterVerticesToEdges2] = $this->getChildClusterComponents($vertexConnectedFrom);

				if ($edgeLength < $this->minClusterSeparation) {
					if (count($childClusterEdges1) > count($childClusterEdges2)) {
						$this->remainingEdges = $childClusterEdges1;
						$this->mapVerticesToEdges = $childClusterVerticesToEdges1;
					} else {
						$this->remainingEdges = $childClusterEdges2;
						$this->mapVerticesToEdges = $childClusterVerticesToEdges2;
					}
					continue;
				}

				// Choose clusters using excess of mass method:
				// Return a list of children if the weight of all children is more than $this->clusterWeight.
				// Otherwise return the current cluster and discard the children. This way we "choose" a combination
				// of clusters that weigh the most (i.e. have most (excess of) mass). Always discard the root cluster.


				$childCluster1 = new MstClusterer($childClusterEdges1, $childClusterVerticesToEdges1, $this->minimumClusterSize, $currentLambda, $this->minClusterSeparation, $this->maxEdgeLength);
				$childCluster2 = new MstClusterer($childClusterEdges2, $childClusterVerticesToEdges2, $this->minimumClusterSize, $currentLambda, $this->minClusterSeparation, $this->maxEdgeLength);

				// Resolve all chosen child clusters recursively
				$childClusters = array_merge($childCluster1->processCluster(), $childCluster2->processCluster());

				$childrenWeight = 0.0;
				foreach ($childClusters as $childCluster) {
					$childrenWeight += $childCluster->getClusterWeight();
					$this->coreEdges = array_merge($this->coreEdges, $childCluster->getCoreEdges());
				}

				if (($childrenWeight >= $this->clusterWeight) || $this->isRoot) {
					return $childClusters;
				} else {
					foreach (array_keys($this->remainingEdges) as $edgeKey) {
						$this->edges[$edgeKey]['finalLambda'] = $currentLambda;
					}
				}

				return [$this];
			}
			
			if ($edgeLength > $this->maxEdgeLength) {
				// Any pruned vertices were too far away to be part of the cluster
                		$this->edges = $this->remainingEdges;
            		}
		}
	}

	private function pruneFromCluster(int $vertexId, float $currentLambda): bool {
		$edgeIndicesToPrune = [];
		$verticesToPrune = [];
		$vertexStack = [$vertexId];

		while (!empty($vertexStack)) {
			$currentVertex = array_pop($vertexStack);
			$verticesToPrune[] = $currentVertex;

			if (count($verticesToPrune) >= $this->minimumClusterSize) {
				return false;
			}

			foreach (array_keys($this->mapVerticesToEdges[$currentVertex]) as $edgeKey) {
				if (isset($edgeIndicesToPrune[$edgeKey])) {
					continue;
				}

				if ($this->remainingEdges[$edgeKey]["vertexFrom"] === $currentVertex) {
					$vertexStack[] = $this->remainingEdges[$edgeKey]["vertexTo"];
					$edgeIndicesToPrune[$edgeKey] = true;
				} elseif ($this->remainingEdges[$edgeKey]["vertexTo"] === $currentVertex) {
					$vertexStack[] = $this->remainingEdges[$edgeKey]["vertexFrom"];
					$edgeIndicesToPrune[$edgeKey] = true;
				}
			}
		}

		// Prune edges
		foreach (array_keys($edgeIndicesToPrune) as $edgeToPrune) {
			$this->edges[$edgeToPrune]['finalLambda'] = $currentLambda;
			unset($this->remainingEdges[$edgeToPrune]);
		}

		// Prune vertices to edges map (not stricly necessary but saves some memory)
		foreach ($verticesToPrune as $vertexLabel) {
			unset($this->mapVerticesToEdges[$vertexLabel]);
		}

		return true;
	}

	private function getChildClusterComponents(int $vertexId): array {
		$vertexStack = [$vertexId];
		$edgeIndicesInCluster = [];
		$verticesInCluster = [];

		while (!empty($vertexStack)) {
			$currentVertex = array_pop($vertexStack);
			$verticesInCluster[$currentVertex] = $this->mapVerticesToEdges[$currentVertex];

			foreach (array_keys($this->mapVerticesToEdges[$currentVertex]) as $edgeKey) {
				if (isset($edgeIndicesInCluster[$edgeKey])) {
					continue;
				}

				if ($this->remainingEdges[$edgeKey]["vertexFrom"] === $currentVertex) {
					$vertexStack[] = $this->remainingEdges[$edgeKey]["vertexTo"];
					$edgeIndicesInCluster[$edgeKey] = true;
				} elseif ($this->remainingEdges[$edgeKey]["vertexTo"] === $currentVertex) {
					$vertexStack[] = $this->remainingEdges[$edgeKey]["vertexFrom"];
					$edgeIndicesInCluster[$edgeKey] = true;
				}
			}
		}

		// Collecting the edges is done in a separate loop to perserve the ordering according to length.
		// (See constructor.)
		$edgesInCluster = [];
		foreach ($this->remainingEdges as $edgeKey => $edge) {
			if (isset($edgeIndicesInCluster[$edgeKey])) {
				$edgesInCluster[$edgeKey] = $edge;
			}
		}

		return [$edgesInCluster, $verticesInCluster];
	}

	public function getClusterWeight(): float {
		return $this->clusterWeight;
	}

	public function getClusterVertices(): array {
		$vertices = [];

		foreach ($this->edges as $edge) {
			$vertices[$edge["vertexTo"]] = min($edge["finalLambda"], $vertices[$edge["vertexTo"]] ?? INF);
			$vertices[$edge["vertexFrom"]] = min($edge["finalLambda"], $vertices[$edge["vertexFrom"]] ?? INF);
		}

		return $vertices;
	}

	public function getCoreEdges(): array {
		return $this->coreEdges;
	}

	public function getClusterEdges(): array {
		return $this->edges;
	}

	public function getCoreVertices(): array {
		$vertices = [];

		foreach ($this->coreEdges as $edge) {
			$vertices[$edge["vertexTo"]] = min($edge["finalLambda"], $vertices[$edge["vertexTo"]] ?? INF);
			$vertices[$edge["vertexFrom"]] = min($edge["finalLambda"], $vertices[$edge["vertexFrom"]] ?? INF);
		}

		return $vertices;
	}
}
