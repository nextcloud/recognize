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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version010000001Date20250727094821 extends SimpleMigrationStep {

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
		if ($schema->hasTable('recognize_face_detections')) {
			$table = $schema->getTable('recognize_face_detections');
			if ($table->hasColumn('vector')) {
				$table->dropColumn('vector');
				$changed = true;
			}
			if ($table->hasColumn('face_vector')) {
				$table->modifyColumn('face_vector', [
					'notnull' => true,
				]);
				$changed = true;
			}
		}
		return $changed ? $schema : null;
	}
}
