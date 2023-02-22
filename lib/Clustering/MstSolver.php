<?php
/*
 * Copyright (c) 2023 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Clustering;

use OCA\Recognize\Vendor\Rubix\ML\Datasets\Labeled;
use OCA\Recognize\Vendor\Rubix\ML\Kernels\Distance\Distance;

class MstSolver {
	private MrdBallTree $tree;
	private Distance $kernel;
	private bool $useTrueMst;

	public function __construct(Labeled $fullDataset, int $maxLeafSize = 20, int $sampleSize = 5, ?Distance $kernel = null, ?array $oldCoreDistances = null, bool $useTrueMst = true) {
		$this->kernel = $kernel ?? new SquaredDistance();

		$this->tree = new MrdBallTree($maxLeafSize, $sampleSize, $this->kernel);

		$this->tree->grow($fullDataset);
		$this->tree->precalculateCoreDistances($oldCoreDistances);

		$this->useTrueMst = $useTrueMst;
	}

	public function kernel(): Distance {
		return $this->kernel;
	}

	public function getCoreNeighborDistances(): array {
		return $this->tree->getCoreNeighborDistances();
	}

	public function getTree(): MrdBallTree {
		return $this->tree;
	}

	private function updateEdges($queryNode, $referenceNode, array &$newEdges, array &$vertexToSetId): void {
		$querySamples = $queryNode->dataset()->samples();
		$queryLabels = $queryNode->dataset()->labels();
		$referenceSamples = $referenceNode->dataset()->samples();
		$referenceLabels = $referenceNode->dataset()->labels();

		$longestDistance = 0.0;
		$shortestDistance = INF;

		foreach ($querySamples as $queryKey => $querySample) {
			$queryLabel = $queryLabels[$queryKey];
			$querySetId = $vertexToSetId[$queryLabel];

			if ($this->tree->getCoreDistance($queryLabel) > ($newEdges[$querySetId]["distance"] ?? INF)) {
				// The core distance of the current vertex is greater than the current best edge
				// for this setId. This means that the MRD will always be greater than the current best.
				continue;
			}

			foreach ($referenceSamples as $referenceKey => $referenceSample) {
				$referenceLabel = $referenceLabels[$referenceKey];
				$referenceSetId = $vertexToSetId[$referenceLabel];

				if ($querySetId === $referenceSetId) {
					continue;
				}

				$distance = $this->tree->computeMrd($queryLabel, $querySample, $referenceLabel, $referenceSample);

				if ($distance < ($newEdges[$querySetId]["distance"] ?? INF)) {
					$newEdges[$querySetId] = ["vertexFrom" => $queryLabel, "vertexTo" => $referenceLabel, "distance" => $distance];
				}
			}
			$candidateDist = $newEdges[$querySetId]["distance"] ?? INF;
			if ($candidateDist > $longestDistance) {
				$longestDistance = $candidateDist;
			}

			if ($candidateDist < $shortestDistance) {
				$shortestDistance = $candidateDist;
			}
		}

		// Update the bound of the query node
		if ($this->kernel instanceof SquaredDistance) {
			$longestDistance = min($longestDistance, (2 * sqrt($queryNode->radius()) + sqrt($shortestDistance)) ** 2);
		} else {
			$longestDistance = min($longestDistance, 2 * $queryNode->radius() + $shortestDistance);
		}

		$queryNode->setLongestDistance($longestDistance);
	}

	/**
	 * @param \OCA\Recognize\Clustering\DualTreeBall|\OCA\Recognize\Clustering\DualTreeClique $queryNode
	 * @param \OCA\Recognize\Clustering\DualTreeBall|\OCA\Recognize\Clustering\DualTreeClique $referenceNode
	 * @param array $newEdges
	 * @param array $vertexToSetId
	 * @return void
	 */
	private function findSetNeighbors($queryNode, $referenceNode, array &$newEdges, array &$vertexToSetId): void {
		if ($queryNode->isFullyConnected() && $referenceNode->isFullyConnected()) {
			if ($queryNode->getSetId() === $referenceNode->getSetId()) {
				// These nodes are connected and in the same set, so we can prune this reference node.
				return;
			}
		}

		// if d(Q,R) > d(Q) then
		//  return;

		$nodeDistance = $this->tree->nodeDistance($queryNode, $referenceNode);

		if ($nodeDistance > 0.0) {
			// Calculate smallest possible bound (i.e., d(Q) ):
			if ($queryNode->isFullyConnected()) {
				$currentBound = min($newEdges[$queryNode->getSetId()]["distance"] ?? INF, $queryNode->getLongestDistance());
			} else {
				$currentBound = $queryNode->getLongestDistance();
			}
			// If node distance is greater than the longest possible edge in this node,
			// prune this reference node
			if ($nodeDistance > $currentBound) {
				return;
			}
		}

		if ($queryNode instanceof DualTreeClique && $referenceNode instanceof DualTreeClique) {
			$this->updateEdges($queryNode, $referenceNode, $newEdges, $vertexToSetId);
			return;
		}

		if ($queryNode instanceof DualTreeClique) {
			foreach ($referenceNode->children() as $child) {
				$this->findSetNeighbors($queryNode, $child, $newEdges, $vertexToSetId);
			}
			return;
		}

		if ($referenceNode instanceof DualTreeClique) {
			$longestDistance = 0.0;

			$queryLeft = $queryNode->left();
			$queryRight = $queryNode->right();

			$this->findSetNeighbors($queryLeft, $referenceNode, $newEdges, $vertexToSetId);
			$this->findSetNeighbors($queryRight, $referenceNode, $newEdges, $vertexToSetId);
		} else { // if ($queryNode instanceof DualTreeBall && $referenceNode instanceof DualTreeBall)
			$queryLeft = $queryNode->left();
			$queryRight = $queryNode->right();
			$referenceLeft = $referenceNode->left();
			$referenceRight = $referenceNode->right();

			$this->findSetNeighbors($queryLeft, $referenceLeft, $newEdges, $vertexToSetId);
			$this->findSetNeighbors($queryRight, $referenceRight, $newEdges, $vertexToSetId);
			$this->findSetNeighbors($queryLeft, $referenceRight, $newEdges, $vertexToSetId);
			$this->findSetNeighbors($queryRight, $referenceLeft, $newEdges, $vertexToSetId);
		}

		$longestLeft = $queryLeft->getLongestDistance();
		$longestRight = $queryRight->getLongestDistance();

		// TODO: min($longestLeft, $longestRight) + 2 * ($queryNode->radius()) <--- Can be made tighter?
		if ($this->kernel instanceof SquaredDistance) {
			$longestDistance = max($longestLeft, $longestRight);
			$longestLeft = (sqrt($longestLeft) + 2 * (sqrt($queryNode->radius()) - sqrt($queryLeft->radius()))) ** 2;
			$longestRight = (sqrt($longestRight) + 2 * (sqrt($queryNode->radius()) - sqrt($queryRight->radius()))) ** 2;
			$longestDistance = min($longestDistance, min($longestLeft, $longestRight), (sqrt(min($longestLeft, $longestRight)) + 2 * (sqrt($queryNode->radius()))) ** 2);
		} else {
			$longestDistance = max($longestLeft, $longestRight);
			$longestLeft = $longestLeft + 2 * ($queryNode->radius() - $queryLeft->radius());
			$longestRight = $longestRight + 2 * ($queryNode->radius() - $queryRight->radius());
			$longestDistance = min($longestDistance, min($longestLeft, $longestRight), min($longestLeft, $longestRight) + 2 * ($queryNode->radius()));
		}

		$queryNode->setLongestDistance($longestDistance);

		return;
	}

	/**
	 * @return list<array{vertexFrom:int|string,vertexTo:int|string,distance:float}>
	 */
	public function getMst(): array {
		$edges = [];

		// MST generation using dual-tree boruvka algorithm

		$treeRoot = $this->tree->getRoot();

		$treeRoot->resetFullyConnectedStatus();

		$allLabels = $this->tree->getDataset()->labels();

		$vertexToSetId = array_combine($allLabels, range(0, count($allLabels) - 1));

		$vertexSets = [];
		foreach ($vertexToSetId as $vertex => $setId) {
			$vertexSets[$setId] = [$vertex];
		}

		if (!$this->useTrueMst) {
			$treeRoot->resetLongestEdge();
		}

		// Use nearest neighbors known from determining core distances for each vertex to
		// get the first set of $newEdges (we essentially can skip the first round of Boruvka):
		$newEdges = [];

		foreach ($allLabels as $label) {
			[$coreNeighborLabels, $coreNeighborDistances] = $this->tree->getCoreNeighbors($label);

			$coreDistance = end($coreNeighborDistances);

			foreach ($coreNeighborLabels as $neighborLabel) {
				if ($neighborLabel === $label) {
					continue;
				}

				if ($this->tree->getCoreDistance($neighborLabel) <= $coreDistance) {
					// This point is our nearest neighbor in mutual reachability terms, so
					// an edge spanning these vertices will belong to the MST.
					$newEdges[] = ["vertexFrom" => $label, "vertexTo" => $neighborLabel, "distance" => $coreDistance];
					break;
				}
			}
		}

		///////////////////////////////////////////////////////////////////////////////
		// Main dual tree Boruvka loop:

		while (true) {
			//Add new edges
			//Update vertex to set/set to vertex mappings
			foreach ($newEdges as $connectingEdge) {
				$setId1 = $vertexToSetId[$connectingEdge["vertexFrom"]];
				$setId2 = $vertexToSetId[$connectingEdge["vertexTo"]];

				if ($setId1 === $setId2) {
					// These sets have already been merged earlier in this loop
					continue;
				}

				$edges[] = $connectingEdge;

				if (count($vertexSets[$setId1]) < count($vertexSets[$setId2])) {
					// Make a switch such that the larger set is always Id1
					[$setId1, $setId2] = [$setId2, $setId1];
				}

				// Assign all vertices in set 2 to set 1
				foreach ($vertexSets[$setId2] as $vertexLabel) {
					$vertexToSetId[$vertexLabel] = $setId1;
				}

				$vertexSets[$setId1] = array_merge($vertexSets[$setId1], $vertexSets[$setId2]);
				unset($vertexSets[$setId2]);
			}

			// Check for exit condition
			if (count($vertexSets) === 1) {
				break;
			}

			//Update the tree
			if ($this->useTrueMst || empty($newEdges)) {
				$treeRoot->resetLongestEdge();
			}

			if (!empty($newEdges)) {
				$treeRoot->propagateSetChanges($vertexToSetId);
			}

			// Clear the array for a set of new edges
			$newEdges = [];

			$this->findSetNeighbors($treeRoot, $treeRoot, $newEdges, $vertexToSetId);
		}

		return $edges;
	}
}
