<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Db;

use OCA\Recognize\BackgroundJobs\ProcessAccessUpdatesJob;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @psalm-extends QBMapper<AccessUpdate>
 */
final class AccessUpdateMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private IJobList $jobList,
	) {
		parent::__construct($db, 'recognize_access_updates', AccessUpdate::class);
		$this->db = $db;
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @return list<\OCA\Recognize\Db\AccessUpdate>
	 */
	public function findByStorageId(int $storageId, int $limit = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct(AccessUpdate::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('storage_id', $qb->createPositionalParameter($storageId, IQueryBuilder::PARAM_INT)));
		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}
		return $this->findEntities($qb);
	}

	/**
	 * @param int $storageId
	 * @return int
	 * @throws Exception
	 */
	public function countByStorageId(int $storageId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id'))
			->from($this->getTableName())
			->where($qb->expr()->eq('storage_id', $qb->createPositionalParameter($storageId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();
		if ($count === false) {
			return 0;
		}
		return $count;
	}

	/**
	 * @param int $storageId
	 * @param int $rootId
	 * @return AccessUpdate
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findByStorageIdAndRootId(int $storageId, int $rootId): AccessUpdate {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct(AccessUpdate::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('storage_id', $qb->createPositionalParameter($storageId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('root_id', $qb->createPositionalParameter($rootId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @param int $storageId
	 * @param int $rootId
	 * @return AccessUpdate
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function insertAccessUpdate(int $storageId, int $rootId): AccessUpdate {
		try {
			$accessUpdate = $this->findByStorageIdAndRootId($storageId, $rootId);
		} catch (DoesNotExistException $e) {
			$accessUpdate = new AccessUpdate();
			$accessUpdate->setStorageId($storageId);
			$accessUpdate->setRootId($rootId);
			$this->insert($accessUpdate);
			if (!$this->jobList->has(ProcessAccessUpdatesJob::class, [ 'storage_id' => $storageId ])) {
				$this->jobList->add(self::class, [ 'storage_id' => $storageId ]);
			}
		}
		return $accessUpdate;
	}
}
