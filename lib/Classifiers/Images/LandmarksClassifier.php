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
use OCP\SystemTag\ISystemTag;

class LandmarksClassifier extends Classifier {
	public const IMAGE_TIMEOUT = 480; // seconds
	public const IMAGE_PUREJS_TIMEOUT = 600; // seconds
	public const MODEL_DOWNLOAD_TIMEOUT = 180; // seconds
	public const PRECONDITION_TAGS = ['architecture', 'tower', 'monument', 'bridge', 'historic'];
	public const MODEL_NAME = 'landmarks';


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
	 * @param Image[] $inputImages
	 * @return void
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\NotFoundException
	 */
	public function classify(array $inputImages): void {
		$landmarkImages = $this->filterImagesForLandmarks($inputImages);

		if (count($landmarkImages) === 0) {
			$this->logger->debug('No potential landmarks found');
			return;
		}

		$paths = [];
		$images = [];
		foreach ($landmarkImages as $image) {
			$files = $this->rootFolder->getById($image->getFileId());
			if (count($files) === 0) {
				continue;
			}
			$images[] = $image;
			$paths[] = $files[0]->getStorage()->getLocalFile($files[0]->getInternalPath());
		}

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
			$landmarkImages[$i]->setProcessedLandmarks(true);
			try {
				$this->imageMapper->update($images[$i]);
			} catch (Exception $e) {
				$this->logger->warning($e->getMessage(), ['exception' => $e]);
			}
		}
	}

	/**
	 * @param Image[] $images
	 * @return Image[]
	 * @throws \OCP\DB\Exception
	 */
	private function filterImagesForLandmarks(array $images) : array {
		$tagsByFile = $this->tagManager->getTagsForFiles(array_map(function (Image $image) : string {
			return $image->getFileId();
		}, $images));
		$tagsByFile = array_map(function ($tags) : array {
			return array_map(function (ISystemTag $tag) : string {
				return $tag->getName();
			}, $tags);
		}, $tagsByFile);
		$landmarkFiles = array_values(array_filter($images, function (Image $image) use ($tagsByFile) : bool {
			if (count(array_intersect(self::PRECONDITION_TAGS, $tagsByFile[$image->getFileId()])) !== 0) {
				return true;
			}
			$image->setProcessedLandmarks(true);
			$this->imageMapper->update($image);
			return false;
		}));

		return $landmarkFiles;
	}
}
