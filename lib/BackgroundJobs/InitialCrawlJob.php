<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\FileCrawlerService;
use OCA\Recognize\Service\Logger;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class InitialCrawlJob extends TimedJob {
	public const INTERVAL = 30 * 60; // 30 minutes

	private LoggerInterface $logger;
	private IConfig $config;
	private FileCrawlerService $fileCrawler;
	private IUserManager $userManager;

	public function __construct(
		ITimeFactory $timeFactory, Logger $logger, IConfig $config, FileCrawlerService $fileCrawler, IUserManager $userManager) {
		parent::__construct($timeFactory);

		$this->setInterval(self::INTERVAL);
		$this->logger = $logger;
		$this->config = $config;
		$this->fileCrawler = $fileCrawler;
		$this->userManager = $userManager;
	}

	protected function run($argument): void {
		$users = [];
		$this->userManager->callForSeenUsers(function (IUser $user) use (&$users) {
			$users[] = $user->getUID();
		});

		do {
			$user = array_pop($users);
			if (!$user) {
				$this->logger->debug('No users left to crawl');
				return;
			}
			if ($this->config->getUserValue($user, 'recognize', 'crawl.done', 'false') !== 'false') {
				continue;
			}
			try {
				$this->fileCrawler->crawlForUser($user);
				$this->config->setUserValue($user, 'recognize', 'crawl.done', 'true');
				return;
			} catch (\Exception $e) {
				$this->logger->warning('Crawl process errored');
				$this->logger->warning($e->getMessage());
				return;
			}
		} while (true);
	}
}
