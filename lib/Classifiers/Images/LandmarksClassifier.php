<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Classifiers\Images;

use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\TagManager;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\SystemTag\ISystemTag;

class LandmarksClassifier extends Classifier {
	public const IMAGE_TIMEOUT = 480; // seconds
	public const IMAGE_PUREJS_TIMEOUT = 600; // seconds
	public const MODEL_NAME = 'landmarks';
	public const PRECONDITION_TAGS = ['architecture', 'tower', 'monument', 'bridge', 'historic'];

	private Logger $logger;
	private TagManager $tagManager;
	private IConfig $config;
	private QueueService $queue;

	public function __construct(Logger $logger, IConfig $config, TagManager $tagManager, QueueService $queue, IRootFolder $rootFolder) {
		parent::__construct($logger, $config, $rootFolder, $queue);
		$this->logger = $logger;
		$this->config = $config;
		$this->tagManager = $tagManager;
		$this->queue = $queue;
	}

	/**
	 * @param \OCA\Recognize\Db\QueueFile[] $queueFiles
	 * @return void
	 */
	public function classify(array $queueFiles): void {
		$landmarkImages = $this->filterImagesForLandmarks($queueFiles);

		if (count($landmarkImages) === 0) {
			$this->logger->debug('No potential landmarks found');
			return;
		}

		if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true') {
			$timeout = self::IMAGE_PUREJS_TIMEOUT;
		} else {
			$timeout = self::IMAGE_TIMEOUT;
		}
		$classifierProcess = $this->classifyFiles(self::MODEL_NAME, $queueFiles, $timeout);

		foreach ($classifierProcess as $queueFile => $results) {
			$this->tagManager->assignTags($queueFile->getFileId(), $results);
		}
	}


	/**
	 * Filters out only images that have promising imagenet tags
	 *
	 * @param QueueFile[] $queueFiles
	 * @return QueueFile[]
	 */
	private function filterImagesForLandmarks(array $queueFiles) : array {
		// Get tags for each file
		$tagsByFile = $this->tagManager->getTagsForFiles(array_map(function (QueueFile $image) : string {
			return $image->getFileId();
		}, $queueFiles));
		// Map tag objects to strings
		$tagsByFile = array_map(function ($tags) : array {
			return array_map(function (ISystemTag $tag) : string {
				return $tag->getName();
			}, $tags);
		}, $tagsByFile);

		// filter out only those files that might contain a landmark based on the imagenet tags
		$landmarkFiles = array_values(array_filter($queueFiles, function (QueueFile $queueFile) use ($tagsByFile) : bool {
			if (count(array_intersect(self::PRECONDITION_TAGS, $tagsByFile[$queueFile->getFileId()])) !== 0) {
				return true;
			}
			try {
				$this->queue->removeFromQueue('landmarks', $queueFile);
			} catch (Exception $e) {
				$this->logger->error('Could not remove file from queue', ['exception' => $e]);
			}
			return false;
		}));

		return $landmarkFiles;
	}
}
