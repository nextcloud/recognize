<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\ClassifyImagenetService;
use OCA\Recognize\Service\ImagesFinderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;

class ClassifyJob extends TimedJob {
    public const BATCH_SIZE = 100; // 100 images
    public const INTERVAL = 30 * 60; // 30 minutes

    /**
     * @var ClassifyImagenetService
     */
    private $imagenet;
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
    /**
     * @var \OCA\Recognize\Service\ClassifyFacesService
     */
    private $facenet;
    /**
     * @var \OCA\Recognize\Service\ReferenceFacesFinderService
     */
    private $referenceFacesFinder;

    public function __construct(
        \OCA\Recognize\Service\ClassifyFacesService $facenet, ClassifyImagenetService $imagenet, ITimeFactory $timeFactory, IRootFolder $rootFolder, LoggerInterface $logger, IUserManager $userManager, ImagesFinderService $imagesFinder, \OCA\Recognize\Service\ReferenceFacesFinderService $referenceFacesFinder
    ) {
        parent::__construct($timeFactory);

        $this->setInterval(self::INTERVAL);
        $this->imagenet = $imagenet;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->imagesFinder = $imagesFinder;
        $this->facenet = $facenet;
        $this->referenceFacesFinder = $referenceFacesFinder;
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
        try {
            $this->imagenet->classify($images);
            $faces = $this->referenceFacesFinder->findReferenceFacesForUser($user);
            $this->facenet->classify($faces, $images);
        }catch(\Exception $e) {
            $this->logger->warning('Classifier process errored');
            $this->logger->warning($e->getMessage());
        }
    }
}
