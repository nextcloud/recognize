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
	case ClassificationsReset = 'recognize.classifications.reset';
	case FaceClustersDeletedAll = 'recognize.face_clusters.deleted_all';
	case FaceDetectionsDeletedAll = 'recognize.face_detections.deleted_all';
	case EmptyTagsDeleted = 'recognize.empty_tags.deleted';
	case LegacyTagsDeleted = 'recognize.legacy_tags.deleted';
	case QueuesCleared = 'recognize.queues.cleared';
	case RecrawlScheduled = 'recognize.recrawl.scheduled';

	public function description(): string {
		return match ($this) {
			self::SettingChanged => 'An admin changed a Recognize setting to a different value.',
			self::ClassificationsReset => 'Recognize classification tags were removed from all files of all users.',
			self::FaceClustersDeletedAll => 'All face clusters (persons) of all users were deleted.',
			self::FaceDetectionsDeletedAll => 'All face detections of all users were deleted.',
			self::EmptyTagsDeleted => 'System tags that were assigned to no file were deleted, including tags not created by Recognize.',
			self::LegacyTagsDeleted => 'System tags created by old Recognize versions were deleted.',
			self::QueuesCleared => 'All Recognize queues and scheduled background jobs were cleared.',
			self::RecrawlScheduled => 'All Recognize queues and background jobs were cleared and a new crawl of all files was scheduled.',
		};
	}
}
