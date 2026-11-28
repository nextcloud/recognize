<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Recognize\Tests\Controller;

use OCA\Recognize\BackgroundJobs\DownloadModelsJob;
use OCA\Recognize\Controller\AdminController;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\QueueMapper;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\SettingsService;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\IJobRuns;
use OCP\BackgroundJob\JobRun;
use OCP\BackgroundJob\JobStatus;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IBinaryFinder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminControllerTest extends TestCase {
	private const MODELS_DIR = __DIR__ . '/../../models';

	private IJobList&MockObject $jobList;
	private IJobRuns&MockObject $jobRuns;
	private ITimeFactory&MockObject $timeFactory;
	private AdminController $controller;

	protected function setUp(): void {
		parent::setUp();
		if (\is_dir(self::MODELS_DIR)) {
			$this->markTestSkipped('The models directory exists in this checkout');
		}

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$jobList = $this->createMock(IJobList::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$db = $this->createMock(IDBConnection::class);
		$systemConfig = $this->createMock(IConfig::class);

		$this->jobList = $jobList;
		$this->jobRuns = $this->createMock(IJobRuns::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		$settingsService = new SettingsService($appConfig, $jobList, $eventDispatcher, $db);
		$queueService = new QueueService(new QueueMapper($db), $jobList, $appConfig, $eventDispatcher);
		$clusterMapper = new FaceClusterMapper($db, $eventDispatcher);
		$detectionMapper = new FaceDetectionMapper($db, $systemConfig, $settingsService, $eventDispatcher);
		$tagManager = new TagManager(
			$this->createMock(ISystemTagManager::class),
			$this->createMock(ISystemTagObjectMapper::class),
			$eventDispatcher,
		);

		$this->controller = new AdminController(
			'recognize',
			$this->createMock(IRequest::class),
			$tagManager,
			$jobList,
			$settingsService,
			$queueService,
			$clusterMapper,
			$detectionMapper,
			$appConfig,
			$detectionMapper,
			$this->createMock(IBinaryFinder::class),
			$this->jobRuns,
			$this->timeFactory,
		);
	}

	/**
	 * @param list<JobRun> $runningJobs
	 */
	private function mockRunningJobs(array $runningJobs): void {
		$this->jobRuns->method('runningJobs')->willReturnCallback(
			static function () use ($runningJobs): \Generator {
				yield from $runningJobs;
			},
		);
	}

	private function runningJob(string $className, int $ageInSeconds = 60): JobRun {
		return new JobRun(
			'123456789',
			$className,
			1,
			1234,
			new \DateTimeImmutable('-' . $ageInSeconds . ' seconds'),
			JobStatus::RUNNING,
		);
	}

	public function testModelsStatusReportsQueuedJob(): void {
		$this->jobList->method('has')->with(DownloadModelsJob::class, null)->willReturn(true);
		$this->mockRunningJobs([]);

		$response = $this->controller->modelsStatus();

		$this->assertSame(['downloaded' => false, 'scheduled' => true], $response->getData());
	}

	public function testModelsStatusReportsRunningJob(): void {
		$this->jobList->method('has')->willReturn(false);
		$this->mockRunningJobs([$this->runningJob(DownloadModelsJob::class)]);
		$this->timeFactory->method('getTime')->willReturn(\time());

		$response = $this->controller->modelsStatus();

		$this->assertSame(['downloaded' => false, 'scheduled' => true], $response->getData());
	}

	public function testModelsStatusIgnoresStaleRunningJob(): void {
		$this->jobList->method('has')->willReturn(false);
		// A crashed PHP process can leave a stale "running" entry behind
		$this->mockRunningJobs([$this->runningJob(DownloadModelsJob::class, 25 * 3600)]);
		$this->timeFactory->method('getTime')->willReturn(\time());

		$response = $this->controller->modelsStatus();

		$this->assertSame(['downloaded' => false, 'scheduled' => false], $response->getData());
	}

	public function testModelsStatusIgnoresOtherRunningJobs(): void {
		$this->jobList->method('has')->willReturn(false);
		$this->mockRunningJobs([$this->runningJob(\stdClass::class)]);
		$this->timeFactory->method('getTime')->willReturn(\time());

		$response = $this->controller->modelsStatus();

		$this->assertSame(['downloaded' => false, 'scheduled' => false], $response->getData());
	}

	public function testDownloadModelsDoesNotQueueSecondJobWhileQueued(): void {
		$this->jobList->method('has')->willReturn(true);
		$this->mockRunningJobs([]);
		$this->jobList->expects($this->never())->method('add');

		$response = $this->controller->downloadModels();

		$this->assertSame(['downloaded' => false, 'scheduled' => true], $response->getData());
	}

	public function testDownloadModelsDoesNotQueueSecondJobWhileRunning(): void {
		$this->jobList->method('has')->willReturn(false);
		$this->mockRunningJobs([$this->runningJob(DownloadModelsJob::class)]);
		$this->timeFactory->method('getTime')->willReturn(\time());
		$this->jobList->expects($this->never())->method('add');

		$response = $this->controller->downloadModels();

		$this->assertSame(['downloaded' => false, 'scheduled' => true], $response->getData());
	}

	public function testDownloadModelsQueuesJobWhenIdle(): void {
		$this->jobList->method('has')->willReturn(false);
		$this->mockRunningJobs([]);
		$this->jobList->expects($this->once())->method('add')->with(DownloadModelsJob::class);

		$response = $this->controller->downloadModels();

		$this->assertSame(['downloaded' => false, 'scheduled' => true], $response->getData());
	}
}
