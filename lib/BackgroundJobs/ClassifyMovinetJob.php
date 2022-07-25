<?php

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ClassifyMovinetJob extends ClassifierJob {
	public const MODEL_NAME = 'movinet';
	public const BATCH_SIZE = 30; // 30 files
	public const BATCH_SIZE_PUREJS = 10; // 10 files

	private IConfig $config;
	private MovinetClassifier $movinet;

	public function __construct(ITimeFactory $time, LoggerInterface $logger, QueueService $queue, IConfig $config, MovinetClassifier $movinet) {
		parent::__construct($time, $logger, $queue);
		$this->config = $config;
		$this->movinet = $movinet;
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument) {
		$this->runClassifier(self::MODEL_NAME, $argument);
	}

	/**
	 * @param string|null $userId
	 * @param array $files
	 * @return void
	 * @throws \OCA\Recognize\Exception\Exception
	 */
	protected function classify(array $files) : void {
		$this->movinet->classify($files);
	}

	/**
	 * @return int
	 */
	protected function getBatchSize() :int {
		return $this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'false' ? self::BATCH_SIZE : self::BATCH_SIZE_PUREJS;
	}
}
