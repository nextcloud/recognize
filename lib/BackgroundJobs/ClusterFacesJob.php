<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\FaceClusterAnalyzer;
use OCA\Recognize\Service\Logger;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

class ClusterFacesJob extends QueuedJob {
	private FaceClusterAnalyzer $clusterAnalyzer;
	private IJobList $jobList;
	private LoggerInterface $logger;

	public function __construct(ITimeFactory $time, Logger $logger, IJobList $jobList, FaceClusterAnalyzer $clusterAnalyzer) {
		parent::__construct($time);
		$this->logger = $logger;
		$this->jobList = $jobList;
		$this->clusterAnalyzer = $clusterAnalyzer;
	}

	/**
	 * @inheritDoc
	 *
	 * @return void
	 */
	protected function run($argument) {
		/** @var string $userId */
		$userId = $argument['userId'];
		try {
			$this->clusterAnalyzer->calculateClusters($userId);
		} catch (\JsonException|Exception $e) {
			$this->logger->error('Failed to calculate face clusters', ['exception' => $e]);
		}
		$this->jobList->remove(self::class, $argument);
	}
}
