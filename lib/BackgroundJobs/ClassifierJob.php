<?php

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\Job;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

abstract class ClassifierJob extends Job {
	private LoggerInterface $logger;
	private QueueService $queue;

	public function __construct(ITimeFactory $time, LoggerInterface $logger, QueueService $queue) {
		parent::__construct($time);
		$this->logger = $logger;
		$this->queue = $queue;
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
		$this->classify($storageId, $rootId, $files);

		try {
			// If there is at least one file left in the queue, reschedule this job
			$files = $this->queue->getFromQueue($model, $storageId, $rootId, 1);
			if (count($files) > 0) {
				$this->queue->scheduleJob($model, $files[0]);
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
