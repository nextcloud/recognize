<?php

namespace OCA\Recognize\Files;

use OCA\Recognize\Db\Audio;
use OCA\Recognize\Db\AudioMapper;
use OCA\Recognize\Service\Logger;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;

class AudioToDbFinder extends AudioFinder
{
    /**
     * @var \OCA\Recognize\Service\Logger
     */
    private Logger $logger;
    /**
     * @var \OCA\Recognize\Db\AudioMapper
     */
    private AudioMapper $audioMapper;

    public function __construct(Logger $logger, AudioMapper $audioMapper)
    {
        parent::__construct($logger);
        $this->logger = $logger;
        $this->audioMapper = $audioMapper;
    }


    /**
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotFoundException
     * @throws \OCP\DB\Exception
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function foundFile(string $user, File $node) {
        try {
            $this->audioMapper->findByFileId($node->getId());
        } catch (DoesNotExistException $e) {
            $audio = new Audio();
            $audio->setUserId($user);
            $audio->setFileId($node->getId());
            $this->audioMapper->insert($audio);
        }
    }
}
