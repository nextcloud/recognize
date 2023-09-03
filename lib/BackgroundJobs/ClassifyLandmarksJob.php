<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\IUserMountCache;

class ClassifyLandmarksJob extends ClassifierJob {
	public const MODEL_NAME = 'landmarks';

	private SettingsService $settingsService;
	private LandmarksClassifier $landmarks;

	public function __construct(ITimeFactory $time, Logger $logger, QueueService $queue, SettingsService $settingsService, LandmarksClassifier $landmarks, IUserMountCache $mountCache, IJobList $jobList) {
		parent::__construct($time, $logger, $queue, $mountCache, $jobList, $settingsService);
		$this->settingsService = $settingsService;
		$this->landmarks = $landmarks;
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
		$this->landmarks->classify($files);
	}

	/**
	 * @return int
	 */
	protected function getBatchSize() :int {
		return intval($this->settingsService->getSetting('landmarks.batchSize'));
	}
}
