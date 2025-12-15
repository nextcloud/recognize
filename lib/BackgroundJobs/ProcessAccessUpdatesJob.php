<?php

/*
 * Copyright (c) 2021-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Db\AccessUpdateMapper;
use OCA\Recognize\Service\AccessUpdateService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

final class ProcessAccessUpdatesJob extends TimedJob {

	public function __construct(
		ITimeFactory $timeFactory,
		private AccessUpdateService  $accessUpdateService,
		private IJobList $jobList,
		private AccessUpdateMapper $accessUpdateMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(5 * 60);
		$this->setTimeSensitivity(self::TIME_SENSITIVE);
	}

	/**
	 * @param array{storage_id:int} $argument
	 * @return void
	 */
	protected function run($argument): void {
		$storageId = $argument['storage_id'];

		$this->accessUpdateService->processAccessUpdates($storageId);
		try {
			$count = $this->accessUpdateMapper->countByStorageId($storageId);
		} catch (Exception $e) {
			$this->logger->error('Failed to count access updates' . $e->getMessage(), ['exception' => $e]);
			$count = 1;
		}
		if ($count === 0) {
			// Schedule next iteration
			$this->jobList->remove(self::class, [
				'storage_id' => $storageId,
			]);
		}
	}
}
