<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\ClassifyService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IUser;
use OCP\SystemTag\ISystemTagObjectMapper;

class ClassifyJob extends TimedJob {
	public const BATCH_SIZE = 100; // 100 images
	public const INTERVAL = 50 * 60; // 10 minutes

	/**
	 * @var IConfig
	 */
	private $settings;

	/**
	 * @var ClassifyService
	 */
	private $classifier;
	/**
	 * @var ITimeFactory
	 */
	private $timeFactory;
    /**
     * @var \OCP\SystemTag\ISystemTagObjectMapper
     */
    private $objectMapper;
    /**
     * @var \OCP\Files\IRootFolder
     */
    private $rootFolder;
    /**
     * @var \OCP\SystemTag\ISystemTag
     */
    private $recognizedTag;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var \OCP\IUserManager
     */
    private $userManager;

    public function __construct(
        IConfig $settings, ClassifyService $classifier, ITimeFactory $timeFactory, ISystemTagObjectMapper $objectMapper, IRootFolder $rootFolder, \Psr\Log\LoggerInterface $logger, \OCP\IUserManager $userManager
    ) {
		parent::__construct($timeFactory);
		$this->settings = $settings;

		$this->setInterval(self::INTERVAL);
		$this->classifier = $classifier;
        $this->objectMapper = $objectMapper;
        $this->rootFolder = $rootFolder;
        $this->recognizedTag = $this->classifier->getProcessedTag();
        $this->logger = $logger;
        $this->userManager = $userManager;
    }

	protected function run($argument) {
        $users = [];
        $this->userManager->callForSeenUsers(function(IUser $user) use (&$users) {
            $users[] = $user->getUID();
        });

        do {
            $user = array_pop($users);
            if (!$user) {
                $this->logger->warning('No users left, whose photos could be classified ');
                return;
            }
            $images = $this->findImagesInFolder($this->rootFolder->getUserFolder($user));
        }while(count($images) === 0);
		$images = array_slice($images, 0, self::BATCH_SIZE);

		$this->logger->warning('Classifying photos of user '.$user);
        $this->classifier->classify($images);
    }

    /**
     * @throws \OCP\Files\NotFoundException|\OCP\Files\InvalidPathException
     */
    protected function findImagesInFolder(Folder $folder, &$results = []):array {
        $this->logger->warning('Searching '.$folder->getInternalPath());
        $nodes = $folder->getDirectoryListing();
        foreach ($nodes as $node) {
            if ($node instanceof Folder) {
                $this->findImagesInFolder($node, $results);
            }
            else if ($node instanceof File) {
                if ($this->objectMapper->haveTag([$node->getId()], 'files', $this->recognizedTag->getId())) {
                    continue;
                }
                $mimeType = $node->getMimetype();
                if ($mimeType === 'image/jpeg') {
                    $this->logger->warning('Found '.$node->getPath());
                    $results[] = $node;
                }
            }
        }
        return $results;
    }
}
