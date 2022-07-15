<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\ClassifyVideoService;
use OCA\Recognize\Service\Logger;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ClassifyVideoJob extends TimedJob {
	public const BATCH_SIZE = 100; // 100 files
	public const BATCH_SIZE_PUREJS = 10; // 10 files
	public const INTERVAL = 30 * 60; // 30 minutes

	private LoggerInterface $logger;
	private IUserManager $userManager;
	private ClassifyVideoService $videoClassifier;
	private IConfig $config;


	public function __construct(
		ITimeFactory $timeFactory, Logger $logger, IUserManager $userManager, ClassifyVideoService $videoClassifier, IConfig $config) {
		parent::__construct($timeFactory);

		$this->setInterval(self::INTERVAL);
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->videoClassifier = $videoClassifier;
		$this->config = $config;
	}

	protected function run($argument) {
		$users = [];
		$this->userManager->callForSeenUsers(function (IUser $user) use (&$users) {
			$users[] = $user->getUID();
		});

		$pureJS = $this->config->getAppValue('bookmarks', 'tensorflow.purejs', 'false');

		do {
			$user = array_pop($users);
			if (!$user) {
				$this->logger->debug('No users left, whose photos could be classified ');
				return;
			}
			try {
				$processed = $this->videoClassifier->run($user, $pureJS === 'false' ? self::BATCH_SIZE : self::BATCH_SIZE_PUREJS);
			} catch (\Exception $e) {
				$this->config->setAppValue('recognize', 'video.status', 'false');
				$this->logger->warning('Classifier process errored');
				$this->logger->warning($e->getMessage());
				return;
			}
			if ($processed) {
				$this->config->setAppValue('recognize', 'video.status', 'true');
			}
		} while (!$processed);
	}
}
