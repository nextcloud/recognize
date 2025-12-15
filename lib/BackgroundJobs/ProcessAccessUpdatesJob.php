<?php

/*
 * Copyright (c) 2021-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\AccessUpdateMapper;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\AccessUpdateService;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\StorageService;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

final class ProcessAccessUpdatesJob extends QueuedJob {

	public function __construct(
		ITimeFactory $timeFactory,
		private AccessUpdateService  $accessUpdateService,
		private IJobList $jobList,
		private AccessUpdateMapper $accessUpdateMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
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
		if ($count > 0) {
			// Schedule next iteration
			$this->jobList->add(self::class, [
				'storage_id' => $storageId,
			]);
		}
	}
}
