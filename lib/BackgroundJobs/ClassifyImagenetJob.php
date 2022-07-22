<?php

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ClassifyImagenetJob extends ClassifierJob {
	public const MODEL_NAME = 'imagenet';
	public const BATCH_SIZE = 100; // 100 images
	public const BATCH_SIZE_PUREJS = 25; // 25 images

	private IConfig $config;
	private ImagenetClassifier $imagenet;

	public function __construct(ITimeFactory $time, LoggerInterface $logger, QueueService $queue, IConfig $config, ImagenetClassifier $imagenet)
	{
		parent::__construct($time, $logger, $queue);
		$this->config = $config;
		$this->imagenet = $imagenet;
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument) {
		$this->runClassifier(self::MODEL_NAME, $argument);
	}

	protected function classify(array $files) : void {
		$this->imagenet->classify($files);
	}

	/**
	 * @return int
	 */
	protected function getBatchSize() :int {
		return $this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'false' ? self::BATCH_SIZE : self:: BATCH_SIZE_PUREJS;
	}
}
