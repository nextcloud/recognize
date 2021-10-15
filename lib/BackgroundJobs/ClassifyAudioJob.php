<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\AudioFinderService;
use OCA\Recognize\Service\ClassifyMusicService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ClassifyAudioJob extends TimedJob {
    public const BATCH_SIZE = 100; // 100 files
    public const INTERVAL = 30 * 60; // 30 minutes

    /**
     * @var ClassifyMusicService
     */
    private $musicnn;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var IRootFolder
     */
    private $rootFolder;
    /**
     * @var IUserManager
     */
    private $userManager;

    /**
     * @var \OCA\Recognize\Service\AudioFinderService
     */
    private $audioFinder;


    public function __construct(
        ClassifyMusicService $musicnn, ITimeFactory $timeFactory, IRootFolder $rootFolder, LoggerInterface $logger, IUserManager $userManager, AudioFinderService $audioFinder) {
        parent::__construct($timeFactory);

        $this->setInterval(self::INTERVAL);
        $this->musicnn = $musicnn;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->audioFinder = $audioFinder;
    }

    protected function run($argument) {
        $users = [];
        $this->userManager->callForSeenUsers(function(IUser $user) use (&$users) {
            $users[] = $user->getUID();
        });

        do {
            $user = array_pop($users);
            if (!$user) {
                $this->logger->debug('No users left, whose audios could be classified ');
                return;
            }
            $images = $this->audioFinder->findAudioInFolder($this->rootFolder->getUserFolder($user));
        }while(count($images) === 0);
        $images = array_slice($images, 0, self::BATCH_SIZE);

        $this->logger->warning('Classifying audios of user '.$user);
        try {
            $this->musicnn->classify($images);
        }catch(\Exception $e) {
            $this->logger->warning('Classifier process errored');
            $this->logger->warning($e->getMessage());
        }
    }
}
