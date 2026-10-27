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
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

final class ClusterFacesJob extends QueuedJob {
	public const DEFAULT_BATCH_SIZE = 10_000;
	// Peak memory of FaceClusterAnalyzer::calculateClusters is roughly
	// QUADRATIC_BYTES * n² + LINEAR_BYTES * n for n detections. The quadratic part is
	// the interpoint distance cache in MrdBallTree (measured: ~3.4GB for n=10,000 on real data)
	public const QUADRATIC_BYTES = 34;
	public const LINEAR_BYTES = 8_000;
	// Batch size per byte of memory_limit that is known to work for low memory limits
	public const LINEAR_BATCH_SIZE_PER_BYTE = 5_000 / 1_200_000_000;
	// Share of the free memory we allow clustering to use
	public const MEMORY_HEADROOM = 0.7;
	// Seconds to wait before retrying when another ClusterFacesJob for the same user is running
	public const RETRY_DELAY = 10 * 60;

	private FaceClusterAnalyzer $clusterAnalyzer;
	private IJobList $jobList;
	private LoggerInterface $logger;
	private SettingsService $settingsService;
	private ILockingProvider $lockingProvider;

	public function __construct(ITimeFactory $time, Logger $logger, IJobList $jobList, FaceClusterAnalyzer $clusterAnalyzer, SettingsService $settingsService, ILockingProvider $lockingProvider) {
		parent::__construct($time);
		$this->logger = $logger;
		$this->jobList = $jobList;
		$this->clusterAnalyzer = $clusterAnalyzer;
		$this->settingsService = $settingsService;
		$this->lockingProvider = $lockingProvider;
	}

	/**
	 * @param array{storageId: int, rootId: int, userId: string} $argument
	 */
	protected function run($argument): void {
		$userId = (string)$argument['userId'];

		// QueuedJob removes itself from the job list before running, so new face detections
		// can schedule another ClusterFacesJob for this user while this one is still running.
		// Make sure only one runs per user at a time; the lock is released on shutdown even after a fatal error.
		$lockKey = 'recognize/cluster-faces/' . $userId;
		try {
			$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		} catch (LockedException $e) {
			$this->logger->debug('ClusterFacesJob for user ' . $userId . ' is already running, rescheduling');
			// Re-add the job so that detections added after the running job has fetched its batch are not left behind,
			// but delay it to avoid cron workers picking it up again and again while the other job is still running
			$this->jobList->scheduleAfter(self::class, $this->time->getTime() + self::RETRY_DELAY, $argument);
			return;
		}

		try {
			$this->clusterAnalyzer->calculateClusters($userId, self::getBatchSize());
		} catch (\Throwable $e) {
			$this->settingsService->setSetting('clusterFaces.status', 'false');
			$this->logger->error('Failed to calculate face clusters', ['exception' => $e]);
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	/**
	 * Determine how many face detections fit into the memory available to this process
	 */
	public static function getBatchSize(): int {
		$iniValue = ini_get('memory_limit');
		if ($iniValue === false || $iniValue === '') {
			return self::DEFAULT_BATCH_SIZE;
		}
		$memoryBytes = ini_parse_quantity($iniValue);
		if ($memoryBytes <= 0) {
			return self::DEFAULT_BATCH_SIZE;
		}
		$budget = max(0, $memoryBytes - memory_get_usage(true)) * self::MEMORY_HEADROOM;
		// Solve QUADRATIC_BYTES * n² + LINEAR_BYTES * n = budget for n
		$a = self::QUADRATIC_BYTES;
		$b = self::LINEAR_BYTES;
		$quadraticBatchSize = (int)((-$b + sqrt($b * $b + 4 * $a * $budget)) / (2 * $a));
		$linearBatchSize = (int)($memoryBytes * self::LINEAR_BATCH_SIZE_PER_BYTE);
		return max(FaceClusterAnalyzer::MIN_DATASET_SIZE, min($linearBatchSize, $quadraticBatchSize));
	}
}
