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
 * @method float getX()
 * @method float getY()
 * @method float getHeight()
 * @method float getWidth()
 * @method array getVector()
 * @method setVector(array $vector)
 * @method setX(float $x)
 * @method setY(float $y)
 * @method setHeight(int $height)
 * @method setWidth(int $width)
 * @method setClusterId(int|null $clusterId)
 * @method int getClusterId()
 * @method float getThreshold()
 *  @method setThreshold(float $threshold)
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
	protected $threshold;

	public static $columns = ['id', 'user_id', 'file_id', 'x', 'y', 'height', 'width', 'vector', 'cluster_id', 'threshold'];
	public static $fields = ['id', 'userId', 'fileId', 'x', 'y', 'height', 'width', 'vector', 'clusterId', 'threshold'];

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
		$this->addType('threshold', 'float');
	}

	public function toArray(): array {
		$array = [];
		foreach (static::$fields as $field) {
			$array[$field] = $this->{$field};
		}
		return $array;
	}

	public function markFieldUpdated($attribute) {
		parent::markFieldUpdated($attribute);
	}
}
