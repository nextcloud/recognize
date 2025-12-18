<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require_once __DIR__ . '/../../../tests/bootstrap.php';

use OCA\Recognize\AppInfo\Application;
use OCP\App\IAppManager;

\OCP\Server::get(IAppManager::class)->loadApp(Application::APP_ID);

// Make sure hooks are registered:
\OCP\Server::get(\OC\Files\Node\HookConnector::class)->viewToNode();
// Do not clear hooks, we need them for our tests!
// OC_Hook::clear();
