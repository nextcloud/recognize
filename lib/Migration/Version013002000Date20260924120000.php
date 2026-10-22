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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Drops the move payload columns: users with access are resolved when a move is processed.
 */
final class Version013002000Date20260924120000 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('recognize_fs_moves')) {
			return null;
		}

		$changed = false;
		$table = $schema->getTable('recognize_fs_moves');
		foreach (['owner', 'added_users', 'target_users'] as $column) {
			if ($table->hasColumn($column)) {
				$table->dropColumn($column);
				$changed = true;
			}
		}
		return $changed ? $schema : null;
	}
}
