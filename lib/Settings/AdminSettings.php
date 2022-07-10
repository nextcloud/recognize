<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
	public const SETTINGS = ['tensorflow.cores', 'tensorflow.gpu', 'tensorflow.purejs', 'geo.enabled', 'imagenet.enabled', 'landmarks.enabled', 'faces.enabled', 'musicnn.enabled', 'movinet.enabled', 'node_binary', 'audio.status', 'images.status', 'video.status'];

	/**
	 * @var \OCP\AppFramework\Services\IInitialState
	 */
	private IInitialState $initialState;
	/**
	 * @var \OCP\IConfig
	 */
	private IConfig $config;

	public function __construct(IInitialState $initialState, IConfig $config) {
		$this->initialState = $initialState;
		$this->config = $config;
	}


	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$settings = [];
		foreach (self::SETTINGS as $setting) {
			$settings[$setting] = $this->config->getAppValue('recognize', $setting);
		}
		$this->initialState->provideInitialState('settings', $settings);
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
