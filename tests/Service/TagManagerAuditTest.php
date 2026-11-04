<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);
namespace OCA\Recognize\Tests\Service;

use OCA\Recognize\Service\TagManager;
use OCA\Recognize\Tests\AuditLog\AuditLogTestHelper;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// Not \Test\TestCase: its tearDownAfterClass() clears the file hooks that ClassifierTest relies on
final class TagManagerAuditTest extends TestCase {
	private ISystemTagManager&MockObject $systemTagManager;
	private ISystemTagObjectMapper&MockObject $objectMapper;
	private IEventDispatcher&MockObject $eventDispatcher;
	private TagManager $tagManager;

	protected function setUp(): void {
		parent::setUp();
		$this->systemTagManager = $this->createMock(ISystemTagManager::class);
		$this->objectMapper = $this->createMock(ISystemTagObjectMapper::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->tagManager = new TagManager($this->systemTagManager, $this->objectMapper, $this->eventDispatcher);
	}

	private function createTag(string $id, string $name = 'tag'): ISystemTag&MockObject {
		$tag = $this->createMock(ISystemTag::class);
		$tag->method('getId')->willReturn($id);
		$tag->method('getName')->willReturn($name);
		return $tag;
	}

	private function mockProcessedTag(): void {
		$processedTag = $this->createTag('1', TagManager::RECOGNIZED_TAG);
		$this->systemTagManager->method('getTag')
			->willReturnCallback(function (string $name) use ($processedTag): ISystemTag {
				if ($name === TagManager::RECOGNIZED_TAG) {
					return $processedTag;
				}
				throw new TagNotFoundException();
			});
	}

	public function testResetClassificationsIsLogged(): void {
		$this->mockProcessedTag();
		$this->objectMapper->method('getObjectIdsForTags')->willReturn(['10', '11', '12']);
		$this->objectMapper->expects($this->exactly(3))->method('unassignTags');

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (CriticalActionPerformedEvent $event): bool {
				$this->assertSame('Removed Recognize classification tags from %s files', $event->getLogMessage());
				$this->assertSame(['fileCount' => 3], $event->getParameters());
				$this->assertTrue(AuditLogTestHelper::hasOperation($event, 'recognize.classifications.reset'));
				return true;
			}));

		$this->tagManager->resetClassifications();
	}

	public function testResetClassificationsWithoutClassifiedFilesIsNotLogged(): void {
		$this->mockProcessedTag();
		$this->objectMapper->method('getObjectIdsForTags')->willReturn([]);
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->tagManager->resetClassifications();
	}

	public function testFailedResetClassificationsIsNotLogged(): void {
		$this->mockProcessedTag();
		$this->objectMapper->method('getObjectIdsForTags')->willReturn(['10']);
		$this->objectMapper->method('unassignTags')->willThrowException(new \RuntimeException('failed'));
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');
		$this->expectException(\RuntimeException::class);

		$this->tagManager->resetClassifications();
	}
}
