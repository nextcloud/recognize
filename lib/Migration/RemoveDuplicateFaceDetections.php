<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2025, Marcel Klehr <mklehr@gmx.net>
 *
 * @author Marcel Klehr <mklehr@gmx.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Recognize\Migration;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Removes face detections that describe the same face in the same file for the same user.
 *
 * Processing a file more than once (e.g. after a recrawl) yields a second set of detections
 * whose bounding boxes differ only slightly from the first one, so detections are considered
 * duplicates when their bounding boxes overlap almost completely, not only when they are identical.
 */
final class RemoveDuplicateFaceDetections implements IRepairStep {
	/**
	 * Minimum intersection over union of two bounding boxes to consider them the same face.
	 * Two different faces in the same image practically never overlap this much.
	 */
	public const MIN_IOU = 0.9;

	private const DELETE_CHUNK_SIZE = 1000;

	/**
	 * Approximate number of detections loaded into memory at once
	 */
	private int $batchSize = 10000;

	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @internal Only meant for tests
	 */
	public function setBatchSize(int $batchSize): void {
		$this->batchSize = max(1, $batchSize);
	}

	public function getName(): string {
		return 'Remove duplicate face detections';
	}

	public function run(IOutput $output): void {
		try {
			$removed = 0;
			$lastFileId = -1;
			do {
				$upperFileId = $this->findBatchUpperBound($lastFileId);
				$idsToDelete = $this->findDuplicateIds($lastFileId, $upperFileId);
				foreach (array_chunk($idsToDelete, self::DELETE_CHUNK_SIZE) as $chunk) {
					$deleteQuery = $this->db->getQueryBuilder();
					$deleteQuery->delete('recognize_face_detections')
						->where($deleteQuery->expr()->in('id', $deleteQuery->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
					$deleteQuery->executeStatement();
				}
				$removed += count($idsToDelete);
				$lastFileId = $upperFileId;
			} while ($upperFileId !== null);
			if ($removed > 0) {
				$output->info('Removed ' . $removed . ' duplicate face detections');
				$this->logger->info('Removed ' . $removed . ' duplicate face detections');
			}
		} catch (\Throwable $e) {
			$output->warning('Failed to automatically remove duplicate face detections for recognize.');
			throw new \Exception('Failed to automatically remove duplicate face detections', previous: $e);
		}
	}

	/**
	 * Returns the file id that ends the batch of roughly batchSize detections starting after $lastFileId,
	 * or null if the remaining detections fit into one batch. Uses the index on file_id.
	 *
	 * @throws \OCP\DB\Exception
	 */
	private function findBatchUpperBound(int $lastFileId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('file_id')
			->from('recognize_face_detections')
			->where($qb->expr()->gt('file_id', $qb->createNamedParameter($lastFileId, IQueryBuilder::PARAM_INT)))
			->orderBy('file_id')
			->setFirstResult($this->batchSize)
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$fileId = $result->fetchOne();
		$result->closeCursor();
		return $fileId === false ? null : (int)$fileId;
	}

	/**
	 * Finds duplicates among the detections with $lastFileId < file_id <= $upperFileId (no upper limit if null).
	 * Whole files are always contained in one batch, so all detections of a (file, user) group are compared.
	 *
	 * @return list<int>
	 * @throws \OCP\DB\Exception
	 */
	private function findDuplicateIds(int $lastFileId, ?int $upperFileId): array {
		$selectQuery = $this->db->getQueryBuilder();
		$selectQuery
			->select('id', 'file_id', 'user_id', 'x', 'y', 'height', 'width', 'cluster_id')
			->from('recognize_face_detections')
			->where($selectQuery->expr()->gt('file_id', $selectQuery->createNamedParameter($lastFileId, IQueryBuilder::PARAM_INT)));
		if ($upperFileId !== null) {
			$selectQuery->andWhere($selectQuery->expr()->lte('file_id', $selectQuery->createNamedParameter($upperFileId, IQueryBuilder::PARAM_INT)));
		}
		$result = $selectQuery->executeQuery();

		// Group in PHP instead of relying on the database's sort order, which depends on the collation of user_id
		/** @var array<string, list<array{id: int, x: float, y: float, width: float, height: float, priority: int}>> $groups */
		$groups = [];
		while (
			/** @var array{id: int|string, file_id: int|string, user_id: string, x: float|string, y: float|string, height: float|string, width: float|string, cluster_id: int|string|null}|false $row */
			$row = $result->fetch()
		) {
			$groups[$row['file_id'] . '/' . $row['user_id']][] = [
				'id' => (int)$row['id'],
				'x' => (float)$row['x'],
				'y' => (float)$row['y'],
				'width' => (float)$row['width'],
				'height' => (float)$row['height'],
				'priority' => self::getPriority($row['cluster_id']),
			];
		}
		$result->closeCursor();

		$idsToDelete = [];
		foreach ($groups as $group) {
			array_push($idsToDelete, ...self::findDuplicatesInGroup($group));
		}
		return $idsToDelete;
	}

	/**
	 * Ranks which detection to keep among duplicates: one assigned to a cluster (the user's person assignment),
	 * then a rejected one (cluster_id = -1, so the user's rejection is retained), then an unclustered one.
	 */
	private static function getPriority(int|string|null $clusterId): int {
		if ($clusterId === null) {
			return 0;
		}
		return (int)$clusterId === -1 ? 1 : 2;
	}

	/**
	 * Given all detections of one user in one file, returns the ids of those that duplicate another one.
	 * Detections that are assigned to a cluster are preferred, then rejected ones, then older ones.
	 *
	 * @param list<array{id: int, x: float, y: float, width: float, height: float, priority: int}> $detections
	 * @return list<int>
	 */
	private static function findDuplicatesInGroup(array $detections): array {
		if (count($detections) < 2) {
			return [];
		}
		usort($detections, static fn (array $a, array $b): int => [$b['priority'], $a['id']] <=> [$a['priority'], $b['id']]);
		$kept = [];
		$duplicates = [];
		foreach ($detections as $detection) {
			foreach ($kept as $keptDetection) {
				if (self::isSameFace($detection, $keptDetection)) {
					$duplicates[] = $detection['id'];
					continue 2;
				}
			}
			$kept[] = $detection;
		}
		return $duplicates;
	}

	/**
	 * @param array{x: float, y: float, width: float, height: float, id?: int, priority?: int} $a
	 * @param array{x: float, y: float, width: float, height: float, id?: int, priority?: int} $b
	 */
	public static function isSameFace(array $a, array $b): bool {
		$intersectionWidth = min($a['x'] + $a['width'], $b['x'] + $b['width']) - max($a['x'], $b['x']);
		$intersectionHeight = min($a['y'] + $a['height'], $b['y'] + $b['height']) - max($a['y'], $b['y']);
		$intersection = max(0.0, $intersectionWidth) * max(0.0, $intersectionHeight);
		$union = $a['width'] * $a['height'] + $b['width'] * $b['height'] - $intersection;
		if ($union <= 0.0) {
			// Degenerate boxes without area: only identical ones are duplicates
			return abs($a['x'] - $b['x']) < 1e-9 && abs($a['y'] - $b['y']) < 1e-9
				&& abs($a['width'] - $b['width']) < 1e-9 && abs($a['height'] - $b['height']) < 1e-9;
		}
		return $intersection / $union >= self::MIN_IOU;
	}
}
