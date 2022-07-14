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
 * @method setProcessedMovinet(boolean $processed)
 * @method bool getProcessedMovinet()
 */
class Video extends Entity {
	public $id;
	protected int $fileId;
	protected string $userId;
	protected bool $processedMovinet;

	public static array $columns = ['id', 'file_id', 'user_id', 'processed_movinet'];
	public static array $fields = ['id', 'fileId', 'userId', 'processedMovinet'];

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('file_id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('processedMovinet', 'boolean');
	}

	public function toArray(): array {
		$array = [];
		foreach (self::$fields as $field) {
			$array[$field] = $this->{$field};
		}
		return $array;
	}
}
