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
					'notnull' => false,
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

		$query = $this->db->getQueryBuilder();
		$query->update('recognize_face_detections')
			->set('face_vector', 'vector');
		$query->executeStatement();
	}
}
