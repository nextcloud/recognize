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
 * @method setProcessedMusicnn(boolean $processed)
 * @method bool getProcessedMusicnn()
 */
class Audio extends Entity {
    public $id;
	protected int $fileId;
	protected string $userId;
	protected bool $processedMusicnn;

	public static array $columns = ['id', 'file_id', 'user_id', 'processed_musicnn'];
	public static array $fields = ['id', 'fileId', 'userId', 'processedMusicnn'];

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('file_id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('processedMusicnn', 'boolean');
	}

	public function toArray(): array {
		$array = [];
		foreach (self::$fields as $field) {
			$array[$field] = $this->{$field};
		}
		return $array;
	}
}
