<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class FaceCluster
 *
 * @package OCA\Recognize\Db
 * @method string getTitle()
 * @method setTitle(string $title)
 * @method string getUserId()
 * @method setUserId(string $userId)
 */
class FaceCluster extends Entity {
	protected $title;
	protected $userId;

	/**
	 * @var string[]
	 */
	public static array $columns = ['id', 'title', 'user_id'];

	/**
	 * @var string[]
	 */
	public static array $fields = ['id', 'title', 'userId'];

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('title', 'string');
		$this->addType('userId', 'string');
	}

	public function toArray(): array {
		$array = [];
		foreach (self::$fields as $field) {
			$array[$field] = $this->{$field};
		}
		return $array;
	}
}
