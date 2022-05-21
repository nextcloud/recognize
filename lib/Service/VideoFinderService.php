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

class VideoFinderService extends FileFinderService {
	public const FORMATS = ['image/gif', 'video/mp4', 'video/MP2T', 'video/x-msvideo', 'video/x-ms-wmv', 'video/quicktime', 'video/ogg', 'video/mpeg', 'video/webm', 'video/x-matroska'];
	public const IGNORE_MARKERS = ['.novideo', '.nomedia'];

	public function __construct(Logger $logger, ISystemTagObjectMapper $objectMapper, TagManager $tagManager) {
		parent::__construct($logger, $objectMapper, $tagManager);
		$this->setFormats(self::FORMATS);
		$this->setIgnoreMarkers(self::IGNORE_MARKERS);
	}

	/**
	 * @throws NotFoundException|InvalidPathException
	 */
	public function findVideoInFolder(string $user, Folder $folder):array {
		return $this->findFilesInFolder($user, $folder);
	}
}
