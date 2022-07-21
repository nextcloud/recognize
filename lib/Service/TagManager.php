<?php

namespace OCA\Recognize\Service;

use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;

class TagManager {
	public const RECOGNIZED_TAG = 'Tagged by recognize v2.1.2';

	private ISystemTagManager $tagManager;
	private ISystemTagObjectMapper $objectMapper;

	public function __construct(ISystemTagManager $systemTagManager, ISystemTagObjectMapper $objectMapper) {
		$this->tagManager = $systemTagManager;
		$this->objectMapper = $objectMapper;
	}

	/**
	 * @param $name
	 * @return \OCP\SystemTag\ISystemTag
	 */
	public function getTag($name) : ISystemTag {
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
			$this->objectMapper->unassignTags($fileId, 'files', [$this->getTag($oldName)->getId()]);
		}
		$this->assignTags($fileId, [$name]);
	}

	/**
	 * @param int $fileId
	 * @param array $tags
	 * @return void
	 */
	public function assignTags(int $fileId, array $tags): void {
		$tags = array_map(function ($tag) : string {
			return $this->getTag($tag)->getId();
		}, $tags);
		$tags[] = $this->getProcessedTag()->getId();
		$oldTags = $this->objectMapper->getTagIdsForObjects([$fileId], 'files')[$fileId];
		$this->objectMapper->assignTags($fileId, 'files', array_unique(array_merge($tags, $oldTags)));
	}

	/**
	 * @param array $fileIds
	 * @return array
	 */
	public function getTagsForFiles(array $fileIds): array {
		return array_map(function ($tags) : array {
			return $this->tagManager->getTagsByIds($tags);
		}, $this->objectMapper->getTagIdsForObjects($fileIds, 'files'));
	}

	/**
	 * @return array
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
			$missedChunk = array_keys(array_filter($this->objectMapper->getTagIdsForObjects($classifiedChunk, 'files'), function ($tags) use ($processedId) : bool {
				return count($tags) === 1 && $tags[0] !== $processedId;
			}));
			$missed = array_merge($missed, $missedChunk);
		}
		return $missed;
	}

	/**
	 * @return void
	 */
	public function resetClassifications(): void {
		$fileIds = $this->findClassifiedFiles();
		foreach ($fileIds as $id) {
			$tagIds = $this->objectMapper->getTagIdsForObjects($id, 'files');
			$this->objectMapper->unassignTags($id, 'files', $tagIds[$id]);
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
}
