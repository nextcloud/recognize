<?php

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\IUserMountCache;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ClassifyImagenetJob extends ClassifierJob {
	public const MODEL_NAME = 'imagenet';

	private IConfig $config;
	private ImagenetClassifier $imagenet;

	public function __construct(ITimeFactory $time, LoggerInterface $logger, QueueService $queue, IConfig $config, ImagenetClassifier $imagenet, IUserMountCache $mountCache, IJobList $jobList) {
		parent::__construct($time, $logger, $queue, $mountCache, $jobList, $config);
		$this->config = $config;
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
		return intval($this->config->getAppValue('recognize', 'imagenet.batchSize', '100'));
	}
}
