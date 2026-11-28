<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Recognize\Tests\Service;

use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\ClassificationQueueBuilder;
use PHPUnit\Framework\TestCase;

final class ClassificationQueueBuilderTest extends TestCase {
	private const STORAGE_ID = 5;
	private const ROOT_ID = 42;

	private ClassificationQueueBuilder $builder;

	protected function setUp(): void {
		parent::setUp();
		$this->builder = new ClassificationQueueBuilder();
	}

	/**
	 * @param list<array{fileid: int, image: bool, video: bool, audio: bool}> $files
	 * @param array<array-key, true> $processedFileIds
	 * @param list<string> $models
	 * @return array<string, list<int>>
	 */
	private function queuedFileIds(array $files, array $processedFileIds = [], array $models = [
		ClusteringFaceClassifier::MODEL_NAME,
		ImagenetClassifier::MODEL_NAME,
		MovinetClassifier::MODEL_NAME,
		MusicnnClassifier::MODEL_NAME,
	]): array {
		$queues = $this->builder->build($files, $processedFileIds, $models, self::STORAGE_ID, self::ROOT_ID);
		return array_map(static function (array $queue): array {
			return array_map(static fn (QueueFile $queueFile): int => $queueFile->getFileId(), $queue);
		}, $queues);
	}

	public function testEveryFileTypeReachesItsClassifier(): void {
		$files = [
			['fileid' => 1, 'image' => true, 'video' => false, 'audio' => false],
			['fileid' => 2, 'image' => false, 'video' => true, 'audio' => false],
			['fileid' => 3, 'image' => false, 'video' => false, 'audio' => true],
		];

		$queues = $this->queuedFileIds($files);

		$this->assertSame([1], $queues[ClusteringFaceClassifier::MODEL_NAME]);
		$this->assertSame([1], $queues[ImagenetClassifier::MODEL_NAME]);
		$this->assertSame([2], $queues[MovinetClassifier::MODEL_NAME]);
		$this->assertSame([3], $queues[MusicnnClassifier::MODEL_NAME]);
	}

	public function testDisabledModelsGetNoFiles(): void {
		$files = [
			['fileid' => 1, 'image' => true, 'video' => true, 'audio' => true],
		];

		$queues = $this->queuedFileIds($files, [], [ImagenetClassifier::MODEL_NAME]);

		$this->assertSame([], $queues[ClusteringFaceClassifier::MODEL_NAME]);
		$this->assertSame([1], $queues[ImagenetClassifier::MODEL_NAME]);
		$this->assertSame([], $queues[MovinetClassifier::MODEL_NAME]);
		$this->assertSame([], $queues[MusicnnClassifier::MODEL_NAME]);
	}

	public function testProcessedFilesAreOnlySkippedForTagBasedClassifiers(): void {
		$files = [
			['fileid' => 1, 'image' => true, 'video' => false, 'audio' => false],
			['fileid' => 2, 'image' => true, 'video' => false, 'audio' => false],
		];

		// File 2 already carries the processed tag
		$queues = $this->queuedFileIds($files, [2 => true]);

		// The face classifier does not tag files and has its own retry tracking,
		// so it still receives both files.
		$this->assertSame([1, 2], $queues[ClusteringFaceClassifier::MODEL_NAME]);
		// Tag-based classifiers only see the untagged file.
		$this->assertSame([1], $queues[ImagenetClassifier::MODEL_NAME]);
	}

	public function testProcessedLookupMatchesStringAndIntKeys(): void {
		$files = [
			['fileid' => 123, 'image' => true, 'video' => false, 'audio' => false],
		];

		// array_fill_keys() on numeric strings produces int keys, like the command does
		$processedFileIds = array_fill_keys(['123'], true);
		$queues = $this->queuedFileIds($files, $processedFileIds);

		$this->assertSame([123], $queues[ClusteringFaceClassifier::MODEL_NAME]);
		$this->assertSame([], $queues[ImagenetClassifier::MODEL_NAME]);
	}

	public function testQueueFilesCarryStorageInformation(): void {
		$files = [
			['fileid' => 7, 'image' => true, 'video' => false, 'audio' => false],
		];

		$queues = $this->builder->build($files, [], [ImagenetClassifier::MODEL_NAME], self::STORAGE_ID, self::ROOT_ID);
		$queueFile = $queues[ImagenetClassifier::MODEL_NAME][0];

		$this->assertSame(7, $queueFile->getFileId());
		$this->assertSame(self::STORAGE_ID, $queueFile->getStorageId());
		$this->assertSame(self::ROOT_ID, $queueFile->getRootId());
		$this->assertFalse($queueFile->getUpdate());
	}
}
