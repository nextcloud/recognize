<?php

namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class FaceClusterMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'recognize_face_clusters', FaceCluster::class);
		$this->db = $db;
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function find(int $id): FaceCluster {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceCluster::$columns)
			->from('recognize_face_clusters')
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($id)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function findByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceCluster::$columns)
			->from('recognize_face_clusters')
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function findByDetectionId(int $detectionId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(function ($c) : string {
			return 'f.'.$c;
		}, FaceCluster::$columns))
			->from('recognize_face_clusters', 'f')
			->join('f', 'recognize_face_detections', 'd', 'f.id = d.cluster_id')
			->where($qb->expr()->eq('d.id', $qb->createPositionalParameter($detectionId)));
		return $this->findEntities($qb);
	}

	public function delete(Entity $entity): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')->where($qb->expr()->eq('cluster_id', $qb->createPositionalParameter($entity->getId())));
		$qb->executeStatement();
		return parent::delete($entity);
	}
}
