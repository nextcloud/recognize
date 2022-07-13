<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Files;

use OCA\Recognize\Service\Logger;

abstract class VideoFinder extends FileFinder {
	public const FORMATS = ['image/gif', 'video/mp4', 'video/MP2T', 'video/x-msvideo', 'video/x-ms-wmv', 'video/quicktime', 'video/ogg', 'video/mpeg', 'video/webm', 'video/x-matroska'];
	public const IGNORE_MARKERS = ['.novideo', '.nomedia'];

	public function __construct(Logger $logger) {
		parent::__construct($logger);
		$this->setFormats(self::FORMATS);
		$this->setIgnoreMarkers(self::IGNORE_MARKERS);
	}
}
