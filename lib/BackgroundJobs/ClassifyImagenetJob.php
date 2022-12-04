<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\IUserMountCache;

class ClassifyImagenetJob extends ClassifierJob {
	public const MODEL_NAME = 'imagenet';

	private SettingsService $settingsService;
	private ImagenetClassifier $imagenet;

	public function __construct(ITimeFactory $time, Logger $logger, QueueService $queue, SettingsService $settingsService, ImagenetClassifier $imagenet, IUserMountCache $mountCache, IJobList $jobList) {
		parent::__construct($time, $logger, $queue, $mountCache, $jobList, $settingsService);
		$this->settingsService = $settingsService;
		$this->imagenet = $imagenet;
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument): void {
		$this->runClassifier(self::MODEL_NAME, $argument);
	}

	/**
	 * @param list<\OCA\Recognize\Db\QueueFile> $files
	 * @return void
	 */
	protected function classify(array $files) : void {
		$this->imagenet->classify($files);
	}

	/**
	 * @return int
	 */
	protected function getBatchSize(): int {
		return intval($this->settingsService->getSetting('imagenet.batchSize'));
	}
}
