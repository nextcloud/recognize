<?php

/*
 * Copyright (c) 2024 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

final class MaintenanceJob extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private LoggerInterface $logger,
		private IJobList $jobList,
		private FaceDetectionMapper $faceDetectionMapper,
	) {
		parent::__construct($time);
		$this->setInterval(60 * 60 * 12);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	/**
	 * @param mixed $argument
	 * @return void
	 */
	protected function run($argument) {
		// Trigger clustering in case it's stuck
		try {
			$users = $this->faceDetectionMapper->getUsersForUnclustered();
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
		foreach ($users as $userId) {
			$this->jobList->add(ClusterFacesJob::class, ['userId' => $userId]);
		}
	}
}
