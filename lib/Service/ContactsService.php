<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Service;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\Recognize\Contacts\ContactCard;
use OCA\Recognize\Exception\Exception;
use OCP\App\IAppManager;
use OCP\IURLGenerator;
use Sabre\VObject\Component\VCard;

/**
 * Reads and writes the contacts of a user through the CardDAV backend.
 *
 * Only the address books the user has access to are ever touched, so
 * resolving a contact by (address book URI, UID) doubles as the access check.
 */
final class ContactsService {
	private const READ_ONLY_PROPERTY = '{http://owncloud.org/ns}read-only';

	/** @var array<string, list<array{id: int, uri: string, displayName: string, readOnly: bool}>> */
	private array $addressBooksByUser = [];

	public function __construct(
		private CardDavBackend $cardDavBackend,
		private IURLGenerator $urlGenerator,
		private IAppManager $appManager,
	) {
	}

	/**
	 * The address books a user can read, own ones and ones shared with them.
	 *
	 * @return list<array{id: int, uri: string, displayName: string, readOnly: bool}>
	 */
	public function getAddressBooks(string $userId): array {
		if (!isset($this->addressBooksByUser[$userId])) {
			$books = [];
			/** @var array<string, mixed> $row */
			foreach ($this->cardDavBackend->getAddressBooksForUser('principals/users/' . $userId) as $row) {
				$books[] = [
					'id' => (int)$row['id'],
					'uri' => (string)$row['uri'],
					'displayName' => (string)($row['{DAV:}displayname'] ?? $row['uri']),
					'readOnly' => (bool)($row[self::READ_ONLY_PROPERTY] ?? false),
				];
			}
			$this->addressBooksByUser[$userId] = $books;
		}
		return $this->addressBooksByUser[$userId];
	}

	/**
	 * The Contacts app identifies an address book by the last segment of its DAV
	 * href, so a percent-encoded URI is accepted as well.
	 *
	 * @return array{id: int, uri: string, displayName: string, readOnly: bool}|null
	 */
	public function findAddressBook(string $userId, string $addressBookUri): ?array {
		$decodedUri = rawurldecode($addressBookUri);
		foreach ($this->getAddressBooks($userId) as $book) {
			if ($book['uri'] === $addressBookUri || $book['uri'] === $decodedUri) {
				return $book;
			}
		}
		return null;
	}

	/**
	 * All contacts of one address book of the user.
	 *
	 * @return list<ContactCard>
	 */
	public function getContacts(string $userId, string $addressBookUri): array {
		$book = $this->findAddressBook($userId, $addressBookUri);
		if ($book === null) {
			return [];
		}
		$cards = [];
		/** @var array<string, mixed> $row */
		foreach ($this->cardDavBackend->getCards($book['id']) as $row) {
			$card = $this->rowToCard($book, $row);
			if ($card !== null) {
				$cards[] = $card;
			}
		}
		return $cards;
	}

	/**
	 * @return ContactCard|null The contact, or null when the user has no such contact
	 */
	public function findContact(string $userId, string $addressBookUri, string $uid): ?ContactCard {
		$book = $this->findAddressBook($userId, $addressBookUri);
		if ($book === null) {
			return null;
		}
		$row = $this->cardDavBackend->getCardByUid($book['id'], $uid);
		if ($row === null) {
			return null;
		}
		return $this->rowToCard($book, $row);
	}

	/**
	 * Contacts of the user whose name or email matches the pattern, across all
	 * their address books. An empty pattern lists the first contacts of each book.
	 *
	 * @return list<ContactCard>
	 */
	public function searchContacts(string $userId, string $pattern, int $limit): array {
		$books = [];
		foreach ($this->getAddressBooks($userId) as $book) {
			$books[$book['id']] = $book;
		}
		if (count($books) === 0) {
			return [];
		}

		$rows = $this->cardDavBackend->searchPrincipalUri(
			'principals/users/' . $userId,
			$pattern,
			['FN', 'N', 'EMAIL', 'NICKNAME'],
			['limit' => $limit, 'escape_like_param' => true],
		);

		$cards = [];
		/** @var array<string, mixed> $row */
		foreach ($rows as $row) {
			$book = $books[(int)$row['addressbookid']] ?? null;
			if ($book === null) {
				continue;
			}
			$card = $this->rowToCard($book, $row);
			if ($card !== null) {
				$cards[] = $card;
			}
		}
		usort($cards, static fn (ContactCard $a, ContactCard $b): int => strcasecmp($a->getDisplayName(), $b->getDisplayName()));
		return $cards;
	}

	/**
	 * Replace the photo of a contact.
	 *
	 * @param string $mimeType image/jpeg or image/png
	 * @param string $data The raw image bytes
	 * @throws Exception When the address book cannot be written to
	 */
	public function setPhoto(ContactCard $contact, string $mimeType, string $data): void {
		if ($contact->isAddressBookReadOnly()) {
			throw new Exception('The address book of this contact is read-only');
		}

		$vCard = $contact->getVCard();
		$vCard->remove('PHOTO');
		if ($vCard->getDocumentType() === VCard::VCARD40) {
			// https://tools.ietf.org/html/rfc6350#section-6.2.4
			$vCard->add('PHOTO', 'data:' . $mimeType . ';base64,' . base64_encode($data), ['VALUE' => 'URI']);
		} else {
			// https://tools.ietf.org/html/rfc2426#section-3.1.4
			$vCard->add('PHOTO', $data, ['ENCODING' => 'b', 'TYPE' => strtoupper(substr($mimeType, strlen('image/')))]);
		}

		$this->cardDavBackend->updateCard($contact->getAddressBookId(), $contact->getUri(), $vCard->serialize());
	}

	/**
	 * The page of the contact in the Contacts app, or null when that app is not available to the user.
	 */
	public function getContactUrl(string $userId, ContactCard $contact): ?string {
		if (!$this->appManager->isEnabledForUser('contacts')) {
			return null;
		}
		// The Contacts app knows the address book by the last segment of its DAV href
		return $this->urlGenerator->linkToRouteAbsolute('contacts.contacts.direct', [
			'contact' => $contact->getUid() . '~' . \Sabre\HTTP\encodePath($contact->getAddressBookUri()),
		]);
	}

	/**
	 * Where the photo of the contact can be fetched, which is the same endpoint the Contacts app uses.
	 */
	public function getPhotoUrl(string $userId, ContactCard $contact): string {
		return $this->urlGenerator->getAbsoluteURL(
			'remote.php/dav/addressbooks/users/' . rawurlencode($userId) . '/' . rawurlencode($contact->getAddressBookUri()) . '/' . rawurlencode($contact->getUri()) . '?photo'
		);
	}

	/**
	 * @return array{uid: string, addressBook: string, addressBookName: string, displayName: string, hasPhoto: bool, readOnly: bool, url: string|null, photoUrl: string|null}
	 */
	public function serializeContact(string $userId, ContactCard $contact): array {
		return array_merge($contact->toArray(), [
			'url' => $this->getContactUrl($userId, $contact),
			'photoUrl' => $contact->hasPhoto() ? $this->getPhotoUrl($userId, $contact) : null,
		]);
	}

	/**
	 * @param array{id: int, uri: string, displayName: string, readOnly: bool} $book
	 * @param array $row A row of the cards table
	 */
	private function rowToCard(array $book, array $row): ?ContactCard {
		$cardData = $row['carddata'] ?? null;
		if (is_resource($cardData)) {
			$cardData = stream_get_contents($cardData);
		}
		if (!is_string($cardData) || $cardData === '') {
			return null;
		}
		$card = new ContactCard(
			$book['id'],
			$book['uri'],
			$book['displayName'],
			$book['readOnly'],
			(string)$row['uri'],
			isset($row['uid']) ? (string)$row['uid'] : null,
			$cardData,
		);
		try {
			if ($card->getUid() === '') {
				return null;
			}
		} catch (\Throwable $e) {
			// Unparsable card data
			return null;
		}
		return $card;
	}
}
