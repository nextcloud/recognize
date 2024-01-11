<?php
/*
 * Copyright (c) 2023 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Clustering;

use \OCA\Recognize\Rubix\ML\Datasets\Labeled;
use \OCA\Recognize\Rubix\ML\EstimatorType;
use \OCA\Recognize\Rubix\ML\Helpers\Params;
use \OCA\Recognize\Rubix\ML\Kernels\Distance\Distance;

/**
 * HDBSCAN
 *
 * *Hierarchical Density-Based Spatial Clustering of Applications with Noise* is a clustering algorithm
 * able to find non-linearly separable, arbitrarily-shaped clusters from a space with varying amounts of noise.
 * The only mandatory parameters for the algorithm are a minimum cluster size and a smoothing
 * factor, sample size, that is used for estimating local probability density.
 * HDBSCAN also has the ability to mark outliers as *noise*
 * and thus can be used as a *quasi* anomaly detector.
 *
 * References:  March W.B., Ram P., Gray A.G.
 *              Fast Euclidean Minimum Spanning Tree: Algorithm, Analysis, and Applications
 *              Proc. ACM SIGKDD’10, 2010, 603-611, https://mlpack.org/papers/emst.pdf
 *
 *              Curtin, R., March, W., Ram, P., Anderson, D., Gray, A., & Isbell, C. (2013, May).
 *              Tree-independent dual-tree algorithms.
 *              In International Conference on Machine Learning (pp. 1435-1443). PMLR.
 *
 * @author      Sami Finnilä
 */
class HDBSCAN {
	/**
	 * The minimum number of samples that can be considered to form a cluster.
	 * Larger values will generate more stable clusters.
	 *
	 * @var int
	 */
	protected int $minClusterSize;

	/**
	 * The number of neighbors used for determining core distance when
	 * calculating mutual reachability distance between points.
	 *
	 * @var int
	 */
	protected int $sampleSize;

	/**
	 * The spatial tree used to run range searches.
	 *
	 */
	protected MstSolver $mstSolver;


	/**
	 * The distance kernel used for computing interpoint distances.
	 *
	 */
	protected \OCA\Recognize\Rubix\ML\Datasets\Labeled $dataset;



	/**
	 * @param Labeled $dataset
	 * @param int $minClusterSize
	 * @param int $sampleSize
	 * @param array $oldCoreDistances
	 * @param Distance $kernel
	 * @param bool $useTrueMst // (Build true or approximate minimum spanning tree)
	 * @throws \OCA\Recognize\Rubix\ML\Exceptions\InvalidArgumentException
	 */
	public function __construct(Labeled $dataset, int $minClusterSize = 5, int $sampleSize = 5, array $oldCoreDistances = [], ?Distance $kernel = null, bool $useTrueMst = true) {
		if ($minClusterSize < 2) {
			throw new \InvalidArgumentException('Minimum cluster size must be'
				. " greater than 1, $minClusterSize.");
		}

		if ($sampleSize < 2) {
			throw new \InvalidArgumentException('Minimum sample size must be'
				. " greater than 1, $sampleSize given.");
		}

		$kernel = $kernel ?? new SquaredDistance();
		$this->sampleSize = $sampleSize;
		$this->minClusterSize = $minClusterSize;
		$this->mstSolver = new MstSolver($dataset, 20, $sampleSize, $kernel, $oldCoreDistances, $useTrueMst);
	}

	public function getCoreNeighborDistances(): array {
		return $this->mstSolver->getCoreNeighborDistances();
	}

	/**
	 * Return the estimator type.
	 *
	 * @return \OCA\Recognize\Rubix\ML\EstimatorType
	 */
	public function type(): EstimatorType {
		return EstimatorType::clusterer();
	}

	/**
	 * Return the data types that the estimator is compatible with.
	 *
	 * @return list<\OCA\Recognize\Rubix\ML\DataType>
	 */
	public function compatibility(): array {
		return $this->mstSolver->kernel()->compatibility();
	}

	/**
	 * Return the settings of the hyper-parameters in an associative array.
	 *
	 * @return mixed[]
	 */
	public function params(): array {
		return [
			'min cluster size' => $this->minClusterSize,
			'sample size' => $this->sampleSize,
			'dual tree' => $this->mstSolver,
		];
	}

	/**
	 * Form clusters and make predictions from the dataset (hard clustering).
	 *
	 * @param float $minClusterSeparation
	 * @param float $maxEdgeLength
	 * @return list<MstClusterer>
	 */
	public function predict(float $minClusterSeparation = 0.0, float $maxEdgeLength = 0.5): array {
		// Boruvka algorithm for MST generation
		$edges = $this->mstSolver->getMst();

		// Boruvka complete, $edges now contains our mutual reachability distance MST
		if ($this->mstSolver->kernel() instanceof SquaredDistance) {
			foreach ($edges as &$edge) {
				$edge["distance"] = sqrt($edge["distance"]);
			}
		}
		unset($edge);

		$mstClusterer = new MstClusterer($edges, null, $this->minClusterSize, null, $minClusterSeparation, $maxEdgeLength);
		$flatClusters = $mstClusterer->processCluster();

		return $flatClusters;
	}

	/**
	 * Return the string representation of the object.
	 *
	 * @internal
	 *
	 * @return string
	 */
	public function __toString(): string {
		return 'HDBSCAN (' . Params::stringify($this->params()) . ')';
	}
}
