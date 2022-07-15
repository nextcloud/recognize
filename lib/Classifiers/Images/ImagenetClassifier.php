<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Classifiers\Images;

use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Db\Image;
use OCA\Recognize\Db\ImageMapper;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\TagManager;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\IConfig;

class ImagenetClassifier extends Classifier {
	public const IMAGE_TIMEOUT = 480; // seconds
	public const IMAGE_PUREJS_TIMEOUT = 600; // seconds
	public const MODEL_DOWNLOAD_TIMEOUT = 180; // seconds
	public const MODEL_NAME = 'imagenet';

	private Logger $logger;
	private TagManager $tagManager;
	private IConfig $config;
	private IRootFolder $rootFolder;
	private ImageMapper $imageMapper;

	public function __construct(Logger $logger, IConfig $config, TagManager $tagManager, IRootFolder $rootFolder, ImageMapper $imageMapper) {
		parent::__construct($logger, $config);
		$this->logger = $logger;
		$this->config = $config;
		$this->tagManager = $tagManager;
		$this->rootFolder = $rootFolder;
		$this->imageMapper = $imageMapper;
	}

	/**
	 * @param \OCA\Recognize\Db\Image[] $images
	 * @return void
	 * @throws \OCP\Files\NotFoundException
	 */
	public function classify(array $images): void {
		$paths = array_map(function (Image $image) {
			$file = $this->rootFolder->getById($image->getFileId())[0];
			return $file->getStorage()->getLocalFile($file->getInternalPath());
		}, $images);
		if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true') {
			$timeout = count($paths) * self::IMAGE_PUREJS_TIMEOUT + self::MODEL_DOWNLOAD_TIMEOUT;
		} else {
			$timeout = count($paths) * self::IMAGE_TIMEOUT + self::MODEL_DOWNLOAD_TIMEOUT;
		}
		$classifierProcess = $this->classifyFiles(self::MODEL_NAME, $paths, $timeout);

		foreach ($classifierProcess as $i => $results) {
			// assign tags
			$this->tagManager->assignTags($images[$i]->getFileId(), $results);
			// Update processed status
			$images[$i]->setProcessedImagenet(true);
			try {
				$this->imageMapper->update($images[$i]);
			} catch (Exception $e) {
				$this->logger->warning($e->getMessage());
			}
		}
	}
}
