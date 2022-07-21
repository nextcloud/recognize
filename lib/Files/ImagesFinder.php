<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Files;

use OCA\Recognize\Service\Logger;

abstract class ImagesFinder extends FileFinder {
	public const FORMATS = ['image/jpeg', 'image/png', 'image/bmp', 'image/tiff'];
	public const IGNORE_MARKERS = ['.noimage', '.nomedia'];
	public const MAX_FILE_SIZE = 10000000;

	public function __construct(Logger $logger) {
		parent::__construct($logger);
		$this->setFormats(self::FORMATS);
		$this->setIgnoreMarkers(self::IGNORE_MARKERS);
		$this->setMaxFileSize(self::MAX_FILE_SIZE);
	}
}
