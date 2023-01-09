<?php
/*
 * Copyright (c) 2023 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Clustering;

use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Graph\Trees\BallTree;
use Rubix\ML\Kernels\Distance\Distance;

class MRDistance {
	private Distance $kernel;

	/**
	 * @var list<float|int|string> $coreDistances
	 */
	private array $coreDistances;
	private int $coreDistSampleSize;
	private Labeled $dataset;
	private BallTree $distanceTree;

	public function __construct(int $coreDistSampleSize, Labeled $dataset, Distance $kernel) {
		$this->coreDistSampleSize = $coreDistSampleSize;
		$this->kernel = $kernel;
		$this->coreDistances = [];
		$this->dataset = $dataset;

		$this->distanceTree = new BallTree($coreDistSampleSize * 3, $kernel);
		$this->distanceTree->grow($dataset);

		$this->kernel = $kernel;
	}

	/**
	 * @param int $a
	 * @param list<float|int|string> $aVector
	 * @param int $b
	 * @param list<float|int|string> $bVector
	 * @return float
	 */
	public function distance(int $a, array $aVector, int $b, array $bVector): float {
		$distance = $this->kernel->compute($aVector, $bVector);

		return max($distance, $this->getCoreDistance($a), $this->getCoreDistance($b));
	}

	private function getCoreDistance(int $index): float {
		if (!isset($this->coreDistances[$index])) {
			[$_1, $_2, $distances] = $this->distanceTree->nearest($this->dataset->sample($index), $this->coreDistSampleSize);
			$this->coreDistances[$index] = end($distances);
		}

		return $this->coreDistances[$index];
	}
}
