<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\TaskProcessing\VideoClassifier as TaskProcessingVideoClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\IUserMountCache;

final class ClassifyMovinetJob extends ClassifierJob {
	public const MODEL_NAME = 'movinet';

	private SettingsService $settingsService;
	private MovinetClassifier $movinet;
	private TaskProcessingVideoClassifier $tpVideo;

	public function __construct(ITimeFactory $time, Logger $logger, QueueService $queue, SettingsService $settingsService, MovinetClassifier $movinet, TaskProcessingVideoClassifier $tpVideo, IUserMountCache $mountCache, IJobList $jobList) {
		parent::__construct($time, $logger, $queue, $mountCache, $jobList, $settingsService);
		$this->settingsService = $settingsService;
		$this->movinet = $movinet;
		$this->tpVideo = $tpVideo;
	}

	/**
	 * @param array{storageId: int, rootId: int} $argument
	 */
	protected function run($argument): void {
		$this->runClassifier(self::MODEL_NAME, $argument);
	}

	/**
	 * @param list<\OCA\Recognize\Db\QueueFile> $files
	 * @return void
	 * @throws \OCA\Recognize\Exception\Exception
	 */
	protected function classify(array $files) : void {
		if ($this->settingsService->getSetting('taskprocessing.enabled') === 'true') {
			$this->tpVideo->classify($files);
			return;
		}
		$this->movinet->classify($files);
	}

	/**
	 * @return int
	 */
	protected function getBatchSize(): int {
		return intval($this->settingsService->getSetting('movinet.batchSize'));
	}
}
