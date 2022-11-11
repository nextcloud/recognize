<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Classifiers\Images;

use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\TagManager;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IPreview;
use OCP\ITempManager;

class ImagenetClassifier extends Classifier {
	public const IMAGE_TIMEOUT = 480; // seconds
	public const IMAGE_PUREJS_TIMEOUT = 600; // seconds
	public const MODEL_NAME = 'imagenet';

	private TagManager $tagManager;
	protected QueueService $queue;

	public function __construct(Logger $logger, IConfig $config, TagManager $tagManager, QueueService $queue, IRootFolder $rootFolder, ITempManager $tempManager, IPreview $previewProvider) {
		parent::__construct($logger, $config, $rootFolder, $queue, $tempManager, $previewProvider);
		$this->tagManager = $tagManager;
		$this->queue = $queue;
	}

	/**
	 * @param \OCA\Recognize\Db\QueueFile[] $queueFiles
	 * @return void
	 */
	public function classify(array $queueFiles): void {
		if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true') {
			$timeout = self::IMAGE_PUREJS_TIMEOUT;
		} else {
			$timeout = self::IMAGE_TIMEOUT;
		}
		$classifierProcess = $this->classifyFiles(self::MODEL_NAME, $queueFiles, $timeout);

		/** @var \OCA\Recognize\Db\QueueFile $queueFile */
		/** @var list<string> $results */
		foreach ($classifierProcess as $queueFile => $results) {
			$this->tagManager->assignTags($queueFile->getFileId(), $results);
			$landmarkTags = array_filter($results, function ($tagName) {
				return in_array($tagName, LandmarksClassifier::PRECONDITION_TAGS);
			});
			$this->config->setAppValue('recognize', self::MODEL_NAME.'.status', 'true');
			$this->config->setAppValue('recognize', self::MODEL_NAME.'.lastFile', time());

			if (count($landmarkTags) > 0) {
				try {
					$this->queue->insertIntoQueue(LandmarksClassifier::MODEL_NAME, $queueFile);
				} catch (Exception $e) {
					$this->logger->error('Cannot insert file into queue', ['exception' => $e]);
				}
			}
		}
	}
}
