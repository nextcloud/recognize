<?php

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\IUserMountCache;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ClassifyMusicnnJob extends ClassifierJob {
	public const MODEL_NAME = 'musicnn';

	private IConfig $config;
	private MusicnnClassifier $musicnn;

	public function __construct(ITimeFactory $time, LoggerInterface $logger, QueueService $queue, IConfig $config, MusicnnClassifier $musicnn, IUserMountCache $mountCache, IJobList $jobList) {
		parent::__construct($time, $logger, $queue, $mountCache, $jobList, $config);
		$this->config = $config;
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
		return intval($this->config->getAppValue('recognize', 'musicnn.batchSize', '100'));
	}
}
