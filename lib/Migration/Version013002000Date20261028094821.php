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
 * with access to a file, into recognize_face_vectors, then drops the vector column
 */
final class Version013002000Date20261028094821 extends SimpleMigrationStep {
	private const FILE_BATCH_SIZE = 500;
	private const QUERY_CHUNK_SIZE = 1000;
	// Coordinates are fractions of the image size, so this is far below the size of a pixel
	private const COORDINATE_TOLERANCE = 1e-6;

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('recognize_face_detections') || !$schema->hasTable('recognize_face_vectors')) {
			return;
		}
		$table = $schema->getTable('recognize_face_detections');
		if (!$table->hasColumn('face_vector') || !$table->hasColumn('embedding_id')) {
			return;
		}

		// Detections without a vector can't be clustered and can't be copied into the
		// recognize_face_vectors table, where the vector is mandatory
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')
			->where($qb->expr()->isNull('face_vector'))
			->andWhere($qb->expr()->isNull('embedding_id'));
		$deleted = $qb->executeStatement();
		if ($deleted > 0) {
			$output->info('Removed ' . $deleted . ' face detections without a face vector');
		}

		// Detections without a file can't be shown or linked to an embedding, which belongs to a file,
		// so they would lose their vector when the column is dropped and linger as unusable rows
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')
			->where($qb->expr()->isNull('file_id'))
			->andWhere($qb->expr()->isNull('embedding_id'));
		$deleted = $qb->executeStatement();
		if ($deleted > 0) {
			$output->info('Removed ' . $deleted . ' face detections without a file');
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id'))
			->from('recognize_face_detections')
			->where($qb->expr()->isNull('embedding_id'));
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
	 * @throws \RuntimeException If a face could not be linked to its embedding; this aborts the migration
	 *                           before the face_vector column is dropped, so no vectors are lost
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
		/** @var array<string, array{face: array{file_id: int, x: float, y: float, height: float, width: float}, detectionIds: list<int>}> $faces */
		$faces = [];
		$count = 0;
		while ($row = $result->fetch()) {
			$face = $this->normalizeFace($row);
			$key = implode('|', $face);
			$faces[$key] ??= ['face' => $face, 'detectionIds' => []];
			$faces[$key]['detectionIds'][] = (int)$row['id'];
			$count++;
		}
		$result->closeCursor();

		// Reuse embeddings created by a previous, interrupted run
		$embeddingsByFile = $this->findEmbeddings($fileIds);

		// Copy one detection per face within the database, so neither the vectors nor
		// the float coordinates have to make a lossy round trip through PHP
		$copyQuery = 'INSERT INTO `*PREFIX*recognize_face_vectors` (`file_id`, `x`, `y`, `height`, `width`, `face_vector`) '
			. 'SELECT `file_id`, `x`, `y`, `height`, `width`, `face_vector` FROM `*PREFIX*recognize_face_detections` WHERE `id` = ?';
		$copied = false;
		foreach ($faces as $face) {
			if ($this->findMatchingEmbeddingId($face['face'], $embeddingsByFile) === null) {
				$this->db->executeStatement($copyQuery, [$face['detectionIds'][0]], [IQueryBuilder::PARAM_INT]);
				$copied = true;
				// Placeholder until the ids are read back below, so a face within the tolerance isn't copied twice
				$embeddingsByFile[$face['face']['file_id']][] = ['id' => 0, 'face' => $face['face']];
			}
		}
		// Read the ids back instead of using lastInsertId(), which fails on Oracle for table
		// names longer than the sequence names Doctrine generates
		if ($copied) {
			$embeddingsByFile = $this->findEmbeddings($fileIds);
		}

		foreach ($faces as $face) {
			$embeddingId = $this->findMatchingEmbeddingId($face['face'], $embeddingsByFile);
			if ($embeddingId === null) {
				throw new \RuntimeException('Could not find the copied face embedding for file ' . $face['face']['file_id'] . ' (bounding box ' . implode(', ', array_slice($face['face'], 1)) . ')');
			}
			foreach (array_chunk($face['detectionIds'], self::QUERY_CHUNK_SIZE) as $chunk) {
				$qb = $this->db->getQueryBuilder();
				$qb->update('recognize_face_detections')
					->set('embedding_id', $qb->createNamedParameter($embeddingId, IQueryBuilder::PARAM_INT))
					->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
				$qb->executeStatement();
			}
		}

		return $count;
	}

	/**
	 * @param list<int> $fileIds
	 * @return array<int, list<array{id: int, face: array{file_id: int, x: float, y: float, height: float, width: float}}>> The embeddings by file id
	 * @throws \OCP\DB\Exception
	 */
	private function findEmbeddings(array $fileIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'file_id', 'x', 'y', 'height', 'width')
			->from('recognize_face_vectors')
			->where($qb->expr()->in('file_id', $qb->createNamedParameter($fileIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$result = $qb->executeQuery();
		$embeddingsByFile = [];
		while ($row = $result->fetch()) {
			$face = $this->normalizeFace($row);
			$embeddingsByFile[$face['file_id']][] = ['id' => (int)$row['id'], 'face' => $face];
		}
		$result->closeCursor();
		return $embeddingsByFile;
	}

	/**
	 * Compares the coordinates with a tolerance instead of exactly, as the copied floats
	 * are not guaranteed to be read back with the exact same representation on all databases
	 *
	 * @param array{file_id: int, x: float, y: float, height: float, width: float} $face
	 * @param array<int, list<array{id: int, face: array{file_id: int, x: float, y: float, height: float, width: float}}>> $embeddingsByFile
	 */
	private function findMatchingEmbeddingId(array $face, array $embeddingsByFile): ?int {
		foreach ($embeddingsByFile[$face['file_id']] ?? [] as $embedding) {
			if (abs($embedding['face']['x'] - $face['x']) < self::COORDINATE_TOLERANCE
				&& abs($embedding['face']['y'] - $face['y']) < self::COORDINATE_TOLERANCE
				&& abs($embedding['face']['height'] - $face['height']) < self::COORDINATE_TOLERANCE
				&& abs($embedding['face']['width'] - $face['width']) < self::COORDINATE_TOLERANCE) {
				return $embedding['id'];
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array{file_id: int, x: float, y: float, height: float, width: float}
	 */
	private function normalizeFace(array $row): array {
		return [
			'file_id' => (int)$row['file_id'],
			'x' => (float)$row['x'],
			'y' => (float)$row['y'],
			'height' => (float)$row['height'],
			'width' => (float)$row['width'],
		];
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
