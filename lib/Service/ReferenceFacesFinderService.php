<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\Recognize\Service\TagManager;
use OCP\Contacts\IManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagObjectMapper;
use Psr\Log\LoggerInterface;

class ReferenceFacesFinderService
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var \OCP\Contacts\IManager
     */
    private $contacts;
    /**
     * @var \OCP\IUserSession
     */
    private $session;
    /**
     * @var \OCP\IUserManager
     */
    private $users;
    /**
     * @var \OCP\ITempManager
     */
    private $tempManager;

    public function __construct(LoggerInterface $logger, IManager $contacts, \OCP\IUserSession $session, \OCP\IUserManager $users, \OCP\ITempManager $tempManager) {
        $this->logger = $logger;
        $this->contacts = $contacts;
        $this->session = $session;
        $this->users = $users;
        $this->tempManager = $tempManager;
    }

    /**
     * @param string $userId
     * @return array
     */
    public function findReferenceFacesForUser(string $userId):array {
        $faces = [];
        if (!$this->contacts->isEnabled()) {
            return $faces;
        }
        $this->session->setUser($this->users->get($userId));
        $books = $this->contacts->getUserAddressBooks();
        foreach($books as $book) {
            $cards = $book->search('%',['FN'],['escape_like_param' => false]);
            foreach($cards as $card) {
                if (!isset($card['PHOTO'])) {
                    continue;
                }
                try {
                    $photo = $card['PHOTO'];
                    if (str_starts_with($card['PHOTO'], 'VALUE=uri:')) {
                        $photo = substr($photo, strlen('VALUE=uri:'));
                    }
                    $image = file_get_contents($photo);
                    $filePath = $this->tempManager->getTemporaryFile();
                    file_put_contents($filePath, $image, FILE_APPEND);
                    $faces[$card['FN']] = $filePath;
                }catch(\Exception $e) {
                    $this->logger->debug($e->getMessage());
                }
            }
        }
        return $faces;
    }
}
