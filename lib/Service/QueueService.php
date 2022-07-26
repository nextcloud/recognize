<?php

namespace OCA\Recognize\Service;

use OCA\Recognize\BackgroundJobs\ClassifyFacesJob;
use OCA\Recognize\BackgroundJobs\ClassifyImagenetJob;
use OCA\Recognize\BackgroundJobs\ClassifyLandmarksJob;
use OCA\Recognize\BackgroundJobs\ClassifyMovinetJob;
use OCA\Recognize\BackgroundJobs\ClassifyMusicnnJob;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Db\QueueMapper;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;

class QueueService {
	/**
	 * @const array<string, string> JOB_CLASSES
	 */
	public const JOB_CLASSES = [
		'imagenet' => ClassifyImagenetJob::class,
		'faces' => ClassifyFacesJob::class,
		'landmarks' => ClassifyLandmarksJob::class,
		'movinet' => ClassifyMovinetJob::class,
		'musicnn' => ClassifyMusicnnJob::class,
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
		$this->jobList->add(self::JOB_CLASSES[$model], [
			'storageId' => $file->getStorageId(),
			'rootId' => $file->getRootId(),
		]);
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
}
