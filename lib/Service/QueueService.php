<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\Recognize\BackgroundJobs\ClassifyFacesJob;
use OCA\Recognize\BackgroundJobs\ClassifyImagenetJob;
use OCA\Recognize\BackgroundJobs\ClassifyLandmarksJob;
use OCA\Recognize\BackgroundJobs\ClassifyMovinetJob;
use OCA\Recognize\BackgroundJobs\ClassifyMusicnnJob;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Db\QueueMapper;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;

class QueueService {
	/**
	 * @const array<string, string> JOB_CLASSES
	 */
	public const JOB_CLASSES = [
		ImagenetClassifier::MODEL_NAME => ClassifyImagenetJob::class,
		ClusteringFaceClassifier::MODEL_NAME => ClassifyFacesJob::class,
		LandmarksClassifier::MODEL_NAME => ClassifyLandmarksJob::class,
		MovinetClassifier::MODEL_NAME => ClassifyMovinetJob::class,
		MusicnnClassifier::MODEL_NAME => ClassifyMusicnnJob::class,
	];

	private QueueMapper $queueMapper;
	private IJobList $jobList;
	private IConfig $config;

	public function __construct(QueueMapper $queueMapper, IJobList $jobList, IConfig $config) {
		$this->queueMapper = $queueMapper;
		$this->jobList = $jobList;
		$this->config = $config;
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function insertIntoQueue(string $model, QueueFile $file) : void {
		// Only add to queue if this model is actually enabled
		if ($this->config->getAppValue('recognize', $model.'.enabled', 'false') !== 'true') {
			return;
		}

		// Only add to queue if it's not in there already
		if ($this->queueMapper->existsQueueItem($model, $file)) {
			return;
		}

		$this->queueMapper->insertIntoQueue($model, $file);
		$this->scheduleJob($model, $file);
	}

	/**
	 * @param string $model
	 * @param \OCA\Recognize\Db\QueueFile $file
	 * @param string|null $userId
	 * @return void
	 */
	public function scheduleJob(string $model, QueueFile $file) : void {
		if (!$this->jobList->has(self::JOB_CLASSES[$model], [
			'storageId' => $file->getStorageId(),
			'rootId' => $file->getRootId(),
		])) {
			$this->jobList->add(self::JOB_CLASSES[$model], [
				'storageId' => $file->getStorageId(),
				'rootId' => $file->getRootId(),
			]);
		}
	}

	/**
	 * @param string $model
	 * @param int $storageId
	 * @param int $rootId
	 * @param int $batchSize
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getFromQueue(string $model, int $storageId, int $rootId, int $batchSize) : array {
		return $this->queueMapper->getFromQueue($model, $storageId, $rootId, $batchSize);
	}

	/**
	 * @param string $model
	 * @param \OCA\Recognize\Db\QueueFile $queueFile
	 * @return void
	 * @throws \OCP\DB\Exception
	 */
	public function removeFromQueue(string $model, QueueFile $queueFile) : void {
		$this->queueMapper->removeFromQueue($model, $queueFile);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function removeFileFromAllQueues(int $fileId) : void {
		$this->queueMapper->removeFileFromAllQueues($fileId);
	}

	public function clearQueue(string $model): void {
		$this->queueMapper->clearQueue($model);
	}

	public function count(string $model): int {
		return $this->queueMapper->count($model);
	}
}
