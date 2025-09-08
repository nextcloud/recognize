<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Db;

/**
 * @method string getTitle()
 * @method setTitle(string $title)
 * @method static self fromRow(array $array)
 */
final class FaceDetectionWithTitle extends FaceDetection {
	protected $title;

	public static $columns = ['id', 'user_id', 'file_id', 'x', 'y', 'height', 'width', 'face_vector', 'cluster_id', 'title'];
	public static $fields = ['id', 'userId', 'fileId', 'x', 'y', 'height', 'width', 'faceVector', 'clusterId', 'title'];

	public function __construct() {
		parent::__construct();
		$this->addType('title', 'string');
	}
}
