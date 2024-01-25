<?php
/*
 * Copyright (c) 2021-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Classifiers\Video;

use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Exception\Exception;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\IRootFolder;
use OCP\IPreview;
use OCP\ITempManager;

class MovinetClassifier extends Classifier {
	public const VIDEO_TIMEOUT = 480; // seconds
	public const MODEL_NAME = 'movinet';

	private TagManager $tagManager;

	public function __construct(Logger $logger, IAppConfig $config, TagManager $tagManager, QueueService $queue, IRootFolder $rootFolder, ITempManager $tempManager, IPreview $previewProvider) {
		parent::__construct($logger, $config, $rootFolder, $queue, $tempManager, $previewProvider);
		$this->tagManager = $tagManager;
	}

	/**
	 * @param \OCA\Recognize\Db\QueueFile[] $queueFiles
	 * @return void
	 * @throws \ErrorException|\RuntimeException|Exception
	 */
	public function classify(array $queueFiles): void {
		if ($this->config->getAppValue('tensorflow.purejs', 'false') === 'true') {
			throw new Exception('Movinet does not support WASM mode');
		} else {
			$timeout = self::VIDEO_TIMEOUT;
		}
		$classifierProcess = $this->classifyFiles(self::MODEL_NAME, $queueFiles, $timeout);

		/** @var \OCA\Recognize\Db\QueueFile $queueFile */
		/** @var list<string> $results */
		foreach ($classifierProcess as $queueFile => $results) {
			$this->tagManager->assignTags($queueFile->getFileId(), $results);
			$this->config->setAppValue(self::MODEL_NAME.'.status', 'true');
			$this->config->setAppValue(self::MODEL_NAME.'.lastFile', time());
		}
	}
}
