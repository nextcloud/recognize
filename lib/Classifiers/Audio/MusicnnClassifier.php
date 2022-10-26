<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Classifiers\Audio;

use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\TagManager;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\ITempManager;

class MusicnnClassifier extends Classifier {
	public const AUDIO_TIMEOUT = 40; // seconds
	public const AUDIO_PUREJS_TIMEOUT = 300; // seconds
	public const MODEL_NAME = 'musicnn';

	private TagManager $tagManager;

	public function __construct(Logger $logger, IConfig $config, TagManager $tagManager, QueueService $queue, IRootFolder $rootFolder, ITempManager $tempManager) {
		parent::__construct($logger, $config, $rootFolder, $queue, $tempManager);
		$this->tagManager = $tagManager;
	}

	/**
	 * @param \OCA\Recognize\Db\QueueFile[] $queueFiles
	 * @return void
	 */
	public function classify(array $queueFiles): void {
		if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true') {
			$timeout = self::AUDIO_PUREJS_TIMEOUT;
		} else {
			$timeout = self::AUDIO_TIMEOUT;
		}
		$classifierProcess = $this->classifyFiles(self::MODEL_NAME, $queueFiles, $timeout);

		/**
		 * @var \OCA\Recognize\Db\QueueFile $queueFile
		 * @var list<string> $results
		 */
		foreach ($classifierProcess as $queueFile => $results) {
			$this->tagManager->assignTags($queueFile->getFileId(), $results);
			$this->config->setAppValue('recognize', self::MODEL_NAME.'.status', 'true');
			$this->config->setAppValue('recognize', self::MODEL_NAME.'.lastFile', time());
		}
	}
}
