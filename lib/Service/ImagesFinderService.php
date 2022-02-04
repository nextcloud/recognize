<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\SystemTag\ISystemTagObjectMapper;

class ImagesFinderService extends FileFinderService {
	public const FORMATS = ['image/jpeg', 'image/png', 'image/bmp', 'image/tiff'];
	public const IGNORE_MARKERS = ['.noimage', '.nomedia'];
	public const MAX_FILE_SIZE = 10000000;

	public function __construct(Logger $logger, ISystemTagObjectMapper $objectMapper, TagManager $tagManager) {
		parent::__construct($logger, $objectMapper, $tagManager);
		$this->setFormats(self::FORMATS);
		$this->setIgnoreMarkers(self::IGNORE_MARKERS);
		$this->setMaxFileSize(self::MAX_FILE_SIZE);
	}

	/**
	 * @throws NotFoundException|InvalidPathException
	 */
	public function findImagesInFolder(string $user, Folder $folder):array {
		return $this->findFilesInFolder($user, $folder);
	}
}
