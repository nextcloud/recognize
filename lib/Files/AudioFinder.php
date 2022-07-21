<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Files;

use OCA\Recognize\Service\Logger;

abstract class AudioFinder extends FileFinder {
	public const FORMATS = ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/vnd.wav', 'audio/flac'];
	public const IGNORE_MARKERS = ['.nomusic', '.nomedia'];

	public function __construct(Logger $logger) {
		parent::__construct($logger);
		$this->setFormats(self::FORMATS);
		$this->setIgnoreMarkers(self::IGNORE_MARKERS);
	}
}
