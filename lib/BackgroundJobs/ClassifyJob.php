<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\ClassifyService;
use OCA\Recognize\Service\ImagesFinderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ClassifyJob extends TimedJob {
    public const BATCH_SIZE = 100; // 100 images
    public const INTERVAL = 20 * 60; // 10 minutes

    /**
     * @var ClassifyService
     */
    private $classifier;
    /**
     * @var ImagesFinderService
     */
    private $imagesFinder;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var IRootFolder
     */
    private $rootFolder;
    /**
     * @var ITimeFactory
     */
    private $timeFactory;
    /**
     * @var IUserManager
     */
    private $userManager;

    public function __construct(
        ClassifyService $classifier, ITimeFactory $timeFactory, IRootFolder $rootFolder, LoggerInterface $logger, IUserManager $userManager, ImagesFinderService $imagesFinder
    ) {
        parent::__construct($timeFactory);

        $this->setInterval(self::INTERVAL);
        $this->classifier = $classifier;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->imagesFinder = $imagesFinder;
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
            $images = $this->imagesFinder->findImagesInFolder($this->rootFolder->getUserFolder($user));
        }while(count($images) === 0);
        $images = array_slice($images, 0, self::BATCH_SIZE);

        $this->logger->warning('Classifying photos of user '.$user);
        $this->classifier->classify($images);
    }
}
