<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
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
 * Links face clusters to contacts and caches the face embeddings of contact photos.
 */
final class Version013002000Date20261020094821 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ?ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		if ($schema->hasTable('recognize_face_clusters')) {
			$table = $schema->getTable('recognize_face_clusters');
			if (!$table->hasColumn('contact_uid')) {
				$table->addColumn('contact_uid', Types::STRING, [
					'notnull' => false,
					'length' => 255,
				]);
				$table->addColumn('contact_address_book', Types::STRING, [
					'notnull' => false,
					'length' => 255,
				]);
				$table->addColumn('contact_link_source', Types::STRING, [
					'notnull' => false,
					'length' => 16,
				]);
				$table->addIndex(['user_id', 'contact_address_book', 'contact_uid'], 'recognize_faceclust_contact');
				$changed = true;
			}
		}

		if (!$schema->hasTable('recognize_contact_faces')) {
			$table = $schema->createTable('recognize_contact_faces');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('address_book', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('contact_uid', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('photo_hash', Types::STRING, [
				'notnull' => true,
				'length' => 40,
			]);
			$table->addColumn('face_vector', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('face_count', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->setPrimaryKey(['id'], 'recognize_contactface_id');
			$table->addUniqueIndex(['user_id', 'address_book', 'contact_uid'], 'recognize_contactface_contact');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
