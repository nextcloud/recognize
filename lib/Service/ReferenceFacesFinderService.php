<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\DAV\CardDAV\ContactsManager;
use OCP\IConfig;
use OCA\Recognize\Service\TagManager;
use OCP\Contacts\IManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
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
     * @var IConfig
     */
    private $config;
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
    /**
     * @var \OCA\DAV\CardDAV\ContactsManager
     */
    private $contactsManager;
    /**
     * @var \OCP\IURLGenerator
     */
    private $urlGenerator;

    public function __construct(LoggerInterface $logger, IConfig $config, IManager $contacts, IUserSession $session, IUserManager $users, ITempManager $tempManager, ContactsManager $contactsManager, IURLGenerator $urlGenerator) {
        $this->logger = $logger;
        $this->config = $config;
        $this->contacts = $contacts;
        $this->session = $session;
        $this->users = $users;
        $this->tempManager = $tempManager;
        $this->contactsManager = $contactsManager;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @param string $userId
     * @return array
     */
    public function findReferenceFacesForUser(string $userId):array {
        if ($this->config->getAppValue('recognize', 'faces.enabled', 'false') !== 'true') {
            return 0;
        }
        $faces = [];
        $this->contacts->clear();
        $this->contactsManager->setupContactsProvider($this->contacts, $userId, $this->urlGenerator);
        if (!$this->contacts->isEnabled()) {
            return $faces;
        }
        $books = $this->contacts->getUserAddressBooks();
        foreach($books as $book) {
            if ($book->isSystemAddressBook()) {
                continue;
            }
            $cards = $book->search('', ['FN'], []);
            foreach ($cards as $card) {
                if (empty($card['PHOTO'])) {
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
                } catch (\Exception $e) {
                    $this->logger->debug($e->getMessage());
                }
            }
        }
        return $faces;
    }
}
