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

		if (!$schema->hasTable('recognize_files_images')) {
			$table = $schema->createTable('recognize_files_images');
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('processed_geo', 'boolean');
			$table->addColumn('processed_imagenet', 'boolean');
			$table->addColumn('processed_landmarks', 'boolean');
			$table->addColumn('processed_faces', 'boolean');
			$table->setPrimaryKey(['file_id'], 'recognize_images_id');
			$table->addIndex(['user_id'], 'recognize_images_user');
			$table->addIndex(['user_id', 'processed_imagenet'], 'recognize_imagenet_user');
			$table->addIndex(['user_id', 'processed_landmarks'], 'recognize_landmarks_user');
			$table->addIndex(['user_id', 'processed_faces'], 'recognize_faces_user');
			$table->addIndex(['user_id', 'processed_geo'], 'recognize_geo_user');
		}

		if (!$schema->hasTable('recognize_files_video')) {
			$table = $schema->createTable('recognize_files_video');
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('processed_movinet', 'boolean');
			$table->setPrimaryKey(['file_id'], 'recognize_video_id');
			$table->addIndex(['user_id'], 'recognize_video_user');
			$table->addIndex(['user_id', 'processed_movinet'], 'recognize_movinet_user');
		}

		if (!$schema->hasTable('recognize_files_audio')) {
			$table = $schema->createTable('recognize_files_audio');
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('processed_musicnn', 'boolean');
			$table->setPrimaryKey(['file_id'], 'recognize_audio_id');
			$table->addIndex(['user_id'], 'recognize_audio_user');
			$table->addIndex(['user_id', 'processed_musicnn'], 'recognize_musicnn_user');
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
