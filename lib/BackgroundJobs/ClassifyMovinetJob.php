<?php

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Config\IUserMountCache;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ClassifyMovinetJob extends ClassifierJob {
	public const MODEL_NAME = 'movinet';

	private IConfig $config;
	private MovinetClassifier $movinet;

	public function __construct(ITimeFactory $time, LoggerInterface $logger, QueueService $queue, IConfig $config, MovinetClassifier $movinet, IUserMountCache $mountCache, IJobList $jobList) {
		parent::__construct($time, $logger, $queue, $mountCache, $jobList, $config);
		$this->config = $config;
		$this->movinet = $movinet;
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument):void {
		$this->runClassifier(self::MODEL_NAME, $argument);
	}

	/**
	 * @param list<\OCA\Recognize\Db\QueueFile> $files
	 * @return void
	 * @throws \OCA\Recognize\Exception\Exception
	 */
	protected function classify(array $files) : void {
		$this->movinet->classify($files);
	}

	/**
	 * @return int
	 */
	protected function getBatchSize(): int {
		return intval($this->config->getAppValue('recognize', 'movinet.batchSize', '100'));
	}
}
