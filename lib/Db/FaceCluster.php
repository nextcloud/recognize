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
 * @method string|null getContactUid()
 * @method setContactUid(string|null $contactUid)
 * @method string|null getContactAddressBook()
 * @method setContactAddressBook(string|null $contactAddressBook)
 * @method string|null getContactLinkSource()
 * @method setContactLinkSource(string|null $contactLinkSource)
 */
final class FaceCluster extends Entity {
	/** The link was established by the scheduled matching of contact photos. */
	public const LINK_SOURCE_AUTO = 'auto';
	/** The link, or the absence of one, was decided by the user and is never changed automatically. */
	public const LINK_SOURCE_MANUAL = 'manual';

	protected $title;
	protected $userId;
	protected ?string $contactUid = null;
	protected ?string $contactAddressBook = null;
	protected ?string $contactLinkSource = null;

	/**
	 * @var string[]
	 */
	public static array $columns = ['id', 'title', 'user_id', 'contact_uid', 'contact_address_book', 'contact_link_source'];

	/**
	 * @var string[]
	 */
	public static array $fields = ['id', 'title', 'userId', 'contactUid', 'contactAddressBook', 'contactLinkSource'];

	public function __construct() {
		// add types in constructor
		$this->addType('title', 'string');
		$this->addType('userId', 'string');
		$this->addType('contactUid', 'string');
		$this->addType('contactAddressBook', 'string');
		$this->addType('contactLinkSource', 'string');
	}

	public function isLinked(): bool {
		return $this->getContactUid() !== null && $this->getContactUid() !== ''
			&& $this->getContactAddressBook() !== null && $this->getContactAddressBook() !== '';
	}

	public function toArray(): array {
		$array = [];
		foreach (self::$fields as $field) {
			$array[$field] = $this->{$field};
		}
		return $array;
	}
}
