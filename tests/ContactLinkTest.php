<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

use OCA\Recognize\Contacts\ContactCard;
use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\ContactLinkService;
use Test\TestCase;

/**
 * @group DB
 */
class ContactLinkTest extends TestCase {
	public const TEST_USER1 = 'test-user-contact-link';

	private FaceDetectionMapper $faceDetectionMapper;
	private FaceClusterMapper $faceClusterMapper;
	private ContactLinkService $contactLinkService;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		$backend = new \Test\Util\User\Dummy();
		$backend->createUser(self::TEST_USER1, self::TEST_USER1);
		\OC::$server->get(\OCP\IUserManager::class)->registerBackend($backend);
	}

	public function setUp(): void {
		parent::setUp();
		$this->faceDetectionMapper = \OC::$server->get(FaceDetectionMapper::class);
		$this->faceClusterMapper = \OC::$server->get(FaceClusterMapper::class);
		$this->contactLinkService = \OC::$server->get(ContactLinkService::class);

		foreach ($this->faceClusterMapper->findByUserId(self::TEST_USER1) as $cluster) {
			$this->faceClusterMapper->delete($cluster);
		}
		foreach ($this->faceDetectionMapper->findByUserId(self::TEST_USER1) as $detection) {
			$this->faceDetectionMapper->delete($detection);
		}
	}

	private function createCluster(string $title, int $detections = 1): FaceCluster {
		$cluster = new FaceCluster();
		$cluster->setUserId(self::TEST_USER1);
		$cluster->setTitle($title);
		/** @var FaceCluster $cluster */
		$cluster = $this->faceClusterMapper->insert($cluster);
		for ($i = 0; $i < $detections; $i++) {
			$detection = new FaceDetection();
			$detection->setUserId(self::TEST_USER1);
			$detection->setFileId(1000 + $cluster->getId() * 10 + $i);
			$detection->setVector(array_fill(0, 128, 0.0));
			$detection->setX(0.1);
			$detection->setY(0.1);
			$detection->setHeight(0.5);
			$detection->setWidth(0.5);
			$detection->setClusterId($cluster->getId());
			$this->faceDetectionMapper->insert($detection);
		}
		return $cluster;
	}

	private function createContact(string $uid, string $name): ContactCard {
		return new ContactCard(1, 'contacts', 'Contacts', false, $uid . '.vcf', $uid, "BEGIN:VCARD\r\nVERSION:4.0\r\nUID:$uid\r\nFN:$name\r\nEND:VCARD\r\n");
	}

	public function testManualLinkAndUnlink(): void {
		$cluster = $this->createCluster('Someone');
		$contact = $this->createContact('uid-a', 'Alice');

		$cluster = $this->contactLinkService->linkCluster($cluster, $contact, FaceCluster::LINK_SOURCE_MANUAL);
		self::assertTrue($cluster->isLinked());
		self::assertSame('uid-a', $cluster->getContactUid());
		self::assertSame('contacts', $cluster->getContactAddressBook());
		self::assertSame(FaceCluster::LINK_SOURCE_MANUAL, $cluster->getContactLinkSource());
		// A manual link keeps the name the user gave the person
		self::assertSame('Someone', $cluster->getTitle());

		self::assertCount(1, $this->faceClusterMapper->findByContact(self::TEST_USER1, 'contacts', 'uid-a'));
		self::assertCount(1, $this->faceClusterMapper->findLinkedByUserId(self::TEST_USER1));
		self::assertCount(0, $this->faceClusterMapper->findAutoLinkCandidatesByUserId(self::TEST_USER1));

		$cluster = $this->contactLinkService->unlinkCluster($cluster);
		self::assertFalse($cluster->isLinked());
		self::assertNull($cluster->getContactUid());
		self::assertSame(FaceCluster::LINK_SOURCE_MANUAL, $cluster->getContactLinkSource());
		self::assertCount(0, $this->faceClusterMapper->findLinkedByUserId(self::TEST_USER1));
		// A person the user unlinked is not offered to the automatic linking again
		self::assertCount(0, $this->faceClusterMapper->findAutoLinkCandidatesByUserId(self::TEST_USER1));
	}

	public function testLinkingNamesUnnamedClusterAfterContact(): void {
		$unnamed = $this->createCluster('');
		$named = $this->createCluster('Bob');
		$this->createCluster('Empty', 0);

		$candidates = $this->faceClusterMapper->findAutoLinkCandidatesByUserId(self::TEST_USER1);
		// Only clusters with detections can be matched
		self::assertCount(2, $candidates);

		$unnamed = $this->contactLinkService->linkCluster($unnamed, $this->createContact('uid-a', 'Alice'), FaceCluster::LINK_SOURCE_AUTO);
		self::assertSame('Alice', $unnamed->getTitle());
		self::assertSame(FaceCluster::LINK_SOURCE_AUTO, $unnamed->getContactLinkSource());

		// Linking by hand names an unnamed person as well
		$manual = $this->createCluster('');
		$manual = $this->contactLinkService->linkCluster($manual, $this->createContact('uid-m', 'Mallory'), FaceCluster::LINK_SOURCE_MANUAL);
		self::assertSame('Mallory', $manual->getTitle());
		self::assertSame(FaceCluster::LINK_SOURCE_MANUAL, $manual->getContactLinkSource());

		// A name that is already taken is not used twice
		$another = $this->createCluster('');
		$another = $this->contactLinkService->linkCluster($another, $this->createContact('uid-a2', 'Alice'), FaceCluster::LINK_SOURCE_AUTO);
		self::assertSame('', $another->getTitle());
		self::assertTrue($another->isLinked());

		// A cluster that already has a name keeps it
		$named = $this->contactLinkService->linkCluster($named, $this->createContact('uid-b', 'Robert'), FaceCluster::LINK_SOURCE_AUTO);
		self::assertSame('Bob', $named->getTitle());

		self::assertCount(0, $this->faceClusterMapper->findAutoLinkCandidatesByUserId(self::TEST_USER1));
		self::assertContains(self::TEST_USER1, $this->faceClusterMapper->findUserIds());
	}

	public function testDeletingClusterRemovesLink(): void {
		$cluster = $this->createCluster('Carol');
		$this->contactLinkService->linkCluster($cluster, $this->createContact('uid-c', 'Carol'), FaceCluster::LINK_SOURCE_MANUAL);
		$this->faceClusterMapper->delete($cluster);
		self::assertCount(0, $this->faceClusterMapper->findByContact(self::TEST_USER1, 'contacts', 'uid-c'));
	}
}
