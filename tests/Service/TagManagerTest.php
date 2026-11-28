<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Recognize\Tests\Service;

use OCA\Recognize\Service\TagManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TagManagerTest extends TestCase {
	private ISystemTagManager&MockObject $systemTagManager;
	private ISystemTagObjectMapper&MockObject $objectMapper;
	private TagManager $tagManager;

	protected function setUp(): void {
		parent::setUp();
		$this->systemTagManager = $this->createMock(ISystemTagManager::class);
		$this->objectMapper = $this->createMock(ISystemTagObjectMapper::class);
		$this->tagManager = new TagManager(
			$this->systemTagManager,
			$this->objectMapper,
			$this->createMock(IEventDispatcher::class),
		);

		$processedTag = $this->createMock(ISystemTag::class);
		$processedTag->method('getId')->willReturn('1');
		$this->systemTagManager->method('getTag')
			->willReturnCallback(function (string $name) use ($processedTag): ISystemTag {
				if ($name === TagManager::RECOGNIZED_TAG) {
					return $processedTag;
				}
				throw new TagNotFoundException();
			});
	}

	public function testGetProcessedFileIdsReturnsOnlyTaggedFiles(): void {
		$this->objectMapper->expects($this->once())
			->method('getTagIdsForObjects')
			->with(['10', '11', '12'], 'files')
			->willReturn([
				'10' => ['2', '3'],
				'11' => ['1'],
				'12' => [],
			]);
		// Resolving full tag objects per file would reintroduce the N+1 pattern
		$this->systemTagManager->expects($this->never())->method('getTagsByIds');

		$this->assertSame(['11'], $this->tagManager->getProcessedFileIds(['10', '11', '12']));
	}

	public function testGetProcessedFileIdsWithEmptyInputDoesNotQuery(): void {
		$this->objectMapper->expects($this->never())->method('getTagIdsForObjects');
		$this->systemTagManager->expects($this->never())->method('getTag');

		$this->assertSame([], $this->tagManager->getProcessedFileIds([]));
	}

	public function testGetProcessedFileIdsWithoutMatches(): void {
		$this->objectMapper->method('getTagIdsForObjects')->willReturn([
			'10' => ['2'],
			'11' => ['3', '4'],
		]);

		$this->assertSame([], $this->tagManager->getProcessedFileIds(['10', '11']));
	}
}
