<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\TaskProcessing\ImageFaceRecognitionClassifier as TaskProcessingFaceClassifier;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\IUserMountCache;

final class ClassifyFacesJob extends ClassifierJob {
	public const MODEL_NAME = 'faces';

	private SettingsService $settingsService;
	private ClusteringFaceClassifier $faces;
	private TaskProcessingFaceClassifier $tpFaces;

	public function __construct(ITimeFactory $time, Logger $logger, QueueService $queue, SettingsService $settingsService, ClusteringFaceClassifier $faceClassifier, TaskProcessingFaceClassifier $tpFaces, IUserMountCache $mountCache, IJobList $jobList) {
		parent::__construct($time, $logger, $queue, $mountCache, $jobList, $settingsService);
		$this->settingsService = $settingsService;
		$this->faces = $faceClassifier;
		$this->tpFaces = $tpFaces;
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
	 */
	protected function classify(array $files) : void {
		if ($this->settingsService->getSetting('taskprocessing.enabled') === 'true') {
			$this->tpFaces->classify($files);
			return;
		}
		$this->faces->classify($files);
	}

	/**
	 * @return int
	 */
	protected function getBatchSize() :int {
		return intval($this->settingsService->getSetting('faces.batchSize'));
	}
}
