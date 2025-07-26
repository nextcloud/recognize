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
			$subQuery = $this->db->getQueryBuilder();
			$subQuery->selectAlias($subQuery->func()->min('id'), 'id')
				->from('recognize_face_detections')
				->groupBy('file_id', 'user_id', 'x', 'y', 'height', 'width');

			if ($this->db->getDatabaseProvider() === IDBConnection::PLATFORM_MYSQL) {
				$secondSubQuery = $this->db->getQueryBuilder();
				$secondSubQuery->select('id')->from($secondSubQuery->createFunction('(' . $subQuery->getSQL() .')'), 'x');
				$sql = $secondSubQuery->getSQL();
			} else {
				$sql = $subQuery->getSQL();
			}

			$qb = $this->db->getQueryBuilder();
			$qb->delete('recognize_face_detections')
				->where($qb->expr()->notIn('id', $qb->createFunction('(' . $sql .')')));

			$qb->executeStatement();
		} catch (\Throwable $e) {
			$output->warning('Failed to automatically remove duplicate face detections for recognize.');
			$this->logger->error('Failed to automatically remove duplicate face detections', ['exception' => $e]);
		}
	}
}
