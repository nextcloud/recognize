<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Db;

/**
 * @method string getTitle()
 * @method setTitle(string $title)
 * @method static self fromRow(array $array)
 */
class FaceDetectionWithTitle extends FaceDetection {
	protected $title;

	public static $columns = ['id', 'user_id', 'file_id', 'x', 'y', 'height', 'width', 'vector', 'cluster_id', 'title'];
	public static $fields = ['id', 'userId', 'fileId', 'x', 'y', 'height', 'width', 'vector', 'clusterId', 'title'];

	public function __construct() {
		parent::__construct();
		$this->addType('title', 'string');
	}
}
