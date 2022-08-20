<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version002003000Date20220713094721 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('recognize_queue_imagenet')) {
			$table = $schema->createTable('recognize_queue_imagenet');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('storage_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('root_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('update', 'boolean', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id'], 'recognize_imagenet_id');
			$table->addIndex(['file_id'], 'recognize_imagenet_file');
			$table->addIndex(['storage_id', 'root_id'], 'recognize_imagenet_storage');
		}

		if (!$schema->hasTable('recognize_queue_landmarks')) {
			$table = $schema->createTable('recognize_queue_landmarks');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('storage_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('root_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('update', 'boolean', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id'], 'recognize_landmarks_id');
			$table->addIndex(['file_id'], 'recognize_landmarks_file');
			$table->addIndex(['storage_id', 'root_id'], 'recognize_landmarks_storage');
		}

		if (!$schema->hasTable('recognize_queue_faces')) {
			$table = $schema->createTable('recognize_queue_faces');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('storage_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('root_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('update', 'boolean', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id'], 'recognize_faces_id');
			$table->addIndex(['file_id'], 'recognize_faces_file');
			$table->addIndex(['storage_id', 'root_id'], 'recognize_faces_storage');
		}

		if (!$schema->hasTable('recognize_queue_movinet')) {
			$table = $schema->createTable('recognize_queue_movinet');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('storage_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('root_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('update', 'boolean', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id'], 'recognize_movinet_id');
			$table->addIndex(['file_id'], 'recognize_movinet_file');
			$table->addIndex(['storage_id', 'root_id'], 'recognize_movinet_storage');
		}

		if (!$schema->hasTable('recognize_queue_musicnn')) {
			$table = $schema->createTable('recognize_queue_musicnn');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('storage_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('root_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('update', 'boolean', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id'], 'recognize_musicnn_id');
			$table->addIndex(['file_id'], 'recognize_musicnn_file');
			$table->addIndex(['storage_id', 'root_id'], 'recognize_musicnn_storage');
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
