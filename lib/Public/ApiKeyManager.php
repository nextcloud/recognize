<?php

/*
 * Copyright (c) 2025 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Public;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Security\ICrypto;

/**
 * @api
 */
class ApiKeyManager {

	public function __construct(
		private ICrypto $crypto,
		private ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @throws \JsonException
	 */
	public function generateApiKey(): string {
		return $this->crypto->encrypt(json_encode(['type' => 'recognize-api-key', 'version' => 1, 'timestamp' => $this->timeFactory->now()->getTimestamp()], JSON_THROW_ON_ERROR));
	}
}
