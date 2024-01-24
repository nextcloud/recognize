<?php
/*
 * Copyright (c) 2023 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Clustering;

use \OCA\Recognize\Vendor\Rubix\ML\Datasets\Labeled;
use \OCA\Recognize\Vendor\Rubix\ML\Graph\Nodes\Ball;
use \OCA\Recognize\Vendor\Rubix\ML\Graph\Nodes\Hypersphere;
use \OCA\Recognize\Vendor\Rubix\ML\Graph\Trees\BallTree;
use \OCA\Recognize\Vendor\Rubix\ML\Kernels\Distance\Distance;

class MrdBallTree extends BallTree {
	private ?Labeled $dataset = null;
	private array $nativeInterpointCache = [];
	private array $coreDistances = [];
	private array $coreNeighborDistances = [];
	private int $sampleSize;
	private array $nodeDistances;
	private \SplObjectStorage $nodeIds;
	private float $radiusDiamFactor;

	/**
	 * @param int $maxLeafSize
	 * @param int $coreDistSampleSize
	 * @param \OCA\Recognize\Vendor\Rubix\ML\Kernels\Distance\Distance|null $kernel
	 * @throws \OCA\Recognize\Vendor\Rubix\ML\Exceptions\InvalidArgumentException
	 */
	public function __construct(int $maxLeafSize = 30, int $sampleSize = 5, ?Distance $kernel = null) {
		if ($maxLeafSize < 1) {
			throw new \InvalidArgumentException('At least one sample is required'
				. " to form a leaf node, $maxLeafSize given.");
		}

		$this->maxLeafSize = $maxLeafSize;
		$this->sampleSize = $sampleSize;

		$this->kernel = $kernel ?? new SquaredDistance();
		$this->radiusDiamFactor = $this->kernel instanceof SquaredDistance ? 4 : 2;

		$this->nodeDistances = [];
		$this->nodeIds = new \SplObjectStorage();
	}

	public function getCoreNeighborDistances(): array {
		return $this->coreNeighborDistances;
	}

	/**
	 * @param \OCA\Recognize\Clustering\DualTreeBall|\OCA\Recognize\Clustering\DualTreeClique $queryNode
	 * @param \OCA\Recognize\Clustering\DualTreeBall|\OCA\Recognize\Clustering\DualTreeClique $referenceNode
	 * @return float
	 */
	public function nodeDistance($queryNode, $referenceNode): float {
		// Use cache to accelerate repeated queries
		if ($this->nodeIds->contains($queryNode)) {
			$queryNodeId = $this->nodeIds[$queryNode];
		} else {
			$queryNodeId = $this->nodeIds->count();
			$this->nodeIds[$queryNode] = $queryNodeId;
		}

		if ($this->nodeIds->contains($referenceNode)) {
			$referenceNodeId = $this->nodeIds[$referenceNode];
		} else {
			$referenceNodeId = $this->nodeIds->count();
			$this->nodeIds[$referenceNode] = $referenceNodeId;
		}

		if ($referenceNodeId === $queryNodeId) {
			return (-$this->radiusDiamFactor * $queryNode->radius());
		}

		$smallIndex = min($queryNodeId, $referenceNodeId);
		$largeIndex = max($queryNodeId, $referenceNodeId);

		if (isset($this->nodeDistances[$smallIndex][$largeIndex])) {
			$nodeDistance = $this->nodeDistances[$smallIndex][$largeIndex];
		} else {
			$nodeDistance = $this->kernel->compute($queryNode->center(), $referenceNode->center());

			if ($this->kernel instanceof SquaredDistance) {
				$nodeDistance = sqrt($nodeDistance) - sqrt($queryNode->radius()) - sqrt($referenceNode->radius());
				$nodeDistance = abs($nodeDistance) * $nodeDistance;
			} else {
				$nodeDistance = $nodeDistance - $queryNode->radius() - $referenceNode->radius();
			}

			$this->nodeDistances[$smallIndex][$largeIndex] = $nodeDistance;
		}

		return $nodeDistance;
	}

	/**
	 * Get tree root.
	 *
	 * @internal
	 *
	 * @return DualTreeBall
	 */
	public function getRoot(): Ball {
		return $this->root;
	}

	/**
	 * Get the dataset the tree was grown on.
	 *
	 * @internal
	 *
	 * @return Labeled
	 */
	public function getDataset(): Labeled {
		return $this->dataset;
	}

	/**
	 * @param \OCA\Recognize\Clustering\DualTreeBall|\OCA\Recognize\Clustering\DualTreeClique $queryNode
	 * @param \OCA\Recognize\Clustering\DualTreeBall|\OCA\Recognize\Clustering\DualTreeClique $referenceNode
	 * @param int $k
	 * @param float $maxRange
	 * @param array $bestDistances
	 * @return void
	 */
	private function updateNearestNeighbors($queryNode, $referenceNode, $k, $maxRange, &$bestDistances): void {
		$querySamples = $queryNode->dataset()->samples();
		$queryLabels = $queryNode->dataset()->labels();
		$referenceSamples = $referenceNode->dataset()->samples();
		$referenceLabels = $referenceNode->dataset()->labels();

		$longestDistance = 0.0;
		$shortestDistance = INF;

		foreach ($querySamples as $queryKey => $querySample) {
			$queryLabel = $queryLabels[$queryKey];

			$bestDistance = $bestDistances[$queryLabel];

			foreach ($referenceSamples as $referenceKey => $referenceSample) {
				$referenceLabel = $referenceLabels[$referenceKey];

				if ($queryLabel === $referenceLabel) {
					continue;
				}

				// Calculate native distance
				$distance = $this->cachedComputeNative($queryLabel, $querySample, $referenceLabel, $referenceSample);

				if ($distance < $bestDistance) {
					//Minimize array queries within these loops:
					$coreNeighborDistances = & $this->coreNeighborDistances[$queryLabel];
					$coreNeighborDistances[$referenceLabel] = $distance;

					if (count($coreNeighborDistances) >= $k) {
						asort($coreNeighborDistances);
						$coreNeighborDistances = array_slice($coreNeighborDistances, 0, $k, true);
						$bestDistance = min(end($coreNeighborDistances), $maxRange);
					}
				}
			}

			if ($bestDistance > $longestDistance) {
				$longestDistance = $bestDistance;
			}

			if ($bestDistance < $shortestDistance) {
				$shortestDistance = $bestDistance;
			}
			$bestDistances[$queryLabel] = $bestDistance;
		}

		if ($this->kernel instanceof SquaredDistance) {
			$longestDistance = min($longestDistance, (2 * sqrt($queryNode->radius()) + sqrt($shortestDistance)) ** 2);
		} else {
			$longestDistance = min($longestDistance, 2 * $queryNode->radius() + $shortestDistance);
		}
		$queryNode->setLongestDistance($longestDistance);
	}

	private function findNearestNeighbors($queryNode, $referenceNode, $k, $maxRange, &$bestDistances): void {
		$nodeDistance = $this->nodeDistance($queryNode, $referenceNode);

		if ($nodeDistance > 0.0) {
			// Calculate smallest possible bound (i.e., d(Q) ):
			$currentBound = $queryNode->getLongestDistance();

			// If node distance is greater than the longest possible edge in this node,
			// prune this reference node
			if ($nodeDistance > $currentBound) {
				return;
			}
		}

		if ($queryNode instanceof DualTreeClique && $referenceNode instanceof DualTreeClique) {
			$this->updateNearestNeighbors($queryNode, $referenceNode, $k, $maxRange, $bestDistances);
			return;
		}

		if ($queryNode instanceof DualTreeClique) {
			foreach ($referenceNode->children() as $child) {
				$this->findNearestNeighbors($queryNode, $child, $k, $maxRange, $bestDistances);
			}
			return;
		}

		if ($referenceNode instanceof DualTreeClique) {
			$longestDistance = 0.0;

			$queryLeft = $queryNode->left();
			$queryRight = $queryNode->right();

			$this->findNearestNeighbors($queryLeft, $referenceNode, $k, $maxRange, $bestDistances);
			$this->findNearestNeighbors($queryRight, $referenceNode, $k, $maxRange, $bestDistances);
		} else {
			// --> if ($queryNode instanceof DualTreeBall && $referenceNode instanceof DualTreeBall)
			$queryLeft = $queryNode->left();
			$queryRight = $queryNode->right();
			$referenceLeft = $referenceNode->left();
			$referenceRight = $referenceNode->right();

			// TODO: traverse closest neighbor nodes first
			$this->findNearestNeighbors($queryLeft, $referenceLeft, $k, $maxRange, $bestDistances);
			$this->findNearestNeighbors($queryLeft, $referenceRight, $k, $maxRange, $bestDistances);
			$this->findNearestNeighbors($queryRight, $referenceLeft, $k, $maxRange, $bestDistances);
			$this->findNearestNeighbors($queryRight, $referenceRight, $k, $maxRange, $bestDistances);
		}

		$longestLeft = $queryLeft->getLongestDistance();
		$longestRight = $queryRight->getLongestDistance();

		// TODO: min($longestLeft, $longestRight) + 2 * ($queryNode->radius()) <--- Can be made tighter by using the shortest distance from child.
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

	public function kNearestAll($k, float $maxRange = INF): void {
		$this->coreNeighborDistances = [];

		$allLabels = $this->dataset->labels();
		$bestDistances = [];
		foreach ($allLabels as $label) {
			$bestDistances[$label] = $maxRange;
		}

		if ($this->root === null) {
			return;
		}

		$treeRoot = $this->root;

		$treeRoot->resetFullyConnectedStatus();
		$treeRoot->resetLongestEdge();

		$this->findNearestNeighbors($treeRoot, $treeRoot, $k, $maxRange, $bestDistances);
	}

	/**
	 * Precompute core distances for the current dataset to accelerate
	 * subsequent MRD queries. Optionally, utilize core distances that've
	 * been previously determined for (a subset of) the current dataset.
	 * Returns the updated core distances for future use.
	 *
	 * @internal
	 *
	 * @param array|null $oldCoreNeighbors
	 * @return array
	 */

	public function precalculateCoreDistances(?array $oldCoreNeighbors = null) {
		if (empty($this->dataset)) {
			throw new \Exception("Precalculation of core distances requested but dataset is empty. Call ->grow() first!");
		}

		$labels = $this->dataset->labels();

		if ($oldCoreNeighbors !== null && !empty($oldCoreNeighbors) && count(reset($oldCoreNeighbors)) >= $this->sampleSize) {
			// Determine the search radius for core distances based on the largest old
			// core distance (points father than that cannot shorten the old core distances)
			$largestOldCoreDistance = 0.0;

			// Utilize old (possibly stale) core distance data
			foreach ($oldCoreNeighbors as $label => $oldDistances) {
				$coreDistance = (array_values($oldDistances))[$this->sampleSize - 1];

				if ($coreDistance > $largestOldCoreDistance) {
					$largestOldCoreDistance = $coreDistance;
				}

				$this->coreNeighborDistances[$label] = $oldDistances;
				$this->coreDistances[$label] = $coreDistance;
			}

			$updatedOldCoreLabels = [];

			// Don't recalculate core distances for the old labels
			$labels = array_filter($labels, function ($label) use ($oldCoreNeighbors) {
				return !isset($oldCoreNeighbors[$label]);
			});

			foreach ($labels as $label) {
				[$neighborLabels, $neighborDistances] = $this->cachedRange($label, $largestOldCoreDistance);
				// TODO: cachedRange may not return $this->sampleSize number of labels.
				$this->coreNeighborDistances[$label] = array_combine(array_slice($neighborLabels, 0, $this->sampleSize), array_slice($neighborDistances, 0, $this->sampleSize));
				$this->coreDistances[$label] = $neighborDistances[$this->sampleSize - 1];

				// If one of the old vertices is within the update radius of this new vertex,
				// check whether the old core distance needs to be updated.
				foreach ($neighborLabels as $distanceKey => $neighborLabel) {
					if (isset($oldCoreNeighbors[$neighborLabel])) {
						$newDistance = $neighborDistances[$distanceKey];
						if ($newDistance < $this->coreDistances[$neighborLabel]) {
							$this->coreNeighborDistances[$neighborLabel][$label] = $newDistance;
							$updatedOldCoreLabels[$neighborLabel] = true;
						}
					}
				}
			}

			foreach (array_keys($updatedOldCoreLabels) as $label) {
				asort($this->coreNeighborDistances[$label]);
				$this->coreNeighborDistances[$label] = array_slice($this->coreNeighborDistances[$label], 0, $this->sampleSize, true);
				$this->coreDistances[$label] = end($this->coreNeighborDistances[$label]);
			}
		} else { // $oldCoreNeighbors === null
			$this->kNearestAll($this->sampleSize, INF);

			foreach ($this->dataset->labels() as $label) {
				$this->coreDistances[$label] = end($this->coreNeighborDistances[$label]);
			}
		}

		return $this->coreNeighborDistances;
	}

	/**
	 * Inserts a new neighbor to core neighbors if the distance
	 * is greater than the current largest distance for the query label.
	 *
	 * Returns the updated core distance or INF if there are less than $this->sampleSize neighbors.
	 *
	 * @internal
	 *
	 * @param mixed $queryLabel
	 * @param mixed $referenceLabel
	 * @param float $distance
	 * @return float
	 */
	private function insertToCoreDistances($queryLabel, $referenceLabel, $distance): float {
		// Update the core distances of the queryLabel
		if (isset($this->coreDistances[$queryLabel])) {
			if ($this->coreDistances[$queryLabel] > $distance) {
				$this->coreNeighborDistances[$queryLabel][$referenceLabel] = $distance;
				asort($this->coreNeighborDistances[$queryLabel]);

				$this->coreNeighborDistances[$queryLabel] = array_slice($this->coreNeighborDistances[$queryLabel], 0, $this->sampleSize, true);
				$this->coreDistances[$queryLabel] = end($this->coreNeighborDistances[$queryLabel]);
			}
		} else {
			$this->coreNeighborDistances[$queryLabel][$referenceLabel] = $distance;

			if (count($this->coreNeighborDistances[$queryLabel]) >= $this->sampleSize) {
				asort($this->coreNeighborDistances[$queryLabel]);

				$this->coreNeighborDistances[$queryLabel] = array_slice($this->coreNeighborDistances[$queryLabel], 0, $this->sampleSize, true);
				$this->coreDistances[$queryLabel] = end($this->coreNeighborDistances[$queryLabel]);
			}
		}

		// Update the core distances of the referenceLabel (this is not necessary, but *may* accelerate the algo slightly)
		if (isset($this->coreDistances[$referenceLabel])) {
			if ($this->coreDistances[$referenceLabel] > $distance) {
				$this->coreNeighborDistances[$referenceLabel][$queryLabel] = $distance;
				asort($this->coreNeighborDistances[$referenceLabel]);

				$this->coreNeighborDistances[$referenceLabel] = array_slice($this->coreNeighborDistances[$referenceLabel], 0, $this->sampleSize, true);
				$this->coreDistances[$referenceLabel] = end($this->coreNeighborDistances[$referenceLabel]);
			}
		} else {
			$this->coreNeighborDistances[$referenceLabel][$queryLabel] = $distance;

			if (count($this->coreNeighborDistances[$referenceLabel]) > $this->sampleSize) {
				asort($this->coreNeighborDistances[$referenceLabel]);
				$this->coreNeighborDistances[$referenceLabel] = array_slice($this->coreNeighborDistances[$referenceLabel], 0, $this->sampleSize, true);
				$this->coreDistances[$referenceLabel] = end($this->coreNeighborDistances[$referenceLabel]);
			}
		}

		return $this->coreDistances[$queryLabel] ?? INF;
	}

	/**
	 * Compute the mutual reachability distance between two vectors.
	 *
	 * @internal
	 *
	 * @param int|string $a
	 * @param int|string $b
	 * @return float
	 */
	public function computeMrd($a, array $a_vector, $b, array $b_vector): float {
		$distance = $this->cachedComputeNative($a, $a_vector, $b, $b_vector);

		return max($distance, $this->getCoreDistance($a), $this->getCoreDistance($b));
	}

	public function getCoreDistance($label): float {
		if (!isset($this->coreDistances[$label])) {
			[$labels, $distances] = $this->getCoreNeighbors($label);
			$this->coreDistances[$label] = end($distances);
		}

		return $this->coreDistances[$label];
	}

	public function cachedComputeNative($a, array $a_vector, $b, array $b_vector, bool $storeNewCalculations = true): float {
		if (isset($this->coreNeighborDistances[$a][$b])) {
			return $this->coreNeighborDistances[$a][$b];
		}
		if (isset($this->coreNeighborDistances[$b][$a])) {
			return $this->coreNeighborDistances[$b][$a];
		}

		if ($a < $b) {
			$smallIndex = $a;
			$largeIndex = $b;
		} else {
			$smallIndex = $b;
			$largeIndex = $a;
		}

		if (!isset($this->nativeInterpointCache[$smallIndex][$largeIndex])) {
			$distance = $this->kernel->compute($a_vector, $b_vector);
			if ($storeNewCalculations) {
				$this->nativeInterpointCache[$smallIndex][$largeIndex] = $distance;
			}
			return $distance;
		}

		return $this->nativeInterpointCache[$smallIndex][$largeIndex];
	}

	/**
	 * Run a n nearest neighbors search on a single label and return the neighbor labels, and distances in a 2-tuple
	 *
	 *
	 * @internal
	 *
	 * @param int|string $sampleLabel
	 * @param bool $useCachedValues
	 * @throws \OCA\Recognize\Vendor\Rubix\ML\Exceptions\InvalidArgumentException
	 * @return array{list<mixed>,list<float>}
	 */
	public function getCoreNeighbors($sampleLabel, bool $useCachedValues = true): array {
		if ($useCachedValues && isset($this->coreNeighborDistances[$sampleLabel])) {
			return [array_keys($this->coreNeighborDistances[$sampleLabel]), array_values($this->coreNeighborDistances[$sampleLabel])];
		}

		$sampleKey = array_search($sampleLabel, $this->dataset->labels());
		$sample = $this->dataset->sample($sampleKey);

		$squaredDistance = $this->kernel instanceof SquaredDistance;

		/** @var list<DualTreeBall|DualTreeClique> **/
		$stack = [$this->root];
		$stackDistances = [0.0];
		$radius = INF;

		$labels = $distances = [];

		while ($current = array_pop($stack)) {
			$currentDistance = array_pop($stackDistances);

			if ($currentDistance > $radius) {
				continue;
			}

			if ($current instanceof DualTreeBall) {
				foreach ($current->children() as $child) {
					if ($child instanceof Hypersphere) {
						$distance = $this->kernel->compute($sample, $child->center());

						if ($squaredDistance) {
							$distance = sqrt($distance);
							$childRadius = sqrt($child->radius());
							$distance = $distance - $childRadius;
							$distance = abs($distance) * $distance;
						} else {
							$distance = $distance - $child->radius();
						}

						if ($distance < $radius) {
							$stack[] = $child;
							$stackDistances[] = $distance;
						}
					}
				}
				array_multisort($stackDistances, SORT_DESC, $stack);
			} elseif ($current instanceof DualTreeClique) {
				$dataset = $current->dataset();
				$neighborLabels = $dataset->labels();

				foreach ($dataset->samples() as $i => $neighbor) {
					if ($neighborLabels[$i] === $sampleLabel) {
						continue;
					}

					$distance = $this->cachedComputeNative($sampleLabel, $sample, $neighborLabels[$i], $neighbor);

					if ($distance <= $radius) {
						$labels[] = $neighborLabels[$i];
						$distances[] = $distance;
					}
				}

				if (count($labels) >= $this->sampleSize) {
					array_multisort($distances, $labels);
					$radius = $distances[$this->sampleSize - 1];
					$labels = array_slice($labels, 0, $this->sampleSize);
					$distances = array_slice($distances, 0, $this->sampleSize);
				}
			}
		}
		return [$labels, $distances];
	}

	/**
	 * Return all labels, and distances within a given radius of a sample.
	 *
	 *
	 * @internal
	 *
	 * @param int $sampleLabel
	 * @param float $radius
	 * @throws \OCA\Recognize\Vendor\Rubix\ML\Exceptions\InvalidArgumentException
	 * @throws \OCA\Recognize\Vendor\Rubix\ML\Exceptions\RuntimeException
	 * @return array{list<mixed>,list<float>}
	 */
	public function cachedRange($sampleLabel, float $radius): array {
		$sampleKey = array_search($sampleLabel, $this->dataset->labels());
		$sample = $this->dataset->sample($sampleKey);

		$squaredDistance = $this->kernel instanceof SquaredDistance;

		/** @var list<DualTreeBall|DualTreeClique> **/
		$stack = [$this->root];

		$labels = $distances = [];

		while ($current = array_pop($stack)) {
			if ($current instanceof DualTreeBall) {
				foreach ($current->children() as $child) {
					if ($child instanceof Hypersphere) {
						$distance = $this->kernel->compute($sample, $child->center());

						if ($squaredDistance) {
							$distance = sqrt($distance);
							$childRadius = sqrt($child->radius());
							$minDistance = $distance - $childRadius;
							$minDistance = abs($minDistance) * $minDistance;
							$maxDistance = ($distance + $childRadius) ** 2;
						} else {
							$childRadius = $child->radius();
							$minDistance = $distance - $childRadius;
							$maxDistance = $distance + $childRadius;
						}

						if ($minDistance < $radius) {
							if ($maxDistance < $radius && $child instanceof DualTreeBall) {
								// The whole child is within the specified radius: greedily add all sub-children recursively to the stack
								$subStack = [$child];
								while ($subStackCurrent = array_pop($subStack)) {
									foreach ($subStackCurrent->children() as $subChild) {
										if ($subChild instanceof DualTreeClique) {
											$stack[] = $subChild;
										} else {
											$subStack[] = $subChild;
										}
									}
								}
							} else {
								$stack[] = $child;
							}
						}
					}
				}
			} elseif ($current instanceof DualTreeClique) {
				$dataset = $current->dataset();
				$neighborLabels = $dataset->labels();

				foreach ($dataset->samples() as $i => $neighbor) {
					$distance = $this->cachedComputeNative($sampleLabel, $sample, $neighborLabels[$i], $neighbor);

					if ($distance <= $radius) {
						$labels[] = $neighborLabels[$i];
						$distances[] = $distance;
					}
				}
			}
		}
		array_multisort($distances, $labels);
		return [$labels, $distances];
	}

	/**
	 * Insert a root node and recursively split the dataset until a terminating
	 * condition is met. This also sets the dataset that will be used to calculate
	 * core distances. Previously calculated core distances will be stored/used
	 * despite calling grow, unless precalculateCoreDistances() is called again.
	 *
	 * @internal
	 *
	 * @param \OCA\Recognize\Vendor\Rubix\ML\Datasets\Labeled $dataset
	 * @throws \OCA\Recognize\Vendor\Rubix\ML\Exceptions\InvalidArgumentException
	 */
	public function grow(Labeled $dataset): void {
		$this->dataset = $dataset;
		$this->root = DualTreeBall::split($dataset, $this->kernel);
		$stack = [$this->root];

		while ($current = array_pop($stack)) {
			[$left, $right] = $current->subsets();

			$current->cleanup();

			if ($left->numSamples() > $this->maxLeafSize) {
				$node = DualTreeBall::split($left, $this->kernel);

				[$subLeft, $subRight] = $node->subsets();

				if ($subLeft->empty() || $subRight->empty()) {
					$current->attachLeft(DualTreeClique::terminate($left, $this->kernel));
				} else {
					$current->attachLeft($node);
					$stack[] = $node;
				}
			} else {
				$current->attachLeft(DualTreeClique::terminate($left, $this->kernel));
			}

			if ($right->numSamples() > $this->maxLeafSize) {
				$node = DualTreeBall::split($right, $this->kernel);

				[$subLeft, $subRight] = $node->subsets();

				if ($node->isPoint() || $subLeft->empty() || $subRight->empty()) {
					$current->attachRight(DualTreeClique::terminate($right, $this->kernel));
				} else {
					$current->attachRight($node);
					$stack[] = $node;
				}
			} else {
				$current->attachRight(DualTreeClique::terminate($right, $this->kernel));
			}
		}
	}
}
