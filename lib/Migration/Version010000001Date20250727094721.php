<?php

/*
 * Copyright (c) 2020-2025 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version010000001Date20250727094721 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $db,
	) {
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
			if (!$table->hasColumn('face_vector')) {
				$table->addColumn('face_vector', Types::TEXT, [
					'notnull' => true,
				]);
				return $schema;
			}
		}
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('recognize_face_detections')) {
			return;
		}

		$table = $schema->getTable('recognize_face_detections');
		$copyData = $table->hasColumn('face_vector') && $table->hasColumn('vector');
		if (!$copyData) {
			return;
		}

		$selectQuery = $this->db->getQueryBuilder();
		$selectQuery->select('id', 'vector')
			->from('recognize_face_detections');
		$updateQuery = $this->db->getQueryBuilder();
		$updateQuery->update('recognize_face_detections')
			->set('face_vector', $updateQuery->createParameter('face_vector'))
			->where($updateQuery->expr()->eq('id', $updateQuery->createParameter('id')));
		$result = $selectQuery->executeQuery();
		while ($row = $result->fetch()) {
			$updateQuery->setParameter('id', $row['id']);
			$updateQuery->setParameter('face_vector', $row['vector']);
			$updateQuery->executeStatement();
		}
		$result->closeCursor();
	}
}
