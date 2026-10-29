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
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds the recognize_face_vectors table, which stores each face vector once per file
 * instead of once per user with access to the file
 */
final class Version013002000Date20261028094721 extends SimpleMigrationStep {

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

		$changed = false;
		if (!$schema->hasTable('recognize_face_vectors')) {
			$table = $schema->createTable('recognize_face_vectors');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('x', Types::FLOAT, [
				'notnull' => false,
			]);
			$table->addColumn('y', Types::FLOAT, [
				'notnull' => false,
			]);
			$table->addColumn('height', Types::FLOAT, [
				'notnull' => false,
			]);
			$table->addColumn('width', Types::FLOAT, [
				'notnull' => false,
			]);
			$table->addColumn('face_vector', Types::TEXT, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id'], 'recognize_faceemb_id');
			$table->addIndex(['file_id'], 'recognize_faceemb_file');
			$changed = true;
		}

		if ($schema->hasTable('recognize_face_detections')) {
			$table = $schema->getTable('recognize_face_detections');
			if (!$table->hasColumn('embedding_id')) {
				$table->addColumn('embedding_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 64,
				]);
				$table->addIndex(['embedding_id'], 'recognize_facedet_embedding');
				$changed = true;
			}
		}
		return $changed ? $schema : null;
	}
}
