<?php

/*
 * Copyright (c) 2021-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Db\FsAccessUpdate;
use OCA\Recognize\Db\FsActionMapper;
use OCA\Recognize\Db\FsCreation;
use OCA\Recognize\Db\FsDeletion;
use OCA\Recognize\Db\FsMove;
use OCA\Recognize\Service\FsActionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

final class ProcessFsActionsJob extends TimedJob {

	public function __construct(
		ITimeFactory            $timeFactory,
		private FsActionService $accessUpdateService,
		private IJobList        $jobList,
		private FsActionMapper  $accessUpdateMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(5 * 60);
		$this->setTimeSensitivity(self::TIME_SENSITIVE);
	}

	/**
	 * @param array{storage_id:int, type: class-string<FsAccessUpdate|FsCreation|FsDeletion|FsMove>} $argument
	 * @return void
	 */
	protected function run($argument): void {
		$storageId = $argument['storage_id'] ?? null;
		$className = $argument['type'];

		if (isset($storageId)) {
			$this->accessUpdateService->processActionsByClassAndStorageId($className, $storageId);
			try {
				$remainingCount = $this->accessUpdateMapper->countByStorageId($className, $storageId);
			} catch (Exception $e) {
				$this->logger->error('Failed to count fs actions: ' . $e->getMessage(), ['exception' => $e]);
				$remainingCount = 1;
			}
		} else {
			$this->accessUpdateService->processActionsByClass($className);
			try {
				$remainingCount = $this->accessUpdateMapper->count($className);
			} catch (Exception $e) {
				$this->logger->error('Failed to count fs actions: ' . $e->getMessage(), ['exception' => $e]);
				$remainingCount = 1;
			}
		}


		if ($remainingCount === 0) {
			// Remove job from queue
			$this->jobList->remove(self::class, $argument);
		}
	}
}
