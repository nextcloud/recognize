<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

use OCA\Recognize\Contacts\ContactCard;
use Test\TestCase;

class ContactCardTest extends TestCase {
	private const PNG_PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	private function card(string $cardData, ?string $uid = 'uid-1'): ContactCard {
		return new ContactCard(1, 'contacts', 'Contacts', false, 'uid-1.vcf', $uid, $cardData);
	}

	public function testDisplayNameFallsBackToStructuredName(): void {
		$card = $this->card("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:uid-1\r\nN:Doe;Jane;;;\r\nEND:VCARD\r\n");
		self::assertSame('Jane Doe', $card->getDisplayName());

		$card = $this->card("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:uid-1\r\nFN:Jane Q. Doe\r\nN:Doe;Jane;;;\r\nEND:VCARD\r\n");
		self::assertSame('Jane Q. Doe', $card->getDisplayName());
	}

	public function testUidIsReadFromCardWhenNotGiven(): void {
		$card = $this->card("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:from-card\r\nFN:Jane\r\nEND:VCARD\r\n", null);
		self::assertSame('from-card', $card->getUid());
	}

	public function testPhotoOfVCard4DataUri(): void {
		$card = $this->card("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:uid-1\r\nFN:Jane\r\nPHOTO:data:image/png;base64," . self::PNG_PIXEL . "\r\nEND:VCARD\r\n");
		$photo = $card->getPhoto();
		self::assertNotNull($photo);
		self::assertSame('image/png', $photo['mimeType']);
		self::assertSame(base64_decode(self::PNG_PIXEL), $photo['data']);
		self::assertTrue($card->hasPhoto());
	}

	public function testPhotoOfVCard3BinaryProperty(): void {
		$card = $this->card("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:uid-1\r\nFN:Jane\r\nPHOTO;ENCODING=b;TYPE=PNG:" . self::PNG_PIXEL . "\r\nEND:VCARD\r\n");
		$photo = $card->getPhoto();
		self::assertNotNull($photo);
		self::assertSame('image/png', $photo['mimeType']);
		self::assertSame(base64_decode(self::PNG_PIXEL), $photo['data']);
	}

	public function testRemotePhotoIsNotFetched(): void {
		$card = $this->card("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:uid-1\r\nFN:Jane\r\nPHOTO;VALUE=URI:https://example.com/jane.png\r\nEND:VCARD\r\n");
		self::assertNull($card->getPhoto());
		self::assertFalse($card->hasPhoto());
	}

	public function testCardWithoutPhoto(): void {
		$card = $this->card("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:uid-1\r\nFN:Jane\r\nEND:VCARD\r\n");
		self::assertNull($card->getPhoto());
		$array = $card->toArray();
		self::assertSame('uid-1', $array['uid']);
		self::assertSame('contacts', $array['addressBook']);
		self::assertFalse($array['hasPhoto']);
		self::assertFalse($array['readOnly']);
	}
}
