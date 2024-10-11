<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Hooks;

use OCA\Recognize\BackgroundJobs\ClusterFacesJob;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Constants;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\IgnoreService;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\StorageService;
use OCP\BackgroundJob\IJobList;
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
use OCP\IGroupManager;
use OCP\Share\Events\ShareAcceptedEvent;
use OCP\Share\Events\ShareDeletedEvent;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class FileListener implements IEventListener {
	private ?bool $movingFromIgnoredTerritory;
	private ?array $movingDirFromIgnoredTerritory;
	/** @var list<string> */
	private array $sourceUserIds;
	private ?Node $source = null;

	public function __construct(
		private FaceDetectionMapper $faceDetectionMapper,
		private LoggerInterface $logger,
		private QueueService $queue,
		private IgnoreService $ignoreService,
		private StorageService $storageService,
		private IRootFolder $rootFolder,
		private IUserMountCache $userMountCache,
		private IManager $shareManager,
		private IGroupManager $groupManager,
		private IJobList $jobList,
	) {
		$this->movingFromIgnoredTerritory = null;
		$this->movingDirFromIgnoredTerritory = null;
		$this->sourceUserIds = [];
	}

	/**
	 * @param Node $node
	 * @return list<string>
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	private function getUsersWithFileAccess(Node $node): array {
		$this->userMountCache->clear();
		$mountInfos = $this->userMountCache->getMountsForFileId($node->getId());
		$userIds = array_map(static function (ICachedMountInfo $mountInfo) {
			return $mountInfo->getUser()->getUID();
		}, $mountInfos);

		return array_values(array_unique($userIds));
	}

	public function handle(Event $event): void {
		try {
			if ($event instanceof ShareAcceptedEvent) {
				$share = $event->getShare();
				$ownerId = $share->getShareOwner();
				$node = $share->getNode();
				$userIds = $this->getUsersWithFileAccess($node);

				if ($node->getType() === FileInfo::TYPE_FOLDER) {
					$mount = $node->getMountPoint();
					if ($mount->getNumericStorageId() === null) {
						return;
					}
					$files = $this->storageService->getFilesInMount($mount->getNumericStorageId(), $node->getId(), [ClusteringFaceClassifier::MODEL_NAME], 0, 0);
					foreach ($files as $fileInfo) {
						foreach ($userIds as $userId) {
							if ($userId === $ownerId) {
								continue;
							}
							if (count($this->faceDetectionMapper->findByFileIdAndUser($node->getId(), $userId)) > 0) {
								continue;
							}
							$this->faceDetectionMapper->copyDetectionsForFileFromUserToUser($fileInfo['fileid'], $ownerId, $userId);
						}
					}
				} else {
					foreach ($userIds as $userId) {
						if ($userId === $ownerId) {
							continue;
						}
						if (count($this->faceDetectionMapper->findByFileIdAndUser($node->getId(), $userId)) > 0) {
							continue;
						}
						$this->faceDetectionMapper->copyDetectionsForFileFromUserToUser($node->getId(), $ownerId, $userId);
					}
				}
			}
			if ($event instanceof ShareDeletedEvent) {
				$share = $event->getShare();
				$node = $share->getNode();
				$userIds = $this->getUsersWithFileAccess($node);

				if ($node->getType() === FileInfo::TYPE_FOLDER) {
					$mount = $node->getMountPoint();
					if ($mount->getNumericStorageId() === null) {
						return;
					}
					$files = $this->storageService->getFilesInMount($mount->getNumericStorageId(), $node->getId(), [ClusteringFaceClassifier::MODEL_NAME], 0, 0);
					foreach ($files as $fileInfo) {
						$this->faceDetectionMapper->removeDetectionsForFileFromUsersNotInList($fileInfo['fileid'], $userIds);
					}
				} else {
					$this->faceDetectionMapper->removeDetectionsForFileFromUsersNotInList($node->getId(), $userIds);
				}
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
				$this->sourceUserIds = $this->getUsersWithFileAccess($event->getSource());
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

		// Try Deleting possibly existing face detections
		try {
			/**
			 * @var \OCA\Recognize\Db\FaceDetection[] $faceDetections
			 */
			$faceDetections = $this->faceDetectionMapper->findByFileId($node->getId());
			foreach ($faceDetections as $detection) {
				$this->logger->debug('Delete face detection ' . $detection->getId());
				$this->faceDetectionMapper->delete($detection);
			}
		} catch (NotFoundException $e) {
			$this->logger->debug($e->getMessage(), ['exception' => $e]);
		} catch (Exception|InvalidPathException $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
		}

		// Try removing file from possibly existing queue entries
		try {
			$this->queue->removeFileFromAllQueues($node->getId());
		} catch (NotFoundException $e) {
			$this->logger->debug($e->getMessage(), ['exception' => $e]);
		} catch (Exception|InvalidPathException $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
		}
	}

	/**
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function postInsert(Node $node, bool $recurse = true, ?array $mimeTypes = null): void {
		if ($node->getType() === FileInfo::TYPE_FOLDER) {
			if (!$recurse) {
				return;
			}
			// For normal inserts we probably get one event per node, but, when removing an ignore file,
			// we only get the folder passed here, so we recurse.
			try {
				/** @var Folder $node */
				foreach ($node->getDirectoryListing() as $child) {
					$this->postInsert($child);
				}
			} catch (NotFoundException $e) {
				$this->logger->debug($e->getMessage(), ['exception' => $e]);
			}
			return;
		}

		if ($mimeTypes !== null && !in_array($node->getMimetype(), $mimeTypes)) {
			return;
		}

		$queueFile = new QueueFile();
		if ($node->getMountPoint()->getNumericStorageId() === null) {
			return;
		}
		$queueFile->setStorageId($node->getMountPoint()->getNumericStorageId());
		$queueFile->setRootId($node->getMountPoint()->getStorageRootId());

		if ($this->isFileIgnored($node)) {
			return;
		}

		try {
			$queueFile->setFileId($node->getId());
		} catch (InvalidPathException|NotFoundException $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			return;
		}

		$queueFile->setUpdate(false);
		try {
			if (in_array($node->getMimetype(), Constants::IMAGE_FORMATS)) {
				$this->queue->insertIntoQueue(ImagenetClassifier::MODEL_NAME, $queueFile);
				$this->queue->insertIntoQueue(ClusteringFaceClassifier::MODEL_NAME, $queueFile);
			}
			if (in_array($node->getMimetype(), Constants::VIDEO_FORMATS)) {
				$this->queue->insertIntoQueue(MovinetClassifier::MODEL_NAME, $queueFile);
			}
			if (in_array($node->getMimetype(), Constants::AUDIO_FORMATS)) {
				$this->queue->insertIntoQueue(MusicnnClassifier::MODEL_NAME, $queueFile);
			}
		} catch (Exception $e) {
			$this->logger->error('Failed to add file to queue', ['exception' => $e]);
			return;
		}
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function postRename(Node $source, Node $target): void {
		$targetUserIds = $this->getUsersWithFileAccess($target);

		$usersToAdd = array_values(array_diff($targetUserIds, $this->sourceUserIds));
		$existingUsers = array_diff($targetUserIds, $usersToAdd);
		$sourceOwner = $source->getOwner();
		$targetOwner = $target->getOwner();
		$ownerId = $sourceOwner !== null ? $sourceOwner->getUID() : ($targetOwner ? $targetOwner->getUID() : $existingUsers[0]);

		$this->copyFaceDetectionsForNode($ownerId, $usersToAdd, $targetUserIds, $target);
	}

	/**
	 * @param string $ownerId
	 * @param list<string> $usersToAdd
	 * @param list<string> $targetUserIds
	 * @param Node $node
	 * @return void
	 * @throws Exception|InvalidPathException|NotFoundException
	 */
	private function copyFaceDetectionsForNode(string $ownerId, array $usersToAdd, array $targetUserIds, Node $node): void {
		if ($node instanceof Folder) {
			try {
				foreach ($node->getDirectoryListing() as $node) {
					if (!in_array($node->getMimetype(), Constants::IMAGE_FORMATS)) {
						continue;
					}
					$this->copyFaceDetectionsForNode($ownerId, $usersToAdd, $targetUserIds, $node);
				}
			} catch (NotFoundException|Exception|InvalidPathException $e) {
				$this->logger->warning('Error in recognize file listener', ['exception' => $e]);
			}
			return;
		}
		foreach ($usersToAdd as $userId) {
			if (count($this->faceDetectionMapper->findByFileIdAndUser($node->getId(), $userId)) > 0) {
				continue;
			}
			$this->faceDetectionMapper->copyDetectionsForFileFromUserToUser($node->getId(), $ownerId, $userId);
			$this->jobList->add(ClusterFacesJob::class, ['userId' => $userId]);
		}
		$this->faceDetectionMapper->removeDetectionsForFileFromUsersNotInList($node->getId(), $targetUserIds);
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
}
