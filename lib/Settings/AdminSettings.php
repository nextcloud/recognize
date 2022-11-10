<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Settings;

use OCA\Recognize\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
	private IInitialState $initialState;
	private SettingsService $settingsService;

	public function __construct(IInitialState $initialState, SettingsService $settingsService) {
		$this->initialState = $initialState;
		$this->settingsService = $settingsService;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$settings = $this->settingsService->getAll();
		$this->initialState->provideInitialState('settings', $settings);

		$modelsPath = __DIR__ . '/../../models';
		$modelsDownloaded = file_exists($modelsPath);
		$this->initialState->provideInitialState('modelsDownloaded', $modelsDownloaded);

		return new TemplateResponse('recognize', 'admin');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection(): string {
		return 'recognize';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of the admin section. The forms are arranged in ascending order of the priority values. It is required to return a value between 0 and 100.
	 */
	public function getPriority(): int {
		return 50;
	}
}
