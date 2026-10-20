<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\ContactLinkService;
use OCA\Recognize\Service\Logger;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

/**
 * Links the face clusters of one user to their contacts.
 *
 * Queued by {@see ScheduleLinkContactsJob} for every user, and by
 * {@see ClusterFacesJob} when the clusters of a user changed.
 */
final class LinkContactsJob extends QueuedJob {
	private LoggerInterface $logger;
	private ContactLinkService $contactLinkService;

	public function __construct(ITimeFactory $time, Logger $logger, ContactLinkService $contactLinkService) {
		parent::__construct($time);
		$this->logger = $logger;
		$this->contactLinkService = $contactLinkService;
	}

	/**
	 * @param array{userId: string} $argument
	 */
	protected function run($argument): void {
		$userId = $argument['userId'];
		if (!$this->contactLinkService->isAutoLinkingAvailable()) {
			return;
		}
		try {
			$this->contactLinkService->linkContactsForUser($userId);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to link face clusters of user ' . $userId . ' to contacts', ['exception' => $e]);
		}
	}
}
