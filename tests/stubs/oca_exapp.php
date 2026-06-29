<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AppAPI\Db;

class ExApp implements \JsonSerializable {
	public function jsonSerialize(): array {}
}

namespace OCA\AppAPI\Service;

use OCA\AppAPI\Db\ExApp;
use OCP\Http\Client\IPromise;
use OCP\Http\Client\IResponse;
use OCP\IRequest;

class ExAppService {
	public function getExApp(string $appId): ?ExApp {}
}

class AppAPIService {
	public function requestToExApp(
		ExApp $exApp,
		string $route,
		?string $userId = null,
		string $method = 'POST',
		array $params = [],
		array $options = [],
		?IRequest $request = null,
	): array|IResponse {}

	public function requestToExAppAsync(
		ExApp $exApp,
		string $route,
		?string $userId = null,
		string $method = 'POST',
		array $params = [],
		array $options = [],
		?IRequest $request = null,
	): IPromise {}
}

namespace OCA\AppAPI;

use OCA\AppAPI\Service\AppAPIService;
use OCA\AppAPI\Service\ExAppService;
use OCP\Http\Client\IPromise;
use OCP\Http\Client\IResponse;
use OCP\IRequest;

readonly class PublicFunctions {

	public function __construct(
		private ExAppService $exAppService,
		private AppAPIService $service,
	) {
	}

	/**
	 * Request to ExApp with AppAPI auth headers
	 */
	public function exAppRequest(
		string $appId,
		string $route,
		?string $userId = null,
		string $method = 'POST',
		array $params = [],
		array $options = [],
		?IRequest $request = null,
	): array|IResponse {}

	/**
	 * Request to ExApp with AppAPI auth headers and ExApp user initialization
	 *
	 * @deprecated since AppAPI 3.0.0, use `exAppRequest` instead
	 */
	public function exAppRequestWithUserInit(
		string $appId,
		string $route,
		?string $userId = null,
		string $method = 'POST',
		array $params = [],
		array $options = [],
		?IRequest $request = null,
	): array|IResponse {}

	/**
	 * Async request to ExApp with AppAPI auth headers
	 *
	 * @throws \Exception if ExApp not found
	 */
	public function asyncExAppRequest(
		string $appId,
		string $route,
		?string $userId = null,
		string $method = 'POST',
		array $params = [],
		array $options = [],
		?IRequest $request = null,
	): IPromise {}

	/**
	 * Async request to ExApp with AppAPI auth headers and ExApp user initialization
	 *
	 * @throws \Exception if ExApp not found or failed to setup ExApp user
	 *
	 * @deprecated since AppAPI 3.0.0, use `asyncExAppRequest` instead
	 */
	public function asyncExAppRequestWithUserInit(
		string $appId,
		string $route,
		?string $userId = null,
		string $method = 'POST',
		array $params = [],
		array $options = [],
		?IRequest $request = null,
	): IPromise {}

	/**
	 * Get basic ExApp info by appid
	 *
	 * @param string $appId
	 *
	 * @return array|null ExApp info (appid, version, name, enabled) or null if no ExApp found
	 */
	public function getExApp(string $appId): ?array {}
}