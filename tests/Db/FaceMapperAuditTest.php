<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);
namespace OCA\Recognize\Tests\Db;

use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\SettingsService;
use OCA\Recognize\Tests\AuditLog\AuditLogTestHelper;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Server;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// Not \Test\TestCase: its tearDownAfterClass() clears the file hooks that ClassifierTest relies on
/**
 * @group DB
 */
final class FaceMapperAuditTest extends TestCase {
	private IDBConnection $db;
	private IEventDispatcher&MockObject $eventDispatcher;
	private FaceClusterMapper $clusterMapper;
	private FaceDetectionMapper $detectionMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->clusterMapper = new FaceClusterMapper($this->db, $this->eventDispatcher);
		$this->detectionMapper = new FaceDetectionMapper($this->db, Server::get(IConfig::class), Server::get(SettingsService::class), $this->eventDispatcher);
		$this->db->getQueryBuilder()->delete('recognize_face_clusters')->executeStatement();
		$this->db->getQueryBuilder()->delete('recognize_face_detections')->executeStatement();
	}

	protected function tearDown(): void {
		$this->db->getQueryBuilder()->delete('recognize_face_clusters')->executeStatement();
		$this->db->getQueryBuilder()->delete('recognize_face_detections')->executeStatement();
		parent::tearDown();
	}

	public function testDeleteAllClustersIsLogged(): void {
		foreach (['user1', 'user2'] as $userId) {
			$cluster = new FaceCluster();
			$cluster->setUserId($userId);
			$cluster->setTitle('');
			$this->clusterMapper->insert($cluster);
		}

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (CriticalActionPerformedEvent $event): bool {
				$this->assertSame('Deleted all %s Recognize face clusters', $event->getLogMessage());
				$this->assertSame(['count' => 2], $event->getParameters());
				$this->assertTrue(AuditLogTestHelper::hasOperation($event, 'recognize.face_clusters.deleted_all'));
				return true;
			}));

		$this->clusterMapper->deleteAll();
	}

	public function testDeleteAllClustersWithoutClustersIsNotLogged(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->clusterMapper->deleteAll();
	}

	public function testDeleteAllDetectionsIsLogged(): void {
		for ($i = 0; $i < 3; $i++) {
			$detection = new FaceDetection();
			$detection->setUserId('user' . $i);
			$detection->setX(0.1 * $i);
			$detection->setY(0.1 * $i);
			$detection->setHeight(0.5);
			$detection->setWidth(0.5);
			$detection->setFileId($i);
			$detection->setThreshold(0.5);
			$detection->setVector([1, 2, 3, 4, 5, 6, 7, 8, 9, 0]);
			$this->detectionMapper->insertWithoutDeduplication($detection);
		}

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (CriticalActionPerformedEvent $event): bool {
				$this->assertSame('Deleted all %s Recognize face detections', $event->getLogMessage());
				$this->assertSame(['count' => 3], $event->getParameters());
				$this->assertTrue(AuditLogTestHelper::hasOperation($event, 'recognize.face_detections.deleted_all'));
				return true;
			}));

		$this->detectionMapper->deleteAll();
	}

	public function testDeleteAllDetectionsWithoutDetectionsIsNotLogged(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->detectionMapper->deleteAll();
	}
}
