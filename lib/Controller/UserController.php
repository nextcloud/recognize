<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Controller;

use OCA\Recognize\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class UserController extends Controller {
	private SettingsService $settingsService;

	public function __construct(string $appName, IRequest $request, SettingsService $settingsService) {
		parent::__construct($appName, $request);
		$this->settingsService = $settingsService;
	}

	/**
	 * @NoAdminRequired
	 * A duplicate of the same function in AdminController.php
	 * @return JSONResponse
	 */
	public function platform(): JSONResponse {
		try {
			exec('lscpu --json' . ' 2>&1', $output, $returnCode);
		} catch (\Throwable $e) {
			return new JSONResponse(['platform' => null]);
		}

		if ($returnCode !== 0) {
			return new JSONResponse(['platform' => null]);
		}

		$lscpu = \json_decode(trim(implode("\n", $output)), true);
		return new JSONResponse(['platform' => $lscpu['lscpu'][0]['data']]);
	}

	/**
	 * @NoAdminRequired
	 * @return JSONResponse
	 */
	public function tensorflowPurejs(): JSONResponse {
		return new JSONResponse(['tensorflowPurejs' => \json_decode($this->settingsService->getSetting('tensorflow.purejs'))]);
	}

	/**
	 * @NoAdminRequired
	 * @return JSONResponse
	 */
	public function setSetting(string $setting, $value): JSONResponse {
		try {
			$this->settingsService->setUserSetting($setting, $value);
			return new JSONResponse([], Http::STATUS_OK);
		} catch (\OCA\Recognize\Exception\Exception $e) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @NoAdminRequired
	 * @return JSONResponse
	 */
	public function getSetting(string $setting):JSONResponse {
		return new JSONResponse(['value' => $this->settingsService->getUserSetting($setting)]);
	}
}
