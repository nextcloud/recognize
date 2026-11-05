<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);
namespace OCA\Recognize\Tests\AuditLog;

use OCP\Log\Audit\CriticalActionPerformedEvent;

final class AuditLogTestHelper {
	/**
	 * Servers before the operation was added to CriticalActionPerformedEvent do not keep it
	 */
	public static function hasOperation(CriticalActionPerformedEvent $event, string $operation): bool {
		return !method_exists($event, 'getOperation') || $event->getOperation() === $operation;
	}
}
