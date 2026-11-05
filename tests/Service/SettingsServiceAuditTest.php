<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);
namespace OCA\Recognize\Tests\Service;

use OCA\Recognize\Exception\Exception;
use OCA\Recognize\Service\SettingsService;
use OCA\Recognize\Tests\AuditLog\AuditLogTestHelper;
use OCP\AppFramework\Services\IAppConfig;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// Not \Test\TestCase: its tearDownAfterClass() clears the file hooks that ClassifierTest relies on
final class SettingsServiceAuditTest extends TestCase {
	private IAppConfig&MockObject $config;
	private IJobList&MockObject $jobList;
	private IEventDispatcher&MockObject $eventDispatcher;
	private SettingsService $service;
	/** @var array<string, string> */
	private array $stored = [];

	protected function setUp(): void {
		parent::setUp();
		$this->stored = [];
		$this->config = $this->createMock(IAppConfig::class);
		$this->config->method('getAppValueString')
			->willReturnCallback(fn (string $key, string $default = '', bool $lazy = false): string => $this->stored[$key] ?? $default);
		$this->config->method('setAppValueString')
			->willReturnCallback(function (string $key, string $value, bool $lazy = false): bool {
				$this->stored[$key] = $value;
				return true;
			});
		$this->jobList = $this->createMock(IJobList::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->service = new SettingsService($this->config, $this->jobList, $this->eventDispatcher);
	}

	public function testChangedSettingIsLogged(): void {
		$this->stored['node_binary'] = '/usr/bin/node';

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (CriticalActionPerformedEvent $event): bool {
				$this->assertSame('Recognize setting "%s" was changed to "%s"', $event->getLogMessage());
				$this->assertSame(['key' => 'node_binary', 'value' => '/opt/node/bin/node', 'oldValue' => '/usr/bin/node'], $event->getParameters());
				$this->assertTrue(AuditLogTestHelper::hasOperation($event, 'recognize.setting.changed'));
				return true;
			}));

		$this->service->setSetting('node_binary', '/opt/node/bin/node');
	}

	public function testChangeFromDefaultIsLogged(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (CriticalActionPerformedEvent $event): bool {
				$this->assertSame(['key' => 'tensorflow.cores', 'value' => '4', 'oldValue' => '0'], $event->getParameters());
				return true;
			}));

		$this->service->setSetting('tensorflow.cores', '4');
	}

	public function testUnchangedSettingIsNotLogged(): void {
		$this->stored['faces.enabled'] = 'true';
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->setSetting('faces.enabled', 'true');
	}

	public function testSettingDefaultValueIsNotLogged(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->setSetting('imagenet.enabled', 'false');
	}

	public function testStateSettingsAreNotLogged(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->setSetting('faces.status', 'true');
		$this->service->setSetting('faces.lastFile', '1700000000');
		$this->service->setSetting('clusterFaces.lastRun', '1700000000');
	}

	public function testUnknownSettingIsNotLogged(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');
		$this->expectException(Exception::class);

		$this->service->setSetting('unknown', 'value');
	}
}
