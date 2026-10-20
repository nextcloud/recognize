<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Contacts;

use Sabre\VObject\Component\VCard;
use Sabre\VObject\Parameter;
use Sabre\VObject\Reader;

/**
 * A contact of one of the address books a user has access to.
 *
 * Address books are identified by the URI they have for that user, which is
 * also what the Contacts app uses, so a link stored as (address book, UID)
 * can be resolved from both apps.
 */
final class ContactCard {
	private ?VCard $vCard = null;
	private ?string $uid;

	/**
	 * @param string|null $uid The UID of the card, read from the card data when not given
	 */
	public function __construct(
		private readonly int $addressBookId,
		private readonly string $addressBookUri,
		private readonly string $addressBookName,
		private readonly bool $addressBookReadOnly,
		private readonly string $uri,
		?string $uid,
		private readonly string $cardData,
	) {
		$this->uid = $uid !== '' ? $uid : null;
	}

	public function getAddressBookId(): int {
		return $this->addressBookId;
	}

	public function getAddressBookUri(): string {
		return $this->addressBookUri;
	}

	public function getAddressBookName(): string {
		return $this->addressBookName;
	}

	public function isAddressBookReadOnly(): bool {
		return $this->addressBookReadOnly;
	}

	public function getUri(): string {
		return $this->uri;
	}

	public function getUid(): string {
		if ($this->uid === null) {
			$vCard = $this->getVCard();
			$this->uid = isset($vCard->UID) ? (string)$vCard->UID : '';
		}
		return $this->uid;
	}

	public function getVCard(): VCard {
		if ($this->vCard === null) {
			/** @var VCard $vCard */
			$vCard = Reader::read($this->cardData);
			$this->vCard = $vCard;
		}
		return $this->vCard;
	}

	public function getDisplayName(): string {
		$vCard = $this->getVCard();
		$fn = isset($vCard->FN) ? trim((string)$vCard->FN) : '';
		if ($fn !== '') {
			return $fn;
		}
		if (isset($vCard->N)) {
			// N holds family name, given name, additional names, prefix and suffix
			$parts = array_map(static fn ($part): string => trim((string)$part), $vCard->N->getParts());
			$ordered = array_filter([$parts[3] ?? '', $parts[1] ?? '', $parts[2] ?? '', $parts[0] ?? '', $parts[4] ?? '']);
			if (count($ordered) > 0) {
				return implode(' ', $ordered);
			}
		}
		return $this->getUid();
	}

	public function hasPhoto(): bool {
		return $this->getPhoto() !== null;
	}

	/**
	 * The embedded photo of the contact. Photos referenced by URL are not
	 * fetched, as the server never downloads remote content on behalf of a user.
	 *
	 * @return array{mimeType: string, data: string}|null
	 */
	public function getPhoto(): ?array {
		$vCard = $this->getVCard();
		if (!isset($vCard->PHOTO)) {
			return null;
		}
		$photo = $vCard->PHOTO;
		$value = $photo->getValue();
		if ($value === '') {
			return null;
		}

		if ($photo->getValueType() === 'URI') {
			if (!str_starts_with($value, 'data:')) {
				return null;
			}
			$comma = strpos($value, ',');
			if ($comma === false) {
				return null;
			}
			$meta = substr($value, strlen('data:'), $comma - strlen('data:'));
			$payload = substr($value, $comma + 1);
			$data = str_contains($meta, ';base64') ? base64_decode($payload, true) : rawurldecode($payload);
			if ($data === false || $data === '') {
				return null;
			}
			$mimeType = strtolower(explode(';', $meta)[0]);
			return ['mimeType' => $mimeType !== '' ? $mimeType : 'application/octet-stream', 'data' => $data];
		}

		$type = '';
		foreach (['TYPE', 'MEDIATYPE'] as $parameterName) {
			if (!isset($photo[$parameterName])) {
				continue;
			}
			$parameter = $photo[$parameterName];
			if ($parameter instanceof Parameter) {
				$type = strtolower((string)$parameter->getValue());
				break;
			}
		}
		if ($type !== '' && !str_starts_with($type, 'image/')) {
			$type = 'image/' . $type;
		}
		return ['mimeType' => $type !== '' ? $type : 'application/octet-stream', 'data' => $value];
	}

	/**
	 * @return array{uid: string, addressBook: string, addressBookName: string, displayName: string, hasPhoto: bool, readOnly: bool}
	 */
	public function toArray(): array {
		return [
			'uid' => $this->getUid(),
			'addressBook' => $this->addressBookUri,
			'addressBookName' => $this->addressBookName,
			'displayName' => $this->getDisplayName(),
			'hasPhoto' => $this->hasPhoto(),
			'readOnly' => $this->addressBookReadOnly,
		];
	}
}
