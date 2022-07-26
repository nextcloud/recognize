<?php

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IUserMountCache;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ClassifyMusicnnJob extends ClassifierJob {
	public const MODEL_NAME = 'imagenet';
	public const BATCH_SIZE = 100; // 100 files
	public const BATCH_SIZE_PUREJS = 25; // 10 files

	private IConfig $config;
	private MusicnnClassifier $musicnn;

	public function __construct(ITimeFactory $time, LoggerInterface $logger, QueueService $queue, IConfig $config, MusicnnClassifier $musicnn, IUserMountCache $mountCache) {
		parent::__construct($time, $logger, $queue, $mountCache);
		$this->config = $config;
		$this->musicnn = $musicnn;
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument) {
		$this->runClassifier(self::MODEL_NAME, $argument);
	}

	protected function classify(array $files) : void {
		$this->musicnn->classify($files);
	}

	/**
	 * @return int
	 */
	protected function getBatchSize() :int {
		return $this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'false' ? self::BATCH_SIZE : self::BATCH_SIZE_PUREJS;
	}
}
