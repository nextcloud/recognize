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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version011000001Date20251215094821 extends SimpleMigrationStep {

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
		if (!$schema->hasTable('recognize_fs_access_updates')) {
			$table = $schema->createTable('recognize_fs_access_updates');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true]);
			$table->addColumn('storage_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('root_id', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'recognize_fs_au_pk');
			$table->addUniqueIndex(['storage_id', 'root_id'], 'recognize_fs_au_uniq');
			$changed = true;
		}
		if (!$schema->hasTable('recognize_fs_creations')) {
			$table = $schema->createTable('recognize_fs_creations');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true]);
			$table->addColumn('storage_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('root_id', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'recognize_fs_cr_pk');
			$table->addUniqueIndex(['storage_id', 'root_id'], 'recognize_fs_cr_unique');
			$changed = true;
		}
		if (!$schema->hasTable('recognize_fs_deletions')) {
			$table = $schema->createTable('recognize_fs_deletions');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true]);
			$table->addColumn('storage_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('node_id', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'recognize_fs_del_pk');
			$table->addUniqueIndex(['storage_id', 'node_id'], 'recognize_fs_del_uniq');
			$changed = true;
		}
		if (!$schema->hasTable('recognize_fs_moves')) {
			$table = $schema->createTable('recognize_fs_moves');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true]);
			$table->addColumn('node_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('owner', Types::TEXT, ['notnull' => true]);
			$table->addColumn('added_users', Types::TEXT, ['notnull' => true]);
			$table->addColumn('target_users', Types::TEXT, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'recognize_fs_mov_pk');
			$table->addUniqueIndex(['node_id'], 'recognize_fs_mov_uniq');
			$changed = true;
		}
		return $changed ? $schema : null;
	}
}
