<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class FsMove
 *
 * @package OCA\Recognize\Db
 * @method int getNodeId()
 * @method setNodeId(int $nodeId)
 */
final class FsMove extends Entity {
	protected ?int $nodeId = null;

	/**
	 * @var string[]
	 */
	public static array $columns = ['id', 'node_id'];

	/**
	 * @var string[]
	 */
	public static array $fields = ['id', 'nodeId'];

	public static string $tableName = 'recognize_fs_moves';

	public function __construct() {
		// add types in constructor
		$this->addType('nodeId', 'integer');
	}
}
