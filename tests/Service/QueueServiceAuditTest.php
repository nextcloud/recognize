<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);
namespace OCA\Recognize\Tests\Service;

use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Db\QueueMapper;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Tests\AuditLog\AuditLogTestHelper;
use OCP\AppFramework\Services\IAppConfig;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Server;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// Not \Test\TestCase: its tearDownAfterClass() clears the file hooks that ClassifierTest relies on
/**
 * @group DB
 */
final class QueueServiceAuditTest extends TestCase {
	private const MODELS = ['imagenet', 'faces', 'landmarks', 'movinet', 'musicnn'];

	private QueueMapper $queueMapper;
	private IJobList&MockObject $jobList;
	private IEventDispatcher&MockObject $eventDispatcher;
	private QueueService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->queueMapper = new QueueMapper(Server::get(IDBConnection::class));
		$this->jobList = $this->createMock(IJobList::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->service = new QueueService($this->queueMapper, $this->jobList, $this->createMock(IAppConfig::class), $this->eventDispatcher);
		foreach (self::MODELS as $model) {
			$this->queueMapper->clearQueue($model);
		}
	}

	protected function tearDown(): void {
		foreach (self::MODELS as $model) {
			$this->queueMapper->clearQueue($model);
		}
		parent::tearDown();
	}

	private function queueFile(string $model, int $fileId): void {
		$file = new QueueFile();
		$file->setFileId($fileId);
		$file->setStorageId(1);
		$file->setRootId(1);
		$file->setUpdate(false);
		$this->queueMapper->insertIntoQueue($model, $file);
	}

	public function testClearAllJobsIsLogged(): void {
		$this->queueFile('imagenet', 1);
		$this->queueFile('imagenet', 2);
		$this->queueFile('faces', 1);
		$this->jobList->expects($this->never())->method('add');

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (CriticalActionPerformedEvent $event): bool {
				$this->assertSame('Cleared all Recognize queues and background jobs, removing %s queued files', $event->getLogMessage());
				$this->assertSame(['removedFiles' => 3], $event->getParameters());
				$this->assertTrue(AuditLogTestHelper::hasOperation($event, 'recognize.queues.cleared'));
				return true;
			}));

		$this->service->clearAllJobs();
		foreach (self::MODELS as $model) {
			$this->assertSame(0, $this->queueMapper->count($model));
		}
	}

	public function testScheduleRecrawlIsLogged(): void {
		$this->queueFile('musicnn', 1);
		$this->jobList->expects($this->once())->method('add')->with(SchedulerJob::class);

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (CriticalActionPerformedEvent $event): bool {
				$this->assertSame('Scheduled a Recognize recrawl of all files, removing %s queued files', $event->getLogMessage());
				$this->assertSame(['removedFiles' => 1], $event->getParameters());
				$this->assertTrue(AuditLogTestHelper::hasOperation($event, 'recognize.recrawl.scheduled'));
				return true;
			}));

		$this->service->scheduleRecrawl();
	}

	public function testFailedRecrawlIsNotLogged(): void {
		$this->jobList->method('add')->willThrowException(new \RuntimeException('failed'));
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');
		$this->expectException(\RuntimeException::class);

		$this->service->scheduleRecrawl();
	}
}
