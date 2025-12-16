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
 * @method string getOwner()
 * @method setOwner(string $owner)
 */
final class FsMove extends Entity {
	protected ?int $nodeId = null;
	protected ?string $owner = null;
	protected ?string $addedUsers = null;
	protected ?string $targetUsers = null;

	/**
	 * @var string[]
	 */
	public static array $columns = ['id', 'node_id', 'owner', 'added_users', 'target_users'];

	/**
	 * @var string[]
	 */
	public static array $fields = ['id', 'nodeId', 'owner', 'addedUsers', 'targetUsers'];

	public static string $tableName = 'recognize_fs_moves';

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('nodeId', 'integer');
		$this->addType('owner', 'string');
		$this->addType('addedUsers', 'string');
		$this->addType('targetUsers', 'string');
	}

	public function getAddedUsers(): array {
		return explode(',', $this->addedUsers);
	}

	public function getTargetUsers(): array {
		return explode(',', $this->targetUsers);
	}

	/**
	 * @param list<string> $users
	 */
	public function setAddedUsers(array $users): void {
		$this->setter('addedUsers', [implode(',', $users)]);
	}

	/**
	 * @param list<string> $users
	 */
	public function setTargetUsers(array $users): void {
		$this->setter('targetUsers', [implode(',', $users)]);
	}
}
