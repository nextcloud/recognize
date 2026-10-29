<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Moves the face vectors from recognize_face_detections, where they were duplicated for every user
 * with access to a file, into recognize_face_embeddings, then drops the vector column
 */
final class Version013002000Date20261028094821 extends SimpleMigrationStep {
	private const FILE_BATCH_SIZE = 500;
	private const QUERY_CHUNK_SIZE = 1000;

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('recognize_face_detections') || !$schema->hasTable('recognize_face_embeddings')) {
			return;
		}
		$table = $schema->getTable('recognize_face_detections');
		if (!$table->hasColumn('face_vector') || !$table->hasColumn('embedding_id')) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id'))
			->from('recognize_face_detections')
			->where($qb->expr()->isNull('embedding_id'))
			->andWhere($qb->expr()->isNotNull('file_id'));
		$result = $qb->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();
		if ($total === 0) {
			return;
		}

		$output->info('Deduplicating ' . $total . ' face detections');
		$output->startProgress($total);

		$lastFileId = -1;
		while (true) {
			$qb = $this->db->getQueryBuilder();
			$qb->selectDistinct('file_id')
				->from('recognize_face_detections')
				->where($qb->expr()->isNull('embedding_id'))
				->andWhere($qb->expr()->gt('file_id', $qb->createNamedParameter($lastFileId, IQueryBuilder::PARAM_INT)))
				->orderBy('file_id')
				->setMaxResults(self::FILE_BATCH_SIZE);
			$result = $qb->executeQuery();
			$fileIds = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
			$result->closeCursor();
			if (count($fileIds) === 0) {
				break;
			}
			$lastFileId = max($fileIds);

			$this->db->beginTransaction();
			try {
				$output->advance($this->migrateFiles($fileIds));
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}
		}
		$output->finishProgress();
	}

	/**
	 * @param list<int> $fileIds
	 * @return int The number of migrated detections
	 * @throws \OCP\DB\Exception
	 */
	private function migrateFiles(array $fileIds): int {
		// Group the detections of these files by face; all copies of a face share file and bounding box
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'file_id', 'x', 'y', 'height', 'width')
			->from('recognize_face_detections')
			->where($qb->expr()->isNull('embedding_id'))
			->andWhere($qb->expr()->in('file_id', $qb->createNamedParameter($fileIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('id');
		$result = $qb->executeQuery();
		/** @var array<string, list<int>> $detectionIdsByFace */
		$detectionIdsByFace = [];
		$count = 0;
		while ($row = $result->fetch()) {
			$key = $this->faceKey($row);
			$detectionIdsByFace[$key][] = (int)$row['id'];
			$count++;
		}
		$result->closeCursor();

		// Reuse embeddings created by a previous, interrupted run
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'file_id', 'x', 'y', 'height', 'width')
			->from('recognize_face_embeddings')
			->where($qb->expr()->in('file_id', $qb->createNamedParameter($fileIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$result = $qb->executeQuery();
		/** @var array<string, int> $embeddingIdByFace */
		$embeddingIdByFace = [];
		while ($row = $result->fetch()) {
			$embeddingIdByFace[$this->faceKey($row)] = (int)$row['id'];
		}
		$result->closeCursor();

		// Copy one detection per face within the database, so neither the vectors nor
		// the float coordinates have to make a lossy round trip through PHP
		$missingFaces = array_diff_key($detectionIdsByFace, $embeddingIdByFace);
		$copyQuery = 'INSERT INTO `*PREFIX*recognize_face_embeddings` (`file_id`, `x`, `y`, `height`, `width`, `face_vector`) '
			. 'SELECT `file_id`, `x`, `y`, `height`, `width`, `face_vector` FROM `*PREFIX*recognize_face_detections` WHERE `id` = ?';
		foreach ($missingFaces as $key => $detectionIds) {
			$this->db->executeStatement($copyQuery, [$detectionIds[0]], [IQueryBuilder::PARAM_INT]);
			/** @psalm-suppress DeprecatedMethod The query builder cannot express INSERT ... SELECT */
			$embeddingIdByFace[$key] = $this->db->lastInsertId('*PREFIX*recognize_face_embeddings');
		}

		foreach ($detectionIdsByFace as $key => $detectionIds) {
			foreach (array_chunk($detectionIds, self::QUERY_CHUNK_SIZE) as $chunk) {
				$qb = $this->db->getQueryBuilder();
				$qb->update('recognize_face_detections')
					->set('embedding_id', $qb->createNamedParameter($embeddingIdByFace[$key], IQueryBuilder::PARAM_INT))
					->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
				$qb->executeStatement();
			}
		}

		return $count;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function faceKey(array $row): string {
		return implode('|', [(int)$row['file_id'], (float)$row['x'], (float)$row['y'], (float)$row['height'], (float)$row['width']]);
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ?ISchemaWrapper
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('recognize_face_detections')) {
			$table = $schema->getTable('recognize_face_detections');
			if ($table->hasColumn('face_vector') && $table->hasColumn('embedding_id')) {
				$table->dropColumn('face_vector');
				return $schema;
			}
		}
		return null;
	}
}
