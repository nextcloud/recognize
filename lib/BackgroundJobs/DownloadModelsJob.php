<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\DownloadModelsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

/**
 * Downloads the machine learning models in the background so that administrators
 * can trigger the download from the admin settings without shell access.
 */
final class DownloadModelsJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private DownloadModelsService $downloader,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param mixed $argument unused, the job takes no arguments
	 */
	protected function run($argument): void {
		try {
			$this->downloader->download();
		} catch (\Throwable $e) {
			// Keep the job from failing cron; the admin settings show that the models are still missing
			$this->logger->error('Failed to download the machine learning models', ['exception' => $e]);
		}
	}
}
