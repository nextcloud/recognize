<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Db;

use OCA\Recognize\Classifiers\TaskProcessing\ImageFaceRecognitionClassifier;
use OCA\Recognize\Service\FaceClusterAnalyzer;
use OCA\Recognize\Service\SettingsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FaceDetection>
 */
final class FaceDetectionMapper extends QBMapper {
	private IConfig $config;
	private SettingsService $settingsService;
	private FaceEmbeddingMapper $embeddings;

	public function __construct(IDBConnection $db, IConfig $config, SettingsService $settingsService, FaceEmbeddingMapper $embeddings) {
		parent::__construct($db, 'recognize_face_detections', FaceDetection::class);
		$this->db = $db;
		$this->config = $config;
		$this->settingsService = $settingsService;
		$this->embeddings = $embeddings;
	}

	/**
	 * Selects detections together with the face vector of their embedding
	 */
	private function selectWithVector(IQueryBuilder $qb): IQueryBuilder {
		return $qb->select(array_map(fn ($col) => 'd.'.$col, FaceDetection::$columns))
			->addSelect('e.face_vector')
			->from('recognize_face_detections', 'd')
			->innerJoin('d', 'recognize_face_embeddings', 'e', $qb->expr()->eq('d.embedding_id', 'e.id'));
	}

	/**
	 * Stores the vector of a detection that has no embedding yet as a new embedding
	 *
	 * @throws \OCP\DB\Exception
	 */
	private function ensureEmbedding(FaceDetection $detection): void {
		if ($detection->getEmbeddingId() !== null) {
			return;
		}
		$embedding = new FaceEmbedding();
		$embedding->setFileId($detection->getFileId());
		$embedding->setX($detection->getX());
		$embedding->setY($detection->getY());
		$embedding->setHeight($detection->getHeight());
		$embedding->setWidth($detection->getWidth());
		$embedding->setVector($detection->getVector());
		$this->embeddings->insert($embedding);
		$detection->setEmbeddingId($embedding->getId());
	}

	private function getMinDetectionSize(): float {
		if ($this->settingsService->getSetting('taskprocessing.enabled') === 'true') {
			return ImageFaceRecognitionClassifier::MIN_DETECTION_SIZE;
		}
		return FaceClusterAnalyzer::MIN_DETECTION_SIZE;
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
	 * Inserts a detection for a user, unless that user already has a detection for the same embedding.
	 * If the detection has no embedding yet, its vector is stored as a new embedding.
	 *
	 * @param FaceDetection $entity
	 * @throws \OCP\DB\Exception
	 */
	public function insert(Entity $entity): FaceDetection {
		/** @var FaceDetection $entity */
		$this->ensureEmbedding($entity);
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceDetection::$columns)
			->from('recognize_face_detections')
			->where($qb->expr()->eq('embedding_id', $qb->createPositionalParameter($entity->getEmbeddingId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createPositionalParameter($entity->getUserId(), IQueryBuilder::PARAM_STR)));
		$duplicates = $this->findEntities($qb);

		if (empty($duplicates)) {
			return parent::insert($entity);
		}

		return $duplicates[0];
	}

	/**
	 * @throws Exception
	 */
	public function insertWithoutDeduplication(Entity $entity): FaceDetection {
		/** @var FaceDetection $entity */
		$this->ensureEmbedding($entity);
		return parent::insert($entity);
	}

	/**
	 * Creates the per-user detection for an embedding
	 *
	 * @throws \OCP\DB\Exception
	 */
	public function insertForUser(FaceEmbedding $embedding, string $userId): FaceDetection {
		$detection = new FaceDetection();
		$detection->setEmbeddingId($embedding->getId());
		$detection->setFileId($embedding->getFileId());
		$detection->setX($embedding->getX());
		$detection->setY($embedding->getY());
		$detection->setHeight($embedding->getHeight());
		$detection->setWidth($embedding->getWidth());
		$detection->setUserId($userId);
		return parent::insert($detection);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function deleteAll(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')
			->executeStatement();
		$this->embeddings->deleteAll();
	}

	/**
	 * Deletes all detections of a file for all users, including the embeddings
	 *
	 * @throws \OCP\DB\Exception
	 */
	public function deleteByFileId(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
		$this->embeddings->deleteByFileId($fileId);
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
	 * @param int $clusterId
	 * @return list<\OCA\Recognize\Db\FaceDetection>
	 * @throws \OCP\DB\Exception
	 */
	public function findByClusterIdWithVector(int $clusterId) : array {
		$qb = $this->db->getQueryBuilder();
		$this->selectWithVector($qb)
			->where($qb->expr()->eq('d.cluster_id', $qb->createPositionalParameter($clusterId)));
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
	 * @return \OCA\Recognize\Db\FaceDetection[]
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
	 * Gives the passed users a detection for every face found in the file that they don't have yet
	 *
	 * @param int $fileId
	 * @param list<string> $userIds
	 * @return list<string> The users that received new detections
	 * @throws \OCP\DB\Exception
	 */
	public function addUsersToFile(int $fileId, array $userIds) : array {
		$embeddings = $this->embeddings->findByFileId($fileId);
		if (count($embeddings) === 0 || count($userIds) === 0) {
			return [];
		}
		$hasDetection = [];
		foreach ($this->findByFileId($fileId) as $detection) {
			$hasDetection[$detection->getUserId()][(int)$detection->getEmbeddingId()] = true;
		}
		$addedUsers = [];
		foreach ($userIds as $userId) {
			foreach ($embeddings as $embedding) {
				if ($hasDetection[$userId][$embedding->getId()] ?? false) {
					continue;
				}
				$this->insertForUser($embedding, $userId);
				$addedUsers[$userId] = true;
			}
		}
		return array_map('strval', array_keys($addedUsers));
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
	 * @param string $userId
	 * @return list<\OCA\Recognize\Db\FaceDetectionWithTitle>
	 * @throws \OCP\DB\Exception
	 */
	public function findByFileIdWithTitle(int $fileId, string $userId) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_merge(array_map(fn ($col) => 'd.'.$col, FaceDetection::$columns), ['c.title']))
			->from('recognize_face_detections', 'd')
			->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('d.user_id', $qb->createPositionalParameter($userId, IQueryBuilder::PARAM_STR)))
			->leftJoin('d', 'recognize_face_clusters', 'c', $qb->expr()->eq('d.cluster_id', 'c.id'));
		/** @var list<\OCA\Recognize\Db\FaceDetectionWithTitle> $entities */
		$entities = $this->findEntities($qb);
		return $entities;
	}

	/**
	 * @param list<int> $fileIds
	 * @param string $userId
	 * @return list<\OCA\Recognize\Db\FaceDetectionWithTitle>
	 * @throws \OCP\DB\Exception
	 */
	public function findByFileIdsWithTitle(array $fileIds, string $userId) : array {
		$entities = [];
		// Chunk to stay well below SQLite's bound-parameter limit
		foreach (array_chunk($fileIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select(array_merge(array_map(fn ($col) => 'd.'.$col, FaceDetection::$columns), ['c.title']))
				->from('recognize_face_detections', 'd')
				->where($qb->expr()->in('file_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->eq('d.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
				->leftJoin('d', 'recognize_face_clusters', 'c', $qb->expr()->eq('d.cluster_id', 'c.id'));
			/** @var list<\OCA\Recognize\Db\FaceDetectionWithTitle> $chunkEntities */
			$chunkEntities = $this->findEntities($qb);
			array_push($entities, ...$chunkEntities);
		}
		return $entities;
	}

	/**
	 * @return list<string>
	 * @throws \OCP\DB\Exception
	 */
	public function findUserIds() :array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('d.user_id')
			->from('recognize_face_detections', 'd');
		/** @var list<string> */
		$list = $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
		return $list;
	}

	/**
	 * @param string $userId
	 * @return \OCA\Recognize\Db\FaceDetection[]
	 * @throws \OCP\DB\Exception
	 */
	public function findUnclusteredByUserId(string $userId, int $limit = 0, float $minHeight = 0, float $minWidth = 0) : array {
		$qb = $this->db->getQueryBuilder();
		$this->selectWithVector($qb)
			->where($qb->expr()->eq('d.user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->gte('d.height', $qb->createPositionalParameter($minHeight)))
			->andWhere($qb->expr()->gte('d.width', $qb->createPositionalParameter($minWidth)))
			->andWhere($qb->expr()->isNull('d.cluster_id'));
		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}
		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @return \OCA\Recognize\Db\FaceDetection[]
	 * @throws \OCP\DB\Exception
	 */
	public function findRejectedByUserId(string $userId) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(FaceDetection::$columns)
			->from('recognize_face_detections')
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->eq('cluster_id', $qb->createPositionalParameter(-1)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $clusterId
	 * @param int $n
	 * @param int $minHeight
	 * @param int $minWidth
	 * @return FaceDetection[]
	 * @throws \OCP\DB\Exception
	 */
	public function findClusterSample(int $clusterId, int $n, int $minHeight = 0, int $minWidth = 0): array {
		$qb = $this->db->getQueryBuilder();
		$this->selectWithVector($qb)
			->where($qb->expr()->eq('d.cluster_id', $qb->createPositionalParameter($clusterId)))
			->andWhere($qb->expr()->gte('d.height', $qb->createPositionalParameter($minHeight)))
			->andWhere($qb->expr()->gte('d.width', $qb->createPositionalParameter($minWidth)))
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

	/**
	 * @param string $userId
	 * @param int $n
	 * @param float $minHeight
	 * @param float $minWidth
	 * @return FaceDetection[]
	 * @throws \OCP\DB\Exception
	 */
	public function sampleRejectedDetectionsByUserId(string $userId, int $n, float $minHeight = 0, float $minWidth = 0): array {
		$qb = $this->db->getQueryBuilder();
		$this->selectWithVector($qb)
			->where($qb->expr()->eq('d.cluster_id', $qb->createPositionalParameter(-1)))
			->andWhere($qb->expr()->eq('d.user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->gte('d.height', $qb->createPositionalParameter($minHeight)))
			->andWhere($qb->expr()->gte('d.width', $qb->createPositionalParameter($minWidth)))
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

	public function findDetectionForPreviewImageByClusterId(int $clusterId) : FaceDetection {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(fn ($col) => 'd.'.$col, FaceDetection::$columns))
			->from('recognize_face_detections', 'd')
			->where($qb->expr()->eq('d.cluster_id', $qb->createPositionalParameter($clusterId)))
			->andWhere($qb->expr()->gt($qb->createFunction('d.x - d.width * 0.5'), $qb->createFunction('0')))
			->andWhere($qb->expr()->gt($qb->createFunction('d.y - d.height * 0.5'), $qb->createFunction('0')))
			->andWhere($qb->expr()->gt($qb->createFunction('1 - (d.x + d.width) - d.width * 0.5'), $qb->createFunction('0')))
			->andWhere($qb->expr()->gt($qb->createFunction('1 - (d.y + d.height) - d.height * 0.5'), $qb->createFunction('0')))
			->groupBy('d.id')
			->addOrderBy('d.height', 'DESC')
			->addOrderBy('d.width', 'DESC')
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			$qb = $this->db->getQueryBuilder();
			$qb->select(array_map(fn ($col) => 'd.'.$col, FaceDetection::$columns))
				->from('recognize_face_detections', 'd')
				->where($qb->expr()->eq('d.cluster_id', $qb->createPositionalParameter($clusterId)))
				->groupBy('d.id')
				->addOrderBy('d.height', 'DESC')
				->addOrderBy('d.width', 'DESC')
				->setMaxResults(1);
			return $this->findEntity($qb);
		}
	}

	public function countUnclustered(): int {
		$minDetectionSize = $this->getMinDetectionSize();
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id'))
			->from('recognize_face_detections')
			->where($qb->expr()->isNull('cluster_id'))
			->andWhere($qb->expr()->gte('height', $qb->createPositionalParameter($minDetectionSize)))
			->andWhere($qb->expr()->gte('width', $qb->createPositionalParameter($minDetectionSize)));
		$result = $qb->executeQuery();
		/** @var int|string $count */
		$count = $result->fetch(\PDO::FETCH_COLUMN);
		$result->closeCursor();
		return (int) $count;
	}

	/**
	 * @return array<string>
	 * @throws \OCP\DB\Exception
	 */
	public function getUsersForUnclustered(): array {
		$minDetectionSize = $this->getMinDetectionSize();
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('user_id')
			->from('recognize_face_detections')
			->where($qb->expr()->isNull('cluster_id'))
			->andWhere($qb->expr()->gte('height', $qb->createPositionalParameter($minDetectionSize)))
			->andWhere($qb->expr()->gte('width', $qb->createPositionalParameter($minDetectionSize)));
		$result = $qb->executeQuery();
		/** @var array<string> $users */
		$users = $result->fetchAll(\PDO::FETCH_COLUMN);
		$result->closeCursor();
		return $users;
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
