<?php

namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class FaceDetectionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'recognize_face_detections', FaceDetection::class);
		$this->db = $db;
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function find(int $id): FaceDetection {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select(FaceDetection::$columns)
			->from('recognize_face_detections')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	public function findByClusterId(int $clusterId) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceDetection::$columns)
			->from('recognize_face_detections')
			->where($qb->expr()->eq('cluster_id', $qb->createPositionalParameter($clusterId)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function findByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceDetection::$columns)
			->from('recognize_face_detections')
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function findByFileId(int $fileId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceDetection::$columns)
			->from('recognize_face_detections')
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * @param \OCA\Recognize\Db\FaceDetection $faceDetection
	 * @param \OCA\Recognize\Db\FaceCluster $faceCluster
	 * @return void
	 * @throws \OCP\DB\Exception
	 */
	public function assocWithCluster(FaceDetection $faceDetection, FaceCluster $faceCluster) {
		$faceDetection->setClusterId($faceCluster->getId());
		$this->update($faceDetection);
	}

	public function findByFileIdWithTitle(int $fileId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_merge(array_map(fn ($col) => 'd.'.$col, FaceDetection::$columns), ['c.title']))
			->from('recognize_face_detections', 'd')
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)))
			->leftJoin('d', 'recognize_face_clusters', 'c', $qb->expr()->eq('d.cluster_id', 'c.id'));
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function findByFileIdAndClusterId(int $fileId, int $clusterId) : FaceDetection {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(fn ($c) => 'd.'.$c, FaceDetection::$columns))
			->from('recognize_face_detections', 'd')
			->leftJoin('d', 'recognize_face_clusters', 'c', $qb->expr()->eq('d.cluster_id', 'c.id'))
		->where($qb->expr()->eq('d.file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)))
		->andWhere($qb->expr()->eq('c.id', $qb->createPositionalParameter($clusterId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	protected function mapRowToEntity(array $row): Entity {
		try {
			return parent::mapRowToEntity($row);
		} catch (\Exception $e) {
			$entity = FaceDetectionWithTitle::fromRow($row);
			if ($entity->getTitle() === '') {
				$entity->setTitle($entity->getClusterId());
			}
			return $entity;
		}
	}
}
