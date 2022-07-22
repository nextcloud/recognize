<?php

namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class QueueMapper extends QBMapper {
	/**
	 * @var IDBConnection $db
	 */
	protected $db;

	public function __construct(IDBConnection $db) {
		parent::__construct($db, '', QueueFile::class);
	}

	private function getQueueTable(string $model) {
		return 'recognize_queue_'.$model;
	}

	/**
	 * @param string $model
	 * @param int $storageId
	 * @param int $n
	 * @return \OCA\Recognize\Db\QueueFile[]
	 * @throws \OCP\DB\Exception
	 */
	public function getFromQueue(string $model, int $storageId, int $rootId, int $n) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(QueueFile::$columns)
			->from($this->getQueueTable($model))
			->where($qb->expr()->eq('storage_id', $qb->createPositionalParameter($storageId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('root_id', $qb->createPositionalParameter($rootId, IQueryBuilder::PARAM_INT)))
			->setMaxResults($n);

		return $this->findEntities($qb);
	}

	/**
	 * @param string $model
	 * @param \OCA\Recognize\Db\QueueFile $file
	 * @return void
	 * @throws \OCP\DB\Exception
	 */
	public function removeFromQueue(string $model, QueueFile $file) : void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getQueueTable($model))
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($file->getId())))
			->executeStatement();
	}

	/**
	 * @param string $model
	 * @param \OCA\Recognize\Db\QueueFile $file
	 * @return \OCA\Recognize\Db\QueueFile
	 * @throws \OCP\DB\Exception
	 */
	public function insertIntoQueue(string $model, QueueFile $file) : QueueFile {
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getQueueTable($model))
			->values([
				'file_id' => $qb->createPositionalParameter($file->getFileId()),
				'storage_id' => $qb->createPositionalParameter($file->getStorageId()),
				'root_id' => $qb->createPositionalParameter($file->getRootId()),
				'update' => $qb->createPositionalParameter($file->getUpdate())
			])
			->executeStatement();
		$file->setId($qb->getLastInsertId());
		return $file;
	}
}
