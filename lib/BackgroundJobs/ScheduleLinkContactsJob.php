<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Service\ContactLinkService;
use OCA\Recognize\Service\Logger;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Queues a {@see LinkContactsJob} for every user with face clusters, so
 * contacts that got a photo since the last run are linked as well.
 */
final class ScheduleLinkContactsJob extends TimedJob {
	public const INTERVAL = 24 * 60 * 60;

	private LoggerInterface $logger;
	private IJobList $jobList;
	private FaceClusterMapper $clusterMapper;
	private ContactLinkService $contactLinkService;

	public function __construct(ITimeFactory $time, Logger $logger, IJobList $jobList, FaceClusterMapper $clusterMapper, ContactLinkService $contactLinkService) {
		parent::__construct($time);
		$this->logger = $logger;
		$this->jobList = $jobList;
		$this->clusterMapper = $clusterMapper;
		$this->contactLinkService = $contactLinkService;
		$this->setInterval(self::INTERVAL);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	/**
	 * @param array $argument
	 */
	protected function run($argument): void {
		if (!$this->contactLinkService->isAutoLinkingAvailable()) {
			return;
		}
		try {
			$userIds = $this->clusterMapper->findUserIds();
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to list users with face clusters', ['exception' => $e]);
			return;
		}
		foreach ($userIds as $userId) {
			$this->jobList->add(LinkContactsJob::class, ['userId' => $userId]);
		}
	}
}
