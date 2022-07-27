<?php

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\FaceClusterAnalyzer;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

class ClusterFacesJob extends QueuedJob {
	private FaceClusterAnalyzer $clusterAnalyzer;

	public function __construct(ITimeFactory $time, LoggerInterface $logger, IJobList $jobList, FaceClusterAnalyzer $clusterAnalyzer) {
		parent::__construct($time);
		$this->logger = $logger;
		$this->jobList = $jobList;
		$this->clusterAnalyzer = $clusterAnalyzer;
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument) {
		$userId = $argument['userId'];
		try {
			$this->clusterAnalyzer->calculateClusters($userId);
		} catch (\JsonException|Exception $e) {
			$this->logger->error('Failed to calculate face clusters', ['exception' => $e]);
		}
		$this->jobList->remove(self::class, $argument);
	}
}
