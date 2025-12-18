<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Hooks;

use OCA\Recognize\Constants;
use OCA\Recognize\Db\FsActionMapper;
use OCA\Recognize\Service\IgnoreService;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Cache\CacheEntryInsertedEvent;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\NodeRemovedFromCache;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
final class FileListener implements IEventListener {
	private ?bool $movingFromIgnoredTerritory;
	private ?array $movingDirFromIgnoredTerritory;
	/** @var list<string> */
	private array $sourceUserIds;
	private ?Node $source = null;

	/** @var array<string, bool>  */
	private array $addedMounts = [];

	public function __construct(
		private LoggerInterface     $logger,
		private IgnoreService       $ignoreService,
		private IRootFolder         $rootFolder,
		private IUserMountCache     $userMountCache,
		private FsActionMapper      $fsActionMapper,
	) {
		$this->movingFromIgnoredTerritory = null;
		$this->movingDirFromIgnoredTerritory = null;
		$this->sourceUserIds = [];
	}

	/**
	 * @param int $nodeId
	 * @return list<string>
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	private function getUsersWithFileAccess(int $nodeId): array {
		$this->userMountCache->clear();
		$mountInfos = $this->userMountCache->getMountsForFileId($nodeId);
		$userIds = array_map(static function (ICachedMountInfo $mountInfo) {
			return $mountInfo->getUser()->getUID();
		}, $mountInfos);

		return array_values(array_unique($userIds));
	}

	public function handle(Event $event): void {
		try {
			if ($event instanceof \OCP\Files\Config\Event\UserMountAddedEvent) {
				$rootId = $event->mountPoint->getRootId();
				// Asynchronous, because we potentially recurse and this event needs to be handled fast
				$this->onAccessUpdate($event->mountPoint->getStorageId(), $rootId);
				// Remember that this mount was added in the current process (see UserMountRemovedEvent below)
				$this->addedMounts[$event->mountPoint->getUser()->getUID() . '-' . $rootId] = true;
			}

			if ($event instanceof \OCP\Files\Config\Event\UserMountRemovedEvent) {
				// If we just added this mount, ignore the removal, as the 'removal' event is always fired after
				// the 'added' event in server
				$rootId = $event->mountPoint->getRootId();
				$mountKey = $event->mountPoint->getUser()->getUID() . '-' . $rootId;
				if (array_key_exists($mountKey, $this->addedMounts) && $this->addedMounts[$mountKey] === true) {
					return;
				}
				// Asynchronous, because we potentially recurse and this event needs to be handled fast
				$this->onAccessUpdate($event->mountPoint->getStorageId(), $rootId);
			}
			
			if ($event instanceof BeforeNodeRenamedEvent) {
				$this->movingFromIgnoredTerritory = null;
				$this->movingDirFromIgnoredTerritory = [];
				if (in_array($event->getSource()->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true)) {
					$this->resetIgnoreCache($event->getSource());
					return;
				}
				// We try to remember whether the source node is in ignored territory
				// because after moving isIgnored doesn't work anymore :(
				if ($event->getSource()->getType() !== FileInfo::TYPE_FOLDER) {
					if ($this->isFileIgnored($event->getSource())) {
						$this->movingFromIgnoredTerritory = true;
					} else {
						$this->movingFromIgnoredTerritory = false;
					}
				} else {
					$this->movingDirFromIgnoredTerritory = $this->getDirIgnores($event->getSource());
				}
				$this->sourceUserIds = $this->getUsersWithFileAccess($event->getSource()->getId());
				$this->source = $event->getSource();
				return;
			}
			if ($event instanceof NodeRenamedEvent) {
				if (in_array($event->getSource()->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true) &&
					in_array($event->getTarget()->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true)) {
					$this->resetIgnoreCache($event->getTarget());
					$this->postInsert($event->getSource()->getParent());
					$this->postDelete($event->getTarget()->getParent());
					return;
				}

				if (in_array($event->getSource()->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true) &&
					!in_array($event->getTarget()->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true)) {
					$this->postInsert($event->getSource()->getParent());
					return;
				}

				if (!in_array($event->getSource()->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true) &&
					in_array($event->getTarget()->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true)) {
					$this->resetIgnoreCache($event->getTarget());
					$this->postDelete($event->getTarget()->getParent());
					return;
				}
				if ($event->getTarget()->getType() !== FileInfo::TYPE_FOLDER) {
					if ($this->movingFromIgnoredTerritory) {
						if ($this->isFileIgnored($event->getTarget())) {
							return;
						}
						$this->postInsert($event->getTarget());
						return;
					}
					if ($this->isFileIgnored($event->getTarget())) {
						$this->postDelete($event->getTarget());
						return;
					}
				} else {
					if ($this->movingDirFromIgnoredTerritory !== null && count($this->movingDirFromIgnoredTerritory) !== 0) {
						$oldIgnores = $this->movingDirFromIgnoredTerritory;
						$newIgnores = $this->getDirIgnores($event->getTarget());
						$diff1 = array_diff($newIgnores, $oldIgnores);
						$diff2 = array_diff($oldIgnores, $newIgnores);
						if (count($diff1) !== 0 || count($diff2) !== 0) {
							if (count($diff1) !== 0) {
								$this->postDelete($event->getTarget(), true, $diff1);
							}
							if (count($diff2) !== 0) {
								$this->postInsert($event->getTarget(), true, $diff2);
							}
						}
						return;
					}
					$ignoredMimeTypes = $this->getDirIgnores($event->getTarget());
					if (!empty($ignoredMimeTypes)) {
						$this->postDelete($event->getTarget(), true, $ignoredMimeTypes);
						return;
					}
				}
				$this->postRename($this->source ?? $event->getSource(), $event->getTarget());
				return;
			}
			if ($event instanceof BeforeNodeDeletedEvent) {
				$this->postDelete($event->getNode());
				return;
			}
			if ($event instanceof NodeDeletedEvent) {
				if (in_array($event->getNode()->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true)) {
					$this->resetIgnoreCache($event->getNode());
					$this->postInsert($event->getNode()->getParent());
					return;
				}
			}
			if ($event instanceof NodeCreatedEvent) {
				if (in_array($event->getNode()->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true)) {
					$this->resetIgnoreCache($event->getNode());
					$this->postDelete($event->getNode()->getParent());
					return;
				}
				$this->postInsert($event->getNode(), false);
				return;
			}
			if ($event instanceof CacheEntryInsertedEvent) {
				$node = current($this->rootFolder->getById($event->getFileId()));
				if ($node === false) {
					return;
				}
				if ($node instanceof Folder) {
					return;
				}
				if (in_array($node->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true)) {
					$this->resetIgnoreCache($node);
					$this->postDelete($node->getParent());
					return;
				}
				$this->postInsert($node);
			}
			if ($event instanceof NodeRemovedFromCache) {
				$cacheEntry = $event->getStorage()->getCache()->get($event->getPath());
				if ($cacheEntry === false) {
					return;
				}
				$node = current($this->rootFolder->getById($cacheEntry->getId()));
				if ($node === false) {
					return;
				}
				if (in_array($node->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true)) {
					$this->resetIgnoreCache($node);
					$this->postInsert($node->getParent());
					return;
				}
				$this->postDelete($node);
			}
		} catch (\Throwable $e) {
			$this->logger->error('Error in recognize file listener', ['exception' => $e]);
		}
	}

	public function postDelete(Node $node, bool $recurse = true, ?array $mimeTypes = null): void {
		if ($node->getType() === FileInfo::TYPE_FOLDER) {
			// We have to recurse synchronously here, because the nodes will be gone by the time the deletion action is handled
			if (!$recurse) {
				return;
			}
			try {
				/** @var Folder $node */
				foreach ($node->getDirectoryListing() as $child) {
					$this->postDelete($child, true, $mimeTypes);
				}
			} catch (NotFoundException $e) {
				$this->logger->debug($e->getMessage(), ['exception' => $e]);
			}
			return;
		}

		if ($mimeTypes !== null && !in_array($node->getMimetype(), $mimeTypes)) {
			return;
		}

		$this->fsActionMapper->insertDeletion($node->getMountPoint()->getNumericStorageId(), $node->getId());
	}

	/**
	 * @throws \OCP\Files\InvalidPathException
	 * @throws NotFoundException
	 */
	public function postInsert(Node $node, bool $recurse = true, ?array $mimeTypes = null): void {
		if ($node->getType() !== FileInfo::TYPE_FOLDER && $mimeTypes !== null && !in_array($node->getMimetype(), $mimeTypes)) {
			return;
		}
		$storageId = $node->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			return;
		}
		if (preg_match('#^/[^/]*?/files($|/)#', $node->getPath()) !== 1 && preg_match('#^/groupfolders/#', $node->getPath()) !== 1) {
			return;
		}
		$this->fsActionMapper->insertCreation($storageId, $node->getId());
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function postRename(Node $source, Node $target): void {
		$targetUserIds = $this->getUsersWithFileAccess($target->getId());

		$usersToAdd = array_values(array_diff($targetUserIds, $this->sourceUserIds));
		$existingUsers = array_diff($targetUserIds, $usersToAdd);
		$sourceOwner = $source->getOwner();
		$targetOwner = $target->getOwner();
		$ownerId = $sourceOwner?->getUID() ?? $targetOwner?->getUID() ?? $existingUsers[0];

		if (preg_match('#^/[^/]*?/files/#', $target->getPath()) !== 1 && preg_match('#^/groupfolders/#', $target->getPath()) !== 1) {
			return;
		}

		$this->fsActionMapper->insertMove($target->getId(), $ownerId, $usersToAdd, $targetUserIds);
	}

	/**
	 * @param \OCP\Files\Node $node
	 * @return bool
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function isFileIgnored(Node $node) : bool {
		$ignoreMarkers = [];
		$mimeType = $node->getMimetype();
		$storageId = $node->getMountPoint()->getNumericStorageId();

		if ($storageId === null) {
			return true;
		}

		if (in_array($mimeType, Constants::IMAGE_FORMATS)) {
			$ignoreMarkers = array_merge($ignoreMarkers, Constants::IGNORE_MARKERS_IMAGE);
		}
		if (in_array($mimeType, Constants::VIDEO_FORMATS)) {
			$ignoreMarkers = array_merge($ignoreMarkers, Constants::IGNORE_MARKERS_VIDEO);
		}
		if (in_array($mimeType, Constants::AUDIO_FORMATS)) {
			$ignoreMarkers = array_merge($ignoreMarkers, Constants::IGNORE_MARKERS_AUDIO);
		}

		if (count($ignoreMarkers) === 0) {
			return true;
		}

		$ignoreMarkers = array_merge($ignoreMarkers, Constants::IGNORE_MARKERS_ALL);
		$ignoredPaths = $this->ignoreService->getIgnoredDirectories($storageId, $ignoreMarkers);


		foreach ($ignoredPaths as $ignoredPath) {
			if (stripos($node->getInternalPath(), $ignoredPath ? $ignoredPath . '/' : $ignoredPath) === 0) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param \OCP\Files\Node $node
	 * @return array
	 * @throws Exception
	 */
	public function getDirIgnores(Node $node) : array {
		$storageId = $node->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			return [];
		}

		$ignoredMimeTypes = [];
		foreach ([
			[Constants::IGNORE_MARKERS_IMAGE, Constants::IMAGE_FORMATS],
			[Constants::IGNORE_MARKERS_VIDEO, Constants::VIDEO_FORMATS],
			[Constants::IGNORE_MARKERS_AUDIO, Constants::AUDIO_FORMATS],
			[Constants::IGNORE_MARKERS_ALL, array_merge(Constants::IMAGE_FORMATS, Constants::VIDEO_FORMATS, Constants::AUDIO_FORMATS)],
		] as $iteration) {
			[$ignoreMarkers, $mimeTypes] = $iteration;
			$ignoredPaths = $this->ignoreService->getIgnoredDirectories($storageId, $ignoreMarkers);
			foreach ($ignoredPaths as $ignoredPath) {
				if (stripos($node->getInternalPath(), $ignoredPath ? $ignoredPath . '/' : $ignoredPath) === 0) {
					$ignoredMimeTypes = array_unique(array_merge($ignoredMimeTypes, $mimeTypes));
				}
			}
		}

		return $ignoredMimeTypes;
	}

	private function resetIgnoreCache(Node $node) : void {
		$storageId = $node->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			return;
		}
		$this->ignoreService->clearCacheForStorage($storageId);
	}

	/**
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	private function onAccessUpdate(int $storageId, int $rootId): void {
		$node = $this->rootFolder->getFirstNodeById($rootId);
		if (!$node || (preg_match('#^/[^/]*?/files($|/)#', $node->getPath()) !== 1 && preg_match('#^/groupfolders/#', $node->getPath()) !== 1)) {
			return;
		}
		$this->fsActionMapper->insertAccessUpdate($storageId, $rootId);
	}
}
