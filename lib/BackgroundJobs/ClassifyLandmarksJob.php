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

	private IConfig $config;
	private LandmarksClassifier $landmarks;

	public function __construct(ITimeFactory $time, LoggerInterface $logger, QueueService $queue, IConfig $config, LandmarksClassifier $landmarks, IUserMountCache $mountCache, IJobList $jobList) {
		parent::__construct($time, $logger, $queue, $mountCache, $jobList, $config);
		$this->config = $config;
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
		return intval($this->config->getAppValue('recognize', 'landmarks.batchSize', '100'));
	}
}
