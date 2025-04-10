<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\IUserMountCache;

final class ClassifyMusicnnJob extends ClassifierJob {
	public const MODEL_NAME = 'musicnn';

	private SettingsService $settingsService;
	private MusicnnClassifier $musicnn;

	public function __construct(ITimeFactory $time, Logger $logger, QueueService $queue, SettingsService $settingsService, MusicnnClassifier $musicnn, IUserMountCache $mountCache, IJobList $jobList) {
		parent::__construct($time, $logger, $queue, $mountCache, $jobList, $settingsService);
		$this->settingsService = $settingsService;
		$this->musicnn = $musicnn;
	}

	/**
	 * @inheritDoc
	 *
	 * @return void
	 */
	protected function run($argument) {
		$this->runClassifier(self::MODEL_NAME, $argument);
	}

	/**
	 * @param list<\OCA\Recognize\Db\QueueFile> $files
	 * @return void
	 */
	protected function classify(array $files) : void {
		$this->musicnn->classify($files);
	}

	/**
	 * @return int
	 */
	protected function getBatchSize() :int {
		return intval($this->settingsService->getSetting('musicnn.batchSize'));
	}
}
