<?php

/*
 * Copyright (c) 2020-2025 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCA\Recognize\Service\SettingsService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version011000002Date20260129094821 extends SimpleMigrationStep {

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		foreach (SettingsService::LAZY_SETTINGS as $settingsKey) {
			if ($this->appConfig->hasAppKey($settingsKey, lazy: false)) {
				$value = $this->appConfig->getAppValueString($settingsKey);
				$this->appConfig->deleteAppValue($settingsKey);
				$this->appConfig->setAppValueString($settingsKey, $value, lazy: true);
			}
		}
	}
}
