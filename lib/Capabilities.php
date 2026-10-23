<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize;

use OCA\Recognize\AppInfo\Application;
use OCP\Capabilities\ICapability;

/**
 * Tells other apps which integrations this version of recognize offers.
 */
final class Capabilities implements ICapability {
	/**
	 * @return array{recognize: array{faces: array{contactLinking: bool}}}
	 */
	public function getCapabilities(): array {
		return [
			Application::APP_ID => [
				'faces' => [
					// People can be linked to contacts, and faces can be used as contact photos
					'contactLinking' => true,
				],
			],
		];
	}
}
