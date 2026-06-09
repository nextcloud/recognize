<?php

declare(strict_types=1);

/*
 * Copyright (c) 2024 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCP\App\IAppManager;
use OCP\Http\Client\IResponse;
use Psr\Log\LoggerInterface;

/**
 * Thin wrapper around the AppAPI PublicFunctions service.
 *
 * The recognize classifiers can optionally offload the actual node.js inference
 * to an External App (a.k.a. ExApp), which is a container that ships the same
 * classifier_*.js scripts and (optionally) GPU support. This lets users run the
 * heavy machine learning workload on a different, more powerful machine than the
 * one hosting Nextcloud, as requested in
 * https://github.com/nextcloud/recognize/issues/73
 *
 * AppAPI is an optional dependency: every method here degrades gracefully when
 * the app_api app is not installed, so recognize keeps working with the local
 * node.js backend.
 */
final class ExAppService {
	private const APP_API_APP_ID = 'app_api';
	private const APP_API_PUBLIC_FUNCTIONS = 'OCA\\AppAPI\\PublicFunctions';

	public function __construct(
		private IAppManager $appManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Whether the AppAPI app is installed and enabled, so that ExApp requests
	 * can be made at all.
	 */
	public function isAppApiAvailable(): bool {
		return $this->appManager->isEnabledForAnyone(self::APP_API_APP_ID)
			&& class_exists(self::APP_API_PUBLIC_FUNCTIONS);
	}

	/**
	 * Returns the AppAPI PublicFunctions service or null if AppAPI is unavailable.
	 *
	 * @psalm-suppress UndefinedClass AppAPI is an optional dependency
	 */
	private function getPublicFunctions(): ?object {
		if (!$this->isAppApiAvailable()) {
			return null;
		}
		try {
			/**
			 * @psalm-suppress UndefinedClass AppAPI is an optional dependency
			 * @psalm-suppress MixedReturnStatement
			 */
			return \OCP\Server::get(self::APP_API_PUBLIC_FUNCTIONS);
		} catch (\Throwable $e) {
			$this->logger->warning('Could not load AppAPI PublicFunctions', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * Returns ExApp metadata (appid, version, name, enabled) or null if not found
	 * or AppAPI is unavailable.
	 */
	public function getExApp(string $appId): ?array {
		$publicFunctions = $this->getPublicFunctions();
		if ($publicFunctions === null) {
			return null;
		}
		try {
			/**
			 * @psalm-suppress UndefinedClass AppAPI is an optional dependency
			 * @psalm-suppress MixedReturnStatement
			 * @psalm-suppress MixedMethodCall
			 */
			return $publicFunctions->getExApp($appId);
		} catch (\Throwable $e) {
			$this->logger->warning('Could not query ExApp ' . $appId, ['exception' => $e]);
			return null;
		}
	}

	/**
	 * Whether the given ExApp is deployed and enabled.
	 */
	public function isExAppEnabled(string $appId): bool {
		$exApp = $this->getExApp($appId);
		return $exApp !== null && (bool)($exApp['enabled'] ?? false);
	}

	/**
	 * Perform a request to an ExApp using AppAPI's authenticated channel.
	 *
	 * @param array $params JSON body params (ignored if $options['multipart'] is set)
	 * @param array $options Guzzle/IClient request options (multipart, timeout, headers, ...)
	 * @return array|IResponse The decoded response array or an IResponse on success
	 * @throws \RuntimeException If AppAPI is unavailable or the request fails
	 */
	public function request(string $appId, string $route, string $method = 'POST', array $params = [], array $options = []): array|IResponse {
		$publicFunctions = $this->getPublicFunctions();
		if ($publicFunctions === null) {
			throw new \RuntimeException('AppAPI is not available, cannot reach ExApp ' . $appId);
		}

		/**
		 * @psalm-suppress UndefinedClass AppAPI is an optional dependency
		 * @psalm-suppress MixedAssignment
		 * @psalm-suppress MixedMethodCall
		 */
		$response = $publicFunctions->exAppRequest($appId, $route, null, $method, $params, $options);

		if (is_array($response) && isset($response['error'])) {
			throw new \RuntimeException('ExApp request to ' . $appId . $route . ' failed: ' . (string)$response['error']);
		}

		/** @psalm-suppress MixedReturnStatement */
		return $response;
	}
}
