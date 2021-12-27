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

class AudioFinderService extends FileFinderService {
	public const FORMATS = ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/vnd.wav', 'audio/flac'];
	public const IGNORE_MARKERS = ['.nomusic', '.nomedia'];

	public function __construct(Logger $logger, ISystemTagObjectMapper $objectMapper, TagManager $tagManager) {
		parent::__construct($logger, $objectMapper, $tagManager);
		$this->setFormats(self::FORMATS);
		$this->setIgnoreMarkers(self::IGNORE_MARKERS);
	}

	/**
	 * @throws NotFoundException|InvalidPathException
	 */
	public function findAudioInFolder(Folder $folder):array {
		return $this->findFilesInFolder($folder);
	}
}
