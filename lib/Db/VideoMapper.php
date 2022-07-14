<?php

namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class VideoMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'recognize_files_video', Video::class);
		$this->db = $db;
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function find(int $id): Video {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select(Video::$columns)
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 * @returns Video[]
	 */
	public function findByFileId(int $fileId) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Video::$columns)
			->from($this->tableName)
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @returns Video[]
	 */
	public function findByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Video::$columns)
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @returns Video[]
	 */
	public function findUnprocessedByUserId(string $userId, string $modelName): array {
		$column = 'processed_'.$modelName;
		if (!in_array($column, Video::$columns)) {
			throw new \Exception('No video model with name '.$modelName.' exists');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select(Image::$columns)
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->eq($column, $qb->createPositionalParameter(false, IQueryBuilder::PARAM_BOOL)));
		return $this->findEntities($qb);
	}
}
