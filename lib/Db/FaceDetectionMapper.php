<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;

class FaceDetectionMapper extends QBMapper {
	private IConfig $config;

	public function __construct(IDBConnection $db, IConfig $config) {
		parent::__construct($db, 'recognize_face_detections', FaceDetection::class);
		$this->db = $db;
		$this->config = $config;
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

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function deleteAll(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')
			->executeStatement();
	}

	/**
	 * @param int $clusterId
	 * @return list<\OCA\Recognize\Db\FaceDetection>
	 * @throws \OCP\DB\Exception
	 */
	public function findByClusterId(int $clusterId) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceDetection::$columns)
			->from('recognize_face_detections')
			->where($qb->expr()->eq('cluster_id', $qb->createPositionalParameter($clusterId)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @return list<\OCA\Recognize\Db\FaceDetection>
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
	 * @return list<\OCA\Recognize\Db\FaceDetection>
	 */
	public function findByFileId(int $fileId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceDetection::$columns)
			->from('recognize_face_detections')
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @return list<\OCA\Recognize\Db\FaceDetection>
	 */
	public function findByFileIdAndUser(int $fileId, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceDetection::$columns)
			->from('recognize_face_detections')
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId, IQueryBuilder::PARAM_STR)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function copyDetectionsForFileFromUserToUser(int $fileId, string $fromUser, string $toUser) : void {
		$detections = $this->findByFileIdAndUser($fileId, $fromUser);
		foreach ($detections as $detection) {
			$detectionCopy = new FaceDetection();
			$detectionCopy->setX($detection->getX());
			$detectionCopy->setY($detection->getY());
			$detectionCopy->setVector($detection->getVector());
			$detectionCopy->setFileId($detection->getFileId());
			$detectionCopy->setHeight($detection->getHeight());
			$detectionCopy->setWidth($detection->getWidth());
			$detectionCopy->setUserId($toUser);
			$this->insert($detectionCopy);
		}
	}

	/**
	 * @param int $fileId
	 * @param string[] $userIds
	 * @return void
	 * @throws \OCP\DB\Exception
	 */
	public function removeDetectionsForFileFromUsersNotInList(int $fileId, array $userIds) : void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->notIn('user_id', $qb->createPositionalParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY)));
		$qb->executeStatement();
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

	/**
	 * @param int $fileId
	 * @return list<\OCA\Recognize\Db\FaceDetectionWithTitle>
	 * @throws \OCP\DB\Exception
	 */
	public function findByFileIdWithTitle(int $fileId) : array {
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
			->setMaxResults(1)
			->leftJoin('d', 'recognize_face_clusters', 'c', $qb->expr()->eq('d.cluster_id', 'c.id'))
			->where($qb->expr()->eq('d.file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('c.id', $qb->createPositionalParameter($clusterId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return list<string>
	 * @throws \OCP\DB\Exception
	 */
	public function findUserIds() :array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('d.user_id')
			->from('recognize_face_detections', 'd');
		return $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * @param string $userId
	 * @return \OCA\Recognize\Db\FaceDetection[]
	 * @throws \OCP\DB\Exception
	 */
	public function findUnclusteredByUserId(string $userId) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceDetection::$columns)
			->from('recognize_face_detections')
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->isNull('cluster_id'));
		return $this->findEntities($qb);
	}

	public function findClusterSample(int $clusterId, int $n) {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceDetection::$columns)
			->from('recognize_face_detections', 'd')
			->where($qb->expr()->eq('cluster_id', $qb->createPositionalParameter($clusterId)))
			->orderBy(
				$qb->createFunction(
					$this->config->getSystemValue('dbtype', 'sqlite') === 'mysql'
						? 'RAND()'
						: 'RANDOM()'
				)
			)
			->setMaxResults($n);
		return $this->findEntities($qb);
	}

	public function removeAllClusters(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('recognize_face_detections')
			->set('cluster_id', $qb->createPositionalParameter(null))
			->where($qb->expr()->isNotNull('cluster_id'));
		$qb->executeStatement();
	}

	protected function mapRowToEntity(array $row): Entity {
		try {
			return parent::mapRowToEntity($row);
		} catch (\Exception $e) {
			$entity = FaceDetectionWithTitle::fromRow($row);
			if ($entity->getTitle() === '') {
				$entity->setTitle((string) $entity->getClusterId());
			}
			return $entity;
		}
	}
}
