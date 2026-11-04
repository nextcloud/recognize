<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);
namespace OCA\Recognize\AuditLog;

/**
 * Operations Recognize writes to the admin audit log, in the form `app.entity.action`.
 * The values are matched by log consumers (filters, alert rules, SIEM imports),
 * so changing one is a breaking change for them. Case names may be renamed.
 */
enum Operation: string {
	case SettingChanged = 'recognize.setting.changed';

	public function description(): string {
		return match ($this) {
			self::SettingChanged => 'An admin changed a Recognize setting to a different value.',
		};
	}
}
