<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Classifiers\Video;

use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Db\Image;
use OCA\Recognize\Db\VideoMapper;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\TagManager;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\IConfig;

class MovinetClassifier extends Classifier {
	public const VIDEO_TIMEOUT = 480; // seconds
	public const MODEL_DOWNLOAD_TIMEOUT = 180; // seconds
	public const MODEL_NAME = 'movinet';

	private Logger $logger;
	private TagManager $tagManager;
	private IConfig $config;
	private IRootFolder $rootFolder;
	private VideoMapper $videoMapper;

	public function __construct(Logger $logger, IConfig $config, TagManager $tagManager, IRootFolder $rootFolder, VideoMapper $videoMapper) {
		parent::__construct($logger, $config);
		$this->logger = $logger;
		$this->config = $config;
		$this->tagManager = $tagManager;
		$this->rootFolder = $rootFolder;
		$this->videoMapper = $videoMapper;
	}
	/**
	 * @param \OCA\Recognize\Db\Video[] $videos
	 * @return void
	 * @throws \OCP\Files\NotFoundException
	 */
	public function classify(array $videos): void {
		$paths = array_map(function (Image $image) {
			$file = $this->rootFolder->getById($image->getFileId())[0];
			return $file->getStorage()->getLocalFile($file->getInternalPath());
		}, $videos);
		if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true') {
			throw new \Exception('Movinet does not support WASM mode');
		} else {
			$timeout = count($paths) * self::VIDEO_TIMEOUT + self::MODEL_DOWNLOAD_TIMEOUT;
		}
		$classifierProcess = $this->classifyFiles(self::MODEL_NAME, $paths, $timeout);

		foreach ($classifierProcess as $i => $results) {
			// assign tags
			$this->tagManager->assignTags($videos[$i]->getFileId(), $results);
			// Update processed status
			$videos[$i]->setProcessedMovinet(true);
			try {
				$this->videoMapper->update($videos[$i]);
			} catch (Exception $e) {
				$this->logger->warning($e->getMessage());
			}
		}
	}
}
