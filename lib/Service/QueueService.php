<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Service;

use OCA\Recognize\AuditLog\AuditEventFactory;
use OCA\Recognize\AuditLog\Operation;
use OCA\Recognize\BackgroundJobs\ClassifyFacesJob;
use OCA\Recognize\BackgroundJobs\ClassifyImagenetJob;
use OCA\Recognize\BackgroundJobs\ClassifyLandmarksJob;
use OCA\Recognize\BackgroundJobs\ClassifyMovinetJob;
use OCA\Recognize\BackgroundJobs\ClassifyMusicnnJob;
use OCA\Recognize\BackgroundJobs\ClusterFacesJob;
use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\BackgroundJobs\StorageCrawlJob;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Db\QueueMapper;
use OCP\AppFramework\Services\IAppConfig;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;

final class QueueService {
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
	private IAppConfig $config;
	private IEventDispatcher $eventDispatcher;

	public function __construct(QueueMapper $queueMapper, IJobList $jobList, IAppConfig $config, IEventDispatcher $eventDispatcher) {
		$this->queueMapper = $queueMapper;
		$this->jobList = $jobList;
		$this->config = $config;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function insertIntoQueue(string $model, QueueFile $file) : void {
		// Only add to queue if this model is actually enabled
		if ($this->config->getAppValueString($model.'.enabled', 'false') !== 'true') {
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
	 * @return list<QueueFile>
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
	 * Remove a batch of queue files in as few statements as possible.
	 *
	 * @param string $model
	 * @param list<\OCA\Recognize\Db\QueueFile> $queueFiles
	 * @return void
	 * @throws \OCP\DB\Exception
	 */
	public function removeFilesFromQueue(string $model, array $queueFiles) : void {
		$ids = array_map(
			static fn (QueueFile $qf): int => $qf->getId(),
			$queueFiles,
		);
		$this->queueMapper->removeFromQueueByIds($model, $ids);
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

	/**
	 * Empties all queues and removes all scheduled Recognize background jobs
	 *
	 * @throws \OCP\DB\Exception
	 */
	public function clearAllJobs(): void {
		$removedFiles = $this->removeAllJobs();
		$this->eventDispatcher->dispatchTyped(AuditEventFactory::create(
			Operation::QueuesCleared,
			'Cleared all Recognize queues and background jobs, removing %s queued files',
			['removedFiles' => $removedFiles],
		));
	}

	/**
	 * Empties all queues, removes all scheduled Recognize background jobs and schedules a new crawl of all files
	 *
	 * @throws \OCP\DB\Exception
	 */
	public function scheduleRecrawl(): void {
		$removedFiles = $this->removeAllJobs();
		$this->jobList->add(SchedulerJob::class);
		$this->eventDispatcher->dispatchTyped(AuditEventFactory::create(
			Operation::RecrawlScheduled,
			'Scheduled a Recognize recrawl of all files, removing %s queued files',
			['removedFiles' => $removedFiles],
		));
	}

	/**
	 * @return int the number of removed queue entries
	 * @throws \OCP\DB\Exception
	 */
	private function removeAllJobs(): int {
		$removedFiles = 0;
		foreach (array_keys(self::JOB_CLASSES) as $model) {
			$removedFiles += $this->queueMapper->clearQueue($model);
		}
		foreach (self::JOB_CLASSES as $jobClass) {
			$this->jobList->remove($jobClass);
		}
		$this->jobList->remove(ClusterFacesJob::class);
		$this->jobList->remove(SchedulerJob::class);
		$this->jobList->remove(StorageCrawlJob::class);
		return $removedFiles;
	}

	public function count(string $model): int {
		return $this->queueMapper->count($model);
	}
}
