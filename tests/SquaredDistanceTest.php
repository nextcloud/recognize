<?php

/**
 * @copyright Copyright (c) 2026 The Recognize contributors.
 * @license AGPL-3.0-or-later
 *
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING file.
 */
declare(strict_types=1);

use OCA\Recognize\Clustering\SquaredDistance;
use PHPUnit\Framework\TestCase;

class SquaredDistanceTest extends TestCase {
	private SquaredDistance $kernel;

	public function setUp(): void {
		$this->kernel = new SquaredDistance();
	}

	public function testComputesSquaredEuclideanDistance(): void {
		// sqrt-free euclidean: (1-4)^2 + (2-5)^2 = 18
		self::assertSame(18.0, $this->kernel->compute([1, 2], [4, 5]));
	}

	public function testZeroDistanceToItself(): void {
		self::assertSame(0.0, $this->kernel->compute([0.5, 1.5, -2.5], [0.5, 1.5, -2.5]));
	}

	public function testRaggedSecondVectorReturnsNoWarning(): void {
		// #1611: $b shorter than $a used to emit "Undefined array key"
		// warnings. The distance now covers only the shared dimensions
		// (here: (0-3)^2 = 9).
		self::assertSame(9.0, $this->kernel->compute([0, 3, 7], [3]));
	}

	public function testRaggedFirstVectorIsSymmetric(): void {
		// whichever side is shorter, the result must be the same
		self::assertSame(
			$this->kernel->compute([0, 3, 7], [3]),
			$this->kernel->compute([3], [0, 3, 7])
		);
	}

	public function testEmptyVectorsReturnZero(): void {
		self::assertSame(0.0, $this->kernel->compute([], []));
	}
}
