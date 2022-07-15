<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\ClassifyAudioService;
use OCA\Recognize\Service\Logger;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ClassifyAudioJob extends TimedJob {
	public const BATCH_SIZE = 100; // 100 files
	public const BATCH_SIZE_PUREJS = 10; // 10 files
	public const INTERVAL = 30 * 60; // 30 minutes

	private LoggerInterface $logger;
	private IUserManager $userManager;
	private ClassifyAudioService $audioClassifier;
	private IConfig $config;


	public function __construct(
		ITimeFactory $timeFactory, Logger $logger, IUserManager $userManager, ClassifyAudioService $audioClassifier, IConfig $config) {
		parent::__construct($timeFactory);

		$this->setInterval(self::INTERVAL);
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->audioClassifier = $audioClassifier;
		$this->config = $config;
	}

	protected function run($argument): void {
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
				$processed = $this->audioClassifier->run($user, $pureJS === 'false' ? self::BATCH_SIZE : self::BATCH_SIZE_PUREJS);
			} catch (\Exception $e) {
				$this->config->setAppValue('recognize', 'audio.status', 'false');
				$this->logger->warning('Classifier process errored');
				$this->logger->warning($e->getMessage());
				return;
			}
			if ($processed) {
				$this->config->setAppValue('recognize', 'audio.status', 'true');
			}
		} while (!$processed);
	}
}
