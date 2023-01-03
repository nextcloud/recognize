<?php
/*
 * Copyright (c) 2023 @MB-Finski
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize;

use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Graph\Trees\BallTree;
use Rubix\ML\Kernels\Distance\Distance;

class ChineseWhispersClusterer {
	private array $whisperArray;
	private Distance $kernel;


	public function __construct(Labeled $dataset, Distance $kernel, float $neighborRadius = 0.5, int $minNumOfNeighbors = 5) {
		$this->whisperArray = [];

		$distanceTree = new BallTree(10, $kernel);
		$distanceTree->grow($dataset);

		$samples = $dataset->samples();
		$labels = $dataset->labels();

		$currentClusterId = 0;
		foreach ($labels as $key => $label) {
			$sample = $samples[$key];

			[$_1,$neighbors,$_2] = $distanceTree->range($sample, $neighborRadius);

			if (count($neighbors) < $minNumOfNeighbors) {
				$this->whisperArray[$label] = ['clusterId' => null, 'neighbors' => []];
				continue;
			}

			$this->whisperArray[$label] = ['clusterId' => $currentClusterId, 'neighbors' => $neighbors];
			$currentClusterId++;
		}
	}

	public function iterate(int $iterations) : void {
		for ($i = 0; $i < $iterations; $i++) {
			foreach ($this->whisperArray as &$vertexData) {
				$neighborWhispers = [];

				foreach ((array) $vertexData['neighbors'] as $neighborLabel) {
					$neighborData = $this->whisperArray[$neighborLabel];

					$clusterIdWhisper = $neighborData['clusterId'];

					if (is_null($clusterIdWhisper)) {
						continue;
					}

					$neighborWhispers[] = $clusterIdWhisper;
				}

				if (empty($neighborWhispers)) {
					continue;
				}

				$neighborWhispers = array_count_values($neighborWhispers);

				$vertexData['clusterId'] = $this->arrayKeyMaxValue($neighborWhispers);
			}
			unset($vertexData);
		}
	}

	public function getClusterIds(): array {
		$vertexClusterIds = [];

		foreach (array_keys($this->whisperArray) as $label) {
			$vertexClusterIds[$label] = $this->whisperArray[$label]['clusterId'];
		}

		return $vertexClusterIds;
	}


	private function arrayKeyMaxValue($array) {
		$max = null;
		$result = null;
		foreach ($array as $key => $value) {
			if ($max === null || $value > $max) {
				$result = $key;
				$max = $value;
			}
		}

		return $result;
	}
}
