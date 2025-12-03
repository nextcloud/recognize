<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\FaceClusterAnalyzer;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

final class ClusterFacesJob extends QueuedJob {
	private FaceClusterAnalyzer $clusterAnalyzer;
	private IJobList $jobList;
	private LoggerInterface $logger;
	public const BATCH_SIZE = 10000;
	private SettingsService $settingsService;

	public function __construct(ITimeFactory $time, Logger $logger, IJobList $jobList, FaceClusterAnalyzer $clusterAnalyzer, SettingsService $settingsService) {
		parent::__construct($time);
		$this->logger = $logger;
		$this->jobList = $jobList;
		$this->clusterAnalyzer = $clusterAnalyzer;
		$this->settingsService = $settingsService;
	}

	/**
	 * @inheritDoc
	 *
	 * @return void
	 */
	protected function run($argument) {
		$userId = (string) $argument['userId'];
		try {
			$this->clusterAnalyzer->calculateClusters($userId, self::BATCH_SIZE);
		} catch (\Throwable $e) {
			$this->settingsService->setSetting('clusterFaces.status', 'false');
			$this->logger->error('Failed to calculate face clusters', ['exception' => $e]);
		}
	}
}
