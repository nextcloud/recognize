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

final class RemoveDuplicateFaceDetections implements IRepairStep {

	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getName(): string {
		return 'Remove duplicate face detections';
	}

	public function run(IOutput $output): void {
		try {
			$selectQuery = $this->db->getQueryBuilder();
			$selectQuery
				->select('file_id', 'user_id', 'x', 'y', 'height', 'width')
				->selectAlias($selectQuery->func()->min('id'), 'min_id')
				->from('recognize_face_detections')
				->groupBy('file_id', 'user_id', 'x', 'y', 'height', 'width');

			$result = $selectQuery->executeQuery();

			$deleteQuery = $this->db->getQueryBuilder();
			$deleteQuery->delete('recognize_face_detections')
				->andWhere(
					$deleteQuery->expr()->neq('id', $deleteQuery->createParameter('min_id')),
					$deleteQuery->expr()->eq('file_id', $deleteQuery->createParameter('file_id')),
					$deleteQuery->expr()->eq('user_id', $deleteQuery->createParameter('user_id')),
					$deleteQuery->expr()->eq('x', $deleteQuery->createParameter('x')),
					$deleteQuery->expr()->eq('y', $deleteQuery->createParameter('y')),
					$deleteQuery->expr()->eq('height', $deleteQuery->createParameter('height')),
					$deleteQuery->expr()->eq('width', $deleteQuery->createParameter('width'))
				);

			while (
				/** @var array{min_id: int, file_id: int, user_id: string, x: float, y: float, height: float, width: float} $row */
				$row = $result->fetch()
			) {
				$deleteQuery->setParameter('min_id', $row['min_id'], IQueryBuilder::PARAM_INT);
				$deleteQuery->setParameter('file_id', $row['file_id'], IQueryBuilder::PARAM_INT);
				$deleteQuery->setParameter('user_id', $row['user_id']);
				$deleteQuery->setParameter('x', $row['x']);
				$deleteQuery->setParameter('y', $row['y']);
				$deleteQuery->setParameter('height', $row['height']);
				$deleteQuery->setParameter('width', $row['width']);
				$deleteQuery->executeStatement();
			}
			$result->closeCursor();
		} catch (\Throwable $e) {
			$output->warning('Failed to automatically remove duplicate face detections for recognize.');
			throw new \Exception('Failed to automatically remove duplicate face detections', previous: $e);
		}
	}
}
