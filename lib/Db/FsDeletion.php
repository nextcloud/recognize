<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class FsDeletion
 *
 * @package OCA\Recognize\Db
 * @method int getStorageId()
 * @method setStorageId(int $storageId)
 * @method int getNodeId()
 * @method setNodeId(int $rootId)
 */
final class FsDeletion extends Entity {
	protected ?int $storageId = null;
	protected ?int $nodeId = null;

	/**
	 * @var string[]
	 */
	public static array $columns = ['id', 'storage_id', 'node_id'];

	/**
	 * @var string[]
	 */
	public static array $fields = ['id', 'storageId', 'nodeId'];

	public static string $tableName = 'recognize_fs_deletions';

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('storageId', 'integer');
		$this->addType('nodeId', 'integer');
	}
}
