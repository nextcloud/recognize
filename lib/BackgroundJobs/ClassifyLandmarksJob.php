<?php

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\IUserMountCache;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ClassifyLandmarksJob extends ClassifierJob {
	public const MODEL_NAME = 'landmarks';
	public const BATCH_SIZE = 100; // 100 images
	public const BATCH_SIZE_PUREJS = 25; // 25 images

	private IConfig $config;
	private LandmarksClassifier $landmarks;

	public function __construct(ITimeFactory $time, LoggerInterface $logger, QueueService $queue, IConfig $config, LandmarksClassifier $landmarks, IUserMountCache $mountCache, IJobList $jobList) {
		parent::__construct($time, $logger, $queue, $mountCache, $jobList);
		$this->config = $config;
		$this->landmarks = $landmarks;
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument) {
		$this->runClassifier(self::MODEL_NAME, $argument);
	}

	protected function classify(array $files) : void {
		$this->landmarks->classify($files);
	}

	/**
	 * @return int
	 */
	protected function getBatchSize() :int {
		return $this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'false' ? self::BATCH_SIZE : self::BATCH_SIZE_PUREJS;
	}
}
