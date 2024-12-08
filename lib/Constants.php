<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize;

class Constants {
	public const IMAGE_FORMATS = ['image/jpeg', 'image/png', 'image/bmp', 'image/heic', 'image/heif', 'image/tiff'];
	public const AUDIO_FORMATS = ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/vnd.wav', 'audio/flac'];
	public const VIDEO_FORMATS = ['image/gif', 'video/mp4', 'video/MP2T', 'video/x-msvideo', 'video/x-ms-wmv', 'video/quicktime', 'video/ogg', 'video/mpeg', 'video/webm', 'video/x-matroska'];
	public const DIRECTORY_FORMATS = ['httpd/unix-directory'];
	public const IGNORE_MARKERS_ALL = ['.nomedia'];
	public const IGNORE_MARKERS_IMAGE = ['.noimage'];
	public const IGNORE_MARKERS_VIDEO = ['.novideo'];
	public const IGNORE_MARKERS_AUDIO = ['.nomusic'];
	public const MAX_FILE_SIZE = 10000000;
}
