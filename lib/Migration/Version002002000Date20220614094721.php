<?php

/*
 * Copyright (c) 2020-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version002002000Date20220614094721 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return void
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('recognize_face_detections')) {
			$table = $schema->createTable('recognize_face_detections');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('file_id', 'bigint', [
				'notnull' => false,
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
			$table->addColumn('vector', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('cluster_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id'], 'recognize_facedet_id');
			$table->addIndex(['cluster_id'], 'recognize_facedet_cluster');
			$table->addIndex(['user_id'], 'recognize_facedet_user');
		}

		if (!$schema->hasTable('recognize_face_clusters')) {
			$table = $schema->createTable('recognize_face_clusters');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => true,
				'length' => 4000,
				'default' => '',
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->setPrimaryKey(['id'], 'recognize_faceclust_id');
			$table->addIndex(['user_id'], 'recognize_faceclust_user');
		}
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return void
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
