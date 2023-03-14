<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;

class TagManager {
	public const RECOGNIZED_TAG = 'Tagged by recognize v3.0.0';

	private ISystemTagManager $tagManager;
	private ISystemTagObjectMapper $objectMapper;

	public function __construct(ISystemTagManager $systemTagManager, ISystemTagObjectMapper $objectMapper) {
		$this->tagManager = $systemTagManager;
		$this->objectMapper = $objectMapper;
	}

	/**
	 * @param string $name
	 * @return \OCP\SystemTag\ISystemTag
	 */
	public function getTag(string $name) : ISystemTag {
		try {
			$tag = $this->tagManager->getTag($name, true, true);
		} catch (TagNotFoundException $e) {
			$tag = $this->tagManager->createTag($name, true, true);
		}
		return $tag;
	}

	/**
	 * @return \OCP\SystemTag\ISystemTag
	 */
	public function getProcessedTag(): ISystemTag {
		try {
			$tag = $this->tagManager->getTag(self::RECOGNIZED_TAG, false, false);
		} catch (TagNotFoundException $e) {
			$tag = $this->tagManager->createTag(self::RECOGNIZED_TAG, false, false);
		}
		return $tag;
	}

	/**
	 * @param int $fileId
	 * @param string $name
	 * @param string $oldName
	 * @return void
	 */
	public function assignFace(int $fileId, string $name, string $oldName = '') {
		if ($oldName) {
			$this->getTag($oldName);
			$this->objectMapper->unassignTags((string)$fileId, 'files', [$this->getTag($oldName)->getId()]);
		}
		$this->assignTags($fileId, [$name]);
	}

	/**
	 * @param int $fileId
	 * @param array<string> $tags
	 * @return void
	 */
	public function assignTags(int $fileId, array $tags): void {
		$tags = array_map(function ($tag) : string {
			return $this->getTag($tag)->getId();
		}, $tags);
		$tags[] = $this->getProcessedTag()->getId();
		/** @var array<string, string[]> $tagsByFile */
		$tagsByFile = $this->objectMapper->getTagIdsForObjects([$fileId], 'files');
		$oldTags = $tagsByFile[(string) $fileId];
		$this->objectMapper->assignTags((string)$fileId, 'files', array_unique(array_merge($tags, $oldTags)));
	}

	/**
	 * @param array $fileIds
	 * @return array
	 */
	public function getTagsForFiles(array $fileIds): array {
		/** @var array<string, string[]> $tagsByFile */
		$tagsByFile = $this->objectMapper->getTagIdsForObjects($fileIds, 'files');
		return array_map(function ($tags) : array {
			return $this->tagManager->getTagsByIds($tags);
		}, $tagsByFile);
	}

	/**
	 * @return array<string>
	 */
	public function findClassifiedFiles(): array {
		return $this->objectMapper->getObjectIdsForTags($this->getProcessedTag()->getId(), 'files');
	}

	/**
	 * @return array
	 */
	public function findMissedClassifications(): array {
		$classified = $this->findClassifiedFiles();
		$classifiedChunks = array_chunk($classified, 999, true);
		$missed = [];
		$processedId = $this->getProcessedTag()->getId();
		foreach ($classifiedChunks as $classifiedChunk) {
			/** @var array<string,string[]> $tagIdsByFile */
			$tagIdsByFile = $this->objectMapper->getTagIdsForObjects($classifiedChunk, 'files');
			$missedChunk = array_keys(array_filter($tagIdsByFile, function ($tags) use ($processedId) : bool {
				return count($tags) === 1 && $tags[0] !== $processedId;
			}));
			$missed = array_merge($missed, $missedChunk);
		}
		return $missed;
	}

	/**
	 * @return void
	 * @throws \JsonException
	 */
	public function resetClassifications(): void {
		$json = file_get_contents(__DIR__.'/../../src/things.json');
		/** @var string[] $things */
		$things = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
		$tags = [];
		foreach ($things as $tagName) {
			$tags[] = $this->tagManager->getTag($tagName, true, true);
		}
		$tags[] = $this->getProcessedTag();
		$tagIds = array_map(static fn (ISystemTag $tag) => $tag->getId(), $tags);
		$fileIds = $this->findClassifiedFiles();
		foreach ($fileIds as $id) {
			$this->objectMapper->unassignTags($id, 'files', $tagIds);
		}
	}

	/**
	 * @return void
	 */
	public function removeEmptyTags(): void {
		$tags = $this->tagManager->getAllTags();
		foreach ($tags as $tag) {
			$files = $this->objectMapper->getObjectIdsForTags($tag->getId(), 'files', 1);
			if (empty($files)) {
				$this->tagManager->deleteTags($tag->getId());
			}
		}
	}

	public function removeTags(array $tagNames) :void {
		$tags = $this->tagManager->getAllTags();
		foreach ($tags as $tag) {
			if (in_array($tag->getName(), $tagNames)) {
				$this->tagManager->deleteTags($tag->getId());
			}
		}
	}
}
