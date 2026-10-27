<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ContactFace>
 */
final class ContactFaceMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'recognize_contact_faces', ContactFace::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findByContact(string $userId, string $addressBook, string $contactUid): ContactFace {
		$qb = $this->db->getQueryBuilder();
		$qb->select(ContactFace::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->eq('address_book', $qb->createPositionalParameter($addressBook)))
			->andWhere($qb->expr()->eq('contact_uid', $qb->createPositionalParameter($contactUid)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws Exception
	 */
	public function deleteAll(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->executeStatement();
	}
}
