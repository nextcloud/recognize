<?php

namespace OCA\Recognize\Db;

/**
 * @method string getTitle()
 * @method setTitle(string $title)
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
