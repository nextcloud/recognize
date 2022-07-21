<?php

namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AudioMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'recognize_files_audio', Audio::class);
		$this->db = $db;
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function find(int $id): Audio {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select(Audio::$columns)
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 * @returns \OCA\Recognize\Db\Audio[]
	 */
	public function findByFileId(int $fileId) : Audio {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Audio::$columns)
			->from($this->tableName)
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @returns \OCA\Recognize\Db\Audio[]
	 */
	public function findByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Audio::$columns)
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @returns \OCA\Recognize\Db\Audio[]
	 */
	public function findUnprocessedByUserId(string $userId, string $modelName): array {
		$column = 'processed_'.$modelName;
		if (!in_array($column, Audio::$columns)) {
			throw new \Exception('No audio model with name '.$modelName.' exists');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select(Audio::$columns)
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->eq($column, $qb->createPositionalParameter(false, IQueryBuilder::PARAM_BOOL)));
		return $this->findEntities($qb);
	}
}
