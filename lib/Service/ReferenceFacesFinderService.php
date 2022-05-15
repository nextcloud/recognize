<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\DAV\CardDAV\ContactsManager;
use OCP\Contacts\IManager;
use OCP\ITempManager;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class ReferenceFacesFinderService {
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	private IManager $contacts;

	private ITempManager $tempManager;

	private ContactsManager $contactsManager;

	private IURLGenerator $urlGenerator;

	public function __construct(Logger $logger, IManager $contacts, ITempManager $tempManager, ContactsManager $contactsManager, IURLGenerator $urlGenerator) {
		$this->logger = $logger;
		$this->contacts = $contacts;
		$this->tempManager = $tempManager;
		$this->contactsManager = $contactsManager;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @param string $userId
	 * @return array
	 */
	public function findReferenceFacesForUser(string $userId):array {
		$faces = [];
		$this->contacts->clear();
		$this->contactsManager->setupContactsProvider($this->contacts, $userId, $this->urlGenerator);
		if (!$this->contacts->isEnabled()) {
			return $faces;
		}
		$books = $this->contacts->getUserAddressBooks();
		foreach ($books as $book) {
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
