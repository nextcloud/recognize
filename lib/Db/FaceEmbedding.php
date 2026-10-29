<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A face found in a file, stored once per file regardless of how many users can access it.
 * The per-user part (cluster assignment) lives in {@see FaceDetection}.
 *
 * @package OCA\Recognize\Db
 * @method int getFileId()
 * @method setFileId(int $fileId)
 * @method float getX()
 * @method float getY()
 * @method float getHeight()
 * @method float getWidth()
 * @method setX(float $x)
 * @method setY(float $y)
 * @method setHeight(float $height)
 * @method setWidth(float $width)
 */
class FaceEmbedding extends Entity {
	protected $fileId;
	protected $x;
	protected $y;
	protected $height;
	protected $width;
	protected $faceVector;
	/**
	 * @var string[]
	 */
	public static $columns = ['id', 'file_id', 'x', 'y', 'height', 'width', 'face_vector'];
	/**
	 * @var string[]
	 */
	public static $fields = ['id', 'fileId', 'x', 'y', 'height', 'width', 'faceVector'];

	public function __construct() {
		// add types in constructor
		$this->addType('fileId', 'integer');
		$this->addType('x', 'float');
		$this->addType('y', 'float');
		$this->addType('height', 'float');
		$this->addType('width', 'float');
		$this->addType('faceVector', 'json');
	}

	public function getVector(): array {
		return $this->getter('faceVector');
	}
	public function setVector(array $vector): void {
		$this->setter('faceVector', [$vector]);
	}
}
