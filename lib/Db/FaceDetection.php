<?php

namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class FaceDetection
 *
 * @package OCA\Recognize\Db
 * @method int getFileId()
 * @method setFileId(int $fileId)
 * @method setUserId(string $userId)
 * @method string getUserId()
 * @method int getX()
 * @method int getY()
 * @method int getHeight()
 * @method int getWidth()
 * @method array getVector()
 * @method setVector(array $vector)
 * @method setX(int $x)
 * @method setY(int $y)
 * @method setHeight(int $height)
 * @method setWidth(int $width)
 * @method setClusterId(int $clusterId)
 * @method int getClusterId()
 */
class FaceDetection extends Entity {
	protected $fileId;
	protected $userId;
	protected $x;
	protected $y;
	protected $height;
	protected $width;
	protected $vector;
	protected $clusterId;

	public static $columns = ['id', 'user_id', 'file_id', 'x', 'y', 'height', 'width', 'vector', 'cluster_id'];
	public static $fields = ['id', 'userId', 'fileId', 'x', 'y', 'height', 'width', 'vector', 'clusterId'];

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('x', 'float');
		$this->addType('y', 'float');
		$this->addType('height', 'float');
		$this->addType('width', 'float');
		$this->addType('vector', 'json');
		$this->addType('clusterId', 'int');
	}

	public function toArray(): array {
		$array = [];
		foreach (self::$fields as $field) {
			$array[$field] = $this->{$field};
		}
		return $array;
	}
}
