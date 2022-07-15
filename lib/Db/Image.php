<?php

namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class FaceCluster
 *
 * @package OCA\Recognize\Db
 * @method string getFileId()
 * @method setFileId(int $fileId)
 * @method string getUserId()
 * @method setUserId(string $userId)
 * @method setProcessedGeo(boolean $processed)
 * @method setProcessedLandmarks(boolean $processed)
 * @method setProcessedImagenet(boolean $processed)
 * @method setProcessedFaces(boolean $processed)
 * @method bool getProcessedGeo()
 * @method bool getProcessedImagenet()
 * @method bool getProcessedLandmarks()
 * @method bool getProcessedFaces()
 */
class Image extends Entity {
	public $id;
	protected $fileId;
	protected $userId;
	protected $processedGeo;
	protected $processedImagenet;
	protected $processedLandmarks;
	protected $processedFaces;

	public static array $columns = ['id', 'file_id', 'user_id', 'processed_geo', 'processed_imagenet', 'processed_landmarks', 'processed_faces'];
	public static array $fields = ['id', 'fileId', 'userId', 'processedGeo', 'processedImagenet', 'processedLandmarks', 'processedFaces'];

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('file_id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('processedGeo', 'boolean');
		$this->addType('processedImagenet', 'boolean');
		$this->addType('processedLandmarks', 'boolean');
		$this->addType('processedFaces', 'boolean');
	}

	public function toArray(): array {
		$array = [];
		foreach (self::$fields as $field) {
			$array[$field] = $this->{$field};
		}
		return $array;
	}
}
