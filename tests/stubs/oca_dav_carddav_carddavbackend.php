<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\DAV\CardDAV;

use Sabre\CardDAV\Backend\BackendInterface;
use Sabre\CardDAV\Backend\SyncSupport;

class CardDavBackend implements BackendInterface, SyncSupport {
	/**
	 * @param string $principalUri
	 * @return array
	 */
	public function getAddressBooksForUser($principalUri) {
	}

	/**
	 * @param mixed $addressbookId
	 * @return array
	 */
	public function getCards($addressbookId) {
	}

	/**
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @return array|false
	 */
	public function getCard($addressBookId, $cardUri) {
	}

	public function getCardByUid(int $addressBookId, string $uid): ?array {
	}

	/**
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @param string $cardData
	 * @return string
	 */
	public function updateCard($addressBookId, $cardUri, $cardData) {
	}

	public function searchPrincipalUri(string $principalUri, string $pattern, array $searchProperties, array $options = []): array {
	}
}
