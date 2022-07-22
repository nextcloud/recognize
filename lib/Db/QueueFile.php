<?php

namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class QueueFile
 *
 * @package OCA\Recognize\Db
 * @method string getFileId()
 * @method setFileId(int $fileId)
 * @method string getStorageId()
 * @method setStorageId(string $storageId)
 * @method string getRootId()
 * @method setRootId(string $rootId)
 * @method setUpdate(boolean $processed)
 * @method bool getUpdate()
 */
class QueueFile extends Entity {
	public $id;
	protected $fileId;
	protected $storageId;
	protected $rootId;
	protected $update;

	public static array $columns = ['id', 'file_id', 'storage_id', 'root_id', 'update'];
	public static array $fields = ['id', 'fileId', 'storageId', 'rootId', 'update'];

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('storageId', 'integer');
		$this->addType('rootId', 'integer');
		$this->addType('update', 'boolean');
	}

	public function toArray(): array {
		$array = [];
		foreach (self::$fields as $field) {
			$array[$field] = $this->{$field};
		}
		return $array;
	}
}
