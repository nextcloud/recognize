<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Db;

use OCA\Recognize\BackgroundJobs\ProcessFsActionsJob;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FsAccessUpdate|FsCreation|FsDeletion|FsMove>
 */
final class FsActionMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private IJobList $jobList,
	) {
		parent::__construct($db, '', FsAccessUpdate::class);
		$this->db = $db;
	}

	/**
	 * @param class-string<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $className
	 * @param int $storageId
	 * @param int $limit
	 * @return list<FsCreation|FsDeletion|FsMove|FsAccessUpdate>
	 * @throws \Exception
	 */
	public function findByStorageId(string $className, int $storageId, int $limit = 0): array {
		if (!in_array('storage_id', $className::$columns, true)) {
			throw new \Exception('entity does not have a storage_id column');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct($className::$columns)
			->from($className::$tableName)
			->where($qb->expr()->eq('storage_id', $qb->createPositionalParameter($storageId, IQueryBuilder::PARAM_INT)));
		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}
		return $this->findItems($className, $qb);
	}

	/**
	 * @param class-string<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $className
	 * @return list<FsCreation|FsDeletion|FsMove|FsAccessUpdate>
	 * @throws \OCP\DB\Exception
	 * @throws \Exception
	 */
	public function find(string $className, int $limit = 0): array {
		if (!in_array('storage_id', $className::$columns, true)) {
			throw new \Exception('entity does not have a storage_id column');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct($className::$columns)
			->from($className::$tableName);
		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}
		return $this->findItems($className, $qb);
	}

	/**
	 * @param class-string<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $className
	 * @param int $nodeId
	 * @return FsCreation|FsDeletion|FsMove|FsAccessUpdate
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByNodeId(string $className, int $nodeId): Entity {
		if (!in_array('node_id', $className::$columns, true)) {
			throw new \Exception('entity does not have a node_id column');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct($className::$columns)
			->from($className::$tableName)
			->where($qb->expr()->eq('node_id', $qb->createPositionalParameter($nodeId, IQueryBuilder::PARAM_INT)));
		return $this->findItem($className, $qb);
	}

	/**
	 * @param class-string<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $className
	 * @param int $storageId
	 * @return int
	 * @throws Exception
	 */
	public function countByStorageId(string $className, int $storageId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id'))
			->from($className::$tableName)
			->where($qb->expr()->eq('storage_id', $qb->createPositionalParameter($storageId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		/** @var int|false $count */
		$count = $result->fetchOne();
		$result->closeCursor();
		if ($count === false) {
			return 0;
		}
		return $count;
	}

	/**
	 * @param class-string<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $className
	 * @return int
	 * @throws Exception
	 */
	public function count(string $className): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id'))
			->from($className::$tableName);
		$result = $qb->executeQuery();
		/** @var int|false $count */
		$count = $result->fetchOne();
		$result->closeCursor();
		if ($count === false) {
			return 0;
		}
		return $count;
	}

	/**
	 * @param class-string<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $className
	 * @param int $storageId
	 * @param int $rootId
	 * @return FsCreation|FsDeletion|FsMove|FsAccessUpdate
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByStorageIdAndRootId(string $className, int $storageId, int $rootId): Entity {
		if (!in_array('storage_id', $className::$columns, true) || (!in_array('root_id', $className::$columns, true) && !in_array('node_id', $className::$columns, true))) {
			throw new \Exception('entity does not have all required columns: storage_id, node_id/root_id');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct($className::$columns)
			->from($className::$tableName)
			->where($qb->expr()->eq('storage_id', $qb->createPositionalParameter($storageId, IQueryBuilder::PARAM_INT)));
		if (in_array('root_id', $className::$columns, true)) {
			$qb->andWhere($qb->expr()->eq('root_id', $qb->createPositionalParameter($rootId, IQueryBuilder::PARAM_INT)));
		}
		if (in_array('node_id', $className::$columns, true)) {
			$qb->andWhere($qb->expr()->eq('node_id', $qb->createPositionalParameter($rootId, IQueryBuilder::PARAM_INT)));
		}
		return $this->findItem($className, $qb);
	}

	/**
	 * @param int $storageId
	 * @param int $rootId
	 * @return FsCreation|FsDeletion|FsMove|FsAccessUpdate
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function insertAccessUpdate(int $storageId, int $rootId): Entity {
		try {
			$accessUpdate = $this->findByStorageIdAndRootId(FsAccessUpdate::class, $storageId, $rootId);
		} catch (DoesNotExistException $e) {
			$accessUpdate = new FsAccessUpdate();
			$accessUpdate->setStorageId($storageId);
			$accessUpdate->setRootId($rootId);
			$this->insert($accessUpdate);
			$arguments = [ 'type' => FsAccessUpdate::class, 'storage_id' => $storageId ];
			if (!$this->jobList->has(ProcessFsActionsJob::class, $arguments)) {
				$this->jobList->add(ProcessFsActionsJob::class, $arguments);
			}
		}
		return $accessUpdate;
	}

	/**
	 * @param int $storageId
	 * @param int $rootId
	 * @return FsCreation|FsDeletion|FsMove|FsAccessUpdate
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function insertCreation(int $storageId, int $rootId): Entity {
		try {
			$creation = $this->findByStorageIdAndRootId(FsCreation::class, $storageId, $rootId);
		} catch (DoesNotExistException $e) {
			$creation = new FsCreation();
			$creation->setStorageId($storageId);
			$creation->setRootId($rootId);
			$this->insert($creation);
			$arguments = [ 'type' => FsCreation::class, 'storage_id' => $storageId ];
			if (!$this->jobList->has(ProcessFsActionsJob::class, $arguments)) {
				$this->jobList->add(ProcessFsActionsJob::class, $arguments);
			}
		}
		return $creation;
	}


	/**
	 * @param int $storageId
	 * @param int $nodeId
	 * @return FsCreation|FsDeletion|FsMove|FsAccessUpdate
	 * @throws Exception|MultipleObjectsReturnedException
	 */
	public function insertDeletion(int $storageId, int $nodeId): FsCreation|FsDeletion|FsMove|FsAccessUpdate {
		try {
			$deletion = $this->findByNodeId(FsDeletion::class, $nodeId);
		} catch (DoesNotExistException $e) {
			$deletion = new FsDeletion();
			$deletion->setStorageId($storageId);
			$deletion->setNodeId($nodeId);
			$this->insert($deletion);
			$arguments = [ 'type' => FsDeletion::class, 'storage_id' => $storageId ];
			if (!$this->jobList->has(ProcessFsActionsJob::class, $arguments)) {
				$this->jobList->add(ProcessFsActionsJob::class, $arguments);
			}
		}
		return $deletion;
	}

	/**
	 * @param int $nodeId
	 * @param string $owner
	 * @param list<string> $addedUsers
	 * @param list<string> $targetUsers
	 * @return FsCreation|FsDeletion|FsMove|FsAccessUpdate
	 * @throws Exception|MultipleObjectsReturnedException
	 */
	public function insertMove(int $nodeId, string $owner, array $addedUsers, array $targetUsers): Entity {
		try {
			$move = $this->findByNodeId(FsMove::class, $nodeId);
		} catch (DoesNotExistException $e) {
			$move = new FsMove();
			$move->setNodeId($nodeId);
			$move->setOwner($owner);
			$move->setAddedUsers($addedUsers);
			$move->setTargetUsers($targetUsers);
			$this->insert($move);
			$arguments = [ 'type' => FsDeletion::class ];
			if (!$this->jobList->has(ProcessFsActionsJob::class, $arguments)) {
				$this->jobList->add(ProcessFsActionsJob::class, $arguments);
			}
		}
		return $move;
	}

	/**
	 * @param FsCreation|FsDeletion|FsMove|FsAccessUpdate $entity
	 * @return FsCreation|FsDeletion|FsMove|FsAccessUpdate
	 * @throws Exception
	 */
	public function insert(Entity $entity): Entity {
		// get updated fields to save, fields have to be set using a setter to
		// be saved
		/** @var array<string, mixed> $properties */
		$properties = $entity->getUpdatedFields();

		$qb = $this->db->getQueryBuilder();
		$qb->insert($entity::$tableName);

		// build the fields
		foreach ($properties as $property => $updated) {
			$column = $entity->propertyToColumn($property);
			$getter = 'get' . ucfirst($property);
			$value = $entity->$getter();

			$type = $this->getParameterTypeForProperty($entity, $property);
			$qb->setValue($column, $qb->createNamedParameter($value, $type));
		}

		$qb->executeStatement();

		/** @psalm-suppress DocblockTypeContradiction */
		if ($entity->id === null) {
			// When autoincrement is used id is always an int
			$entity->setId($qb->getLastInsertId());
		}

		return $entity;
	}

	/**
	 * Returns an db result and throws exceptions when there are more or less
	 * results
	 *
	 * @param class-string<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $className
	 * @param IQueryBuilder $query
	 * @return FsCreation|FsDeletion|FsMove|FsAccessUpdate the entity
	 * @throws DoesNotExistException if the item does not exist
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException if more than one item exist
	 */
	protected function findItem(string $className, IQueryBuilder $query): Entity {
		return $this->mapRowToItem($className, $this->findOneQuery($query));
	}

	/**
	 * Runs a sql query and returns an array of items
	 *
	 * @param class-string<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $className
	 * @param IQueryBuilder $query
	 * @return list<FsCreation|FsDeletion|FsMove|FsAccessUpdate> all fetched entities
	 */
	protected function findItems(string $className, IQueryBuilder $query): array {
		$result = $query->executeQuery();
		try {
			$entities = [];
			while ($row = $result->fetch()) {
				$entities[] = $this->mapRowToItem($className, $row);
			}
			return $entities;
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * @param class-string<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $className
	 */
	protected function mapRowToItem(string $className, array $row): Entity {
		unset($row['DOCTRINE_ROWNUM']); // remove doctrine/dbal helper column
		return \call_user_func($className. '::fromRow', $row);
	}

	/**
	 * Deletes an item from the table
	 *
	 * @param FsCreation|FsDeletion|FsMove|FsAccessUpdate $entity the entity that should be deleted
	 * @return FsCreation|FsDeletion|FsMove|FsAccessUpdate the deleted entity
	 * @throws Exception
	 */
	public function delete(Entity $entity): Entity {
		$qb = $this->db->getQueryBuilder();

		$idType = $this->getParameterTypeForProperty($entity, 'id');

		$qb->delete($entity::$tableName)
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($entity->getId(), $idType))
			);
		$qb->executeStatement();
		return $entity;
	}
}
