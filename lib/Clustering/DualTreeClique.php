<?php
/*
 * Copyright (c) 2023 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Clustering;

use \OCA\Recognize\Vendor\Rubix\ML\Datasets\Labeled;
use \OCA\Recognize\Vendor\Rubix\ML\Graph\Nodes\Clique;
use \OCA\Recognize\Vendor\Rubix\ML\Helpers\Stats;
use \OCA\Recognize\Vendor\Rubix\ML\Kernels\Distance\Distance;
use function \OCA\Recognize\Vendor\Rubix\ML\argmax;

class DualTreeClique extends Clique {
	protected float $longestDistanceInNode = INF;
	protected bool $fullyConnected = false;
	/**
	 * @var null|int|string
	 */
	protected $setId;

	public function setLongestDistance(float $longestDistance): void {
		$this->longestDistanceInNode = $longestDistance;
	}

	public function getLongestDistance(): float {
		return $this->longestDistanceInNode;
	}

	public function resetLongestEdge(): void {
		$this->longestDistanceInNode = INF;
	}

	public function resetFullyConnectedStatus(): void {
		$this->fullyConnected = false;
	}

	/**
	 * @return int|string|null
	 */
	public function getSetId() {
		if (!$this->fullyConnected) {
			return null;
		}

		return $this->setId;
	}

	public function isFullyConnected(): bool {
		return $this->fullyConnected;
	}

	/**
	 * @param array<int|string,int|string> $labelToSetId
	 * @return int|mixed|string|null
	 */
	public function propagateSetChanges(array &$labelToSetId) {
		if ($this->fullyConnected) {
			$this->setId = $labelToSetId[$this->dataset->label(0)];
			return $this->setId;
		}

		$labels = $this->dataset->labels();

		$setId = $labelToSetId[array_pop($labels)];

		foreach ($labels as $label) {
			if ($setId !== $labelToSetId[$label]) {
				return null;
			}
		}

		$this->fullyConnected = true;
		$this->setId = $setId;

		return $this->setId;
	}

	/**
	 * Terminate a branch with a dataset.
	 *
	 * @param \OCA\Recognize\Vendor\Rubix\ML\Datasets\Labeled $dataset
	 * @param \OCA\Recognize\Vendor\Rubix\ML\Kernels\Distance\Distance $kernel
	 * @return self
	 */
	public static function terminate(Labeled $dataset, Distance $kernel): self {
		$center = [];

		foreach ($dataset->features() as $column => $values) {
			if ($dataset->featureType($column)->isContinuous()) {
				$center[] = Stats::mean($values);
			} else {
				$center[] = argmax(array_count_values($values));
			}
		}

		$distances = [];

		foreach ($dataset->samples() as $sample) {
			$distances[] = $kernel->compute($sample, $center);
		}
		$radius = max($distances) ?: 0.0;
		return new self($dataset, $center, $radius);
	}
}
