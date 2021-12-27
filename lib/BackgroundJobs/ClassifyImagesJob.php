<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\ClassifyImagenetService;
use OCA\Recognize\Service\ClassifyImagesService;
use OCA\Recognize\Service\ImagesFinderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ClassifyImagesJob extends TimedJob {
    public const BATCH_SIZE = 100; // 100 images
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


    public function __construct(
        ITimeFactory $timeFactory, IRootFolder $rootFolder, LoggerInterface $logger, IUserManager $userManager, ClassifyImagesService $imageClassifier
    ) {
        parent::__construct($timeFactory);
        $this->setInterval(self::INTERVAL);
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->imageClassifier = $imageClassifier;
    }

    protected function run($argument) {
        $users = [];
        $this->userManager->callForSeenUsers(function(IUser $user) use (&$users) {
            $users[] = $user->getUID();
        });

        do {
            $user = array_pop($users);
            if (!$user) {
                $this->logger->debug('No users left, whose photos could be classified ');
                return;
            }
            try {
                $processed = $this->imageClassifier->run($user, self::BATCH_SIZE);
            }catch(\Exception $e) {
                $this->logger->warning('Classifier process errored');
                $this->logger->warning($e->getMessage());
                return;
            }
        }while(!$processed);
    }
}
