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
use Psr\Log\LoggerInterface;

class GeoClassifier extends Classifier {
	public const IMAGE_TIMEOUT = 5; // seconds
	public const MODEL_NAME = 'geo';

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	private TagManager $tagManager;

	/**
	 * @var \OCP\Files\IRootFolder
	 */
	private IRootFolder $rootFolder;
	/**
	 * @var \OCA\Recognize\Db\ImageMapper
	 */
	private ImageMapper $imageMapper;

	public function __construct(Logger $logger, IConfig $config, TagManager $tagManager, IRootFolder $rootFolder, ImageMapper $imageMapper) {
		parent::__construct($logger, $config);
		$this->logger = $logger;
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
		$timeout = count($paths) * self::IMAGE_TIMEOUT;
		$classifierProcess = $this->classifyFiles(self::MODEL_NAME, $paths, $timeout);

		foreach ($classifierProcess as $i => $results) {
			// assign tags
			$this->tagManager->assignTags($images[$i]->getFileId(), $results);
			// Update processed status
			$images[$i]->setProcessedGeo(true);
			try {
				$this->imageMapper->update($images[$i]);
			} catch (Exception $e) {
				$this->logger->warning($e->getMessage());
			}
		}
	}
}
