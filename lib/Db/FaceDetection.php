<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
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
 * @method setX(float $x)
 * @method setY(float $y)
 * @method setHeight(float $height)
 * @method setWidth(float $width)
 * @method setClusterId(int|null $clusterId)
 * @method int|null getClusterId()
 * @method float getThreshold()
 * @method setThreshold(float $threshold)
 */
class FaceDetection extends Entity {
	protected $fileId;
	protected $userId;
	protected $x;
	protected $y;
	protected $height;
	protected $width;
	protected $faceVector;
	protected $clusterId;
	protected $threshold;
	/**
	 * @var string[]
	 */
	public static $columns = ['id', 'user_id', 'file_id', 'x', 'y', 'height', 'width', 'face_vector', 'cluster_id', 'threshold'];
	/**
	 * @var string[]
	 */
	public static $fields = ['id', 'userId', 'fileId', 'x', 'y', 'height', 'width', 'faceVector', 'clusterId', 'threshold'];

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('x', 'float');
		$this->addType('y', 'float');
		$this->addType('height', 'float');
		$this->addType('width', 'float');
		$this->addType('faceVector', 'json');
		$this->addType('clusterId', 'integer');
		$this->addType('threshold', 'float');
	}

	public function toArray(): array {
		$array = [];
		foreach (static::$fields as $field) {
			if ($field === 'faceVector') {
				continue;
			}
			$array[$field] = $this->{$field};
		}
		return $array;
	}

	public function getVector(): array {
		return $this->getter('faceVector');
	}
	public function setVector(array $vector): void {
		$this->setter('faceVector', [$vector]);
	}
}
