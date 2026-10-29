<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FaceEmbedding>
 */
final class FaceEmbeddingMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'recognize_face_vectors', FaceEmbedding::class);
		$this->db = $db;
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @return list<\OCA\Recognize\Db\FaceEmbedding>
	 */
	public function findByFileId(int $fileId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceEmbedding::$columns)
			->from('recognize_face_vectors')
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function countByFileId(int $fileId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id'))
			->from('recognize_face_vectors')
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		/** @var int|string $count */
		$count = $result->fetch(\PDO::FETCH_COLUMN);
		$result->closeCursor();
		return (int)$count;
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function deleteByFileId(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_vectors')
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function deleteAll(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_vectors')
			->executeStatement();
	}
}
