<?php
/*
 * Copyright (c) 2023 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Clustering;

use Rubix\ML\DataType;
use Rubix\ML\Kernels\Distance\Distance;

/**
 * Squared distance
 *
 * Euclidean distance without square root.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Sami Finnilä
 */
class SquaredDistance implements Distance {
	/**
	 * Return the data types that this kernel is compatible with.
	 *
	 * @internal
	 *
	 * @return list<\Rubix\ML\DataType>
	 */
	public function compatibility(): array {
		return [
			DataType::continuous(),
		];
	}

	/**
	 * Compute the distance between two vectors.
	 *
	 * @internal
	 *
	 * @param list<int|float> $a
	 * @param list<int|float> $b
	 * @return float
	 */
	public function compute(array $a, array $b): float {
		$distance = 0.0;

		foreach ($a as $i => $value) {
			$distance += ($value - $b[$i]) ** 2;
		}

		return $distance;
	}

	/**
	 * Return the string representation of the object.
	 *
	 * @internal
	 *
	 * @return string
	 */
	public function __toString(): string {
		return 'Squared distance';
	}
}
