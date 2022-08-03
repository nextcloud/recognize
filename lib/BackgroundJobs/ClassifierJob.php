<?php

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\DB\Exception;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use Psr\Log\LoggerInterface;

abstract class ClassifierJob extends Job {
	private LoggerInterface $logger;
	private QueueService $queue;
	private IUserMountCache $userMountCache;
	private IJobList $jobList;

	public function __construct(ITimeFactory $time, LoggerInterface $logger, QueueService $queue, IUserMountCache $userMountCache, IJobList $jobList) {
		parent::__construct($time);
		$this->logger = $logger;
		$this->queue = $queue;
		$this->userMountCache = $userMountCache;
		$this->jobList = $jobList;
	}

	protected function runClassifier(string $model, $argument) {
		$storageId = $argument['storageId'];
		$rootId = $argument['rootId'];
		$this->logger->debug('Classifying files of storage '.$storageId. ' using '.$model);
		try {
			$files = $this->queue->getFromQueue($model, $storageId, $rootId, $this->getBatchSize());
		} catch (Exception $e) {
			$this->logger->error('Cannot retrieve items from imagenet queue', ['exception' => $e]);
			return;
		}

		// Setup Filesystem for a users that can access this mount
		$mounts = array_filter($this->userMountCache->getMountsForStorageId($storageId), function (ICachedMountInfo $mount) use ($rootId) {
			return $mount->getRootId() === $rootId;
		});
		if (count($mounts) > 0) {
			\OC_Util::setupFS($mounts[0]->getUser()->getUID());
		}

		$this->classify($files);

		try {
			// If there is at least one file left in the queue, reschedule this job
			$files = $this->queue->getFromQueue($model, $storageId, $rootId, 1);
			if (count($files) === 0) {
				// `static` to get extending subclasse name
				$this->jobList->remove(static::class, $argument);
			}
		} catch (Exception $e) {
			$this->logger->error('Cannot retrieve items from imagenet queue', ['exception' => $e]);
			return;
		}
	}

	/**
	 * @return int
	 */
	abstract protected function getBatchSize() : int;

	/**
	 * @param array $files
	 * @return void
	 */
	abstract protected function classify(array $files) : void;
}
