<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\ClassifyImagesService;
use OCA\Recognize\Service\Logger;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ClassifyImagesJob extends TimedJob {
	public const BATCH_SIZE = 100; // 100 images
	public const BATCH_SIZE_PUREJS = 25; // 25 images
	public const INTERVAL = 30 * 60; // 30 minutes

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var ITimeFactory
	 */
	private $timeFactory;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var \OCA\Recognize\Service\ClassifyImagesService
	 */
	private $imageClassifier;
    /**
     * @var \OCP\IConfig
     */
    private $config;


    public function __construct(
        ITimeFactory $timeFactory, Logger $logger, IUserManager $userManager, ClassifyImagesService $imageClassifier, IConfig $config
    ) {
		parent::__construct($timeFactory);
		$this->setInterval(self::INTERVAL);
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->imageClassifier = $imageClassifier;
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
				$processed = $this->imageClassifier->run($user, $pureJS === 'false' ? self::BATCH_SIZE : self::BATCH_SIZE_PUREJS);
			} catch (\Exception $e) {
				$this->logger->warning('Classifier process errored');
				$this->logger->warning($e->getMessage());
				return;
			}
		} while (!$processed);
	}
}
