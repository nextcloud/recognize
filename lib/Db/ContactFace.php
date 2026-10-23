<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\Entity;

/**
 * The face embedding found in the photo of a contact, cached per user so the
 * photo only has to be analyzed again when it changes.
 *
 * @method string getUserId()
 * @method setUserId(string $userId)
 * @method string getAddressBook()
 * @method setAddressBook(string $addressBook)
 * @method string getContactUid()
 * @method setContactUid(string $contactUid)
 * @method string getPhotoHash()
 * @method setPhotoHash(string $photoHash)
 * @method int getFaceCount()
 * @method setFaceCount(int $faceCount)
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class ContactFace extends Entity {
	protected ?string $userId = null;
	protected ?string $addressBook = null;
	protected ?string $contactUid = null;
	protected ?string $photoHash = null;
	protected ?array $faceVector = null;
	protected ?int $faceCount = null;

	/**
	 * @var string[]
	 */
	public static array $columns = ['id', 'user_id', 'address_book', 'contact_uid', 'photo_hash', 'face_vector', 'face_count'];

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('addressBook', 'string');
		$this->addType('contactUid', 'string');
		$this->addType('photoHash', 'string');
		$this->addType('faceVector', 'json');
		$this->addType('faceCount', 'integer');
	}

	/**
	 * @return list<float>|null The embedding, or null when the photo holds no usable face
	 */
	public function getVector(): ?array {
		/** @var list<float>|null $vector */
		$vector = $this->getter('faceVector');
		return $vector;
	}

	/**
	 * @param list<float>|null $vector
	 */
	public function setVector(?array $vector): void {
		$this->setter('faceVector', [$vector]);
	}
}
