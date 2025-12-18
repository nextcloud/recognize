<?php

/*
 * Copyright (c) 2021-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Classifiers\Audio;

use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\IRootFolder;
use OCP\IPreview;
use OCP\ITempManager;
use Override;

final class MusicnnClassifier extends Classifier {
	public const AUDIO_TIMEOUT = 40; // seconds
	public const AUDIO_PUREJS_TIMEOUT = 420; // seconds
	public const MODEL_NAME = 'musicnn';

	private TagManager $tagManager;

	public function __construct(Logger $logger, IAppConfig $config, TagManager $tagManager, QueueService $queue, IRootFolder $rootFolder, ITempManager $tempManager, IPreview $previewProvider) {
		parent::__construct($logger, $config, $rootFolder, $queue, $tempManager, $previewProvider);
		$this->tagManager = $tagManager;
	}

	#[Override]
	public function classify(array $queueFiles): void {
		if ($this->config->getAppValueString('tensorflow.purejs', 'false') === 'true') {
			$timeout = self::AUDIO_PUREJS_TIMEOUT;
		} else {
			$timeout = self::AUDIO_TIMEOUT;
		}
		$classifierProcess = $this->classifyFiles(self::MODEL_NAME, $queueFiles, $timeout);

		/**
		 * @var list<string> $results
		 */
		foreach ($classifierProcess as $queueFile => $results) {
			$this->tagManager->assignTags($queueFile->getFileId(), $results);
			$this->config->setAppValueString(self::MODEL_NAME.'.status', 'true');
			$this->config->setAppValueString(self::MODEL_NAME.'.lastFile', (string)time());
		}
	}
}
