<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Hooks;

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
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Cache\CacheEntryInsertedEvent;
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
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\Events\ShareDeletedEvent;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;

class FileListener implements IEventListener {
	private FaceDetectionMapper $faceDetectionMapper;
	private LoggerInterface $logger;
	private QueueService $queue;
	private IgnoreService $ignoreService;
	private ?bool $movingFromIgnoredTerritory;
	private StorageService $storageService;
	private IManager $shareManager;
	private IUserMountCache $userMountCache;
	private IRootFolder $rootFolder;
	private array $nodeCache;

	public function __construct(FaceDetectionMapper $faceDetectionMapper, LoggerInterface $logger, QueueService $queue, IgnoreService $ignoreService, StorageService $storageService, IManager $shareManager, IUserMountCache $userMountCache, IRootFolder $rootFolder) {
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->logger = $logger;
		$this->queue = $queue;
		$this->ignoreService = $ignoreService;
		$this->movingFromIgnoredTerritory = null;
		$this->storageService = $storageService;
		$this->shareManager = $shareManager;
		$this->userMountCache = $userMountCache;
		$this->rootFolder = $rootFolder;
		$this->nodeCache = [];
	}

	public function handle(Event $event): void {
		if ($event instanceof ShareCreatedEvent) {
			$share = $event->getShare();
			$ownerId = $share->getShareOwner();
			$node = $share->getNode();

			$accessList = $this->shareManager->getAccessList($node, true, true);
			/**
			 * @var string[] $userIds
			 */
			$userIds = array_keys($accessList['users']);

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

			$accessList = $this->shareManager->getAccessList($node, true, true);
			/**
			 * @var string[] $userIds
			 */
			$userIds = array_keys($accessList['users']);

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
			if (in_array($event->getSource()->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true) &&
				in_array($event->getTarget()->getName(), [...Constants::IGNORE_MARKERS_ALL, ...Constants::IGNORE_MARKERS_IMAGE, ...Constants::IGNORE_MARKERS_AUDIO, ...Constants::IGNORE_MARKERS_VIDEO], true)) {
				$this->resetIgnoreCache($event->getSource());
				return;
			}
			// We try to remember whether the source node is in ignored territory
			// because after moving isIgnored doesn't work anymore :(
			if ($this->isIgnored($event->getSource())) {
				$this->movingFromIgnoredTerritory = true;
			} else {
				$this->movingFromIgnoredTerritory = false;
			}
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
			if ($this->movingFromIgnoredTerritory) {
				if ($this->isIgnored($event->getTarget())) {
					return;
				}
				$this->postInsert($event->getTarget());
				return;
			}
			if ($this->isIgnored($event->getTarget())) {
				$this->postDelete($event->getTarget());
				return;
			}
			return;
		}
		if ($event instanceof BeforeNodeDeletedEvent) {
			$this->postDelete($event->getNode(), false);
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
	}

	public function postDelete(Node $node, bool $recurse = true): void {
		if ($node->getType() === FileInfo::TYPE_FOLDER) {
			if (!$recurse) {
				return;
			}
			try {
				/** @var Folder $node */
				foreach ($node->getDirectoryListing() as $child) {
					$this->postDelete($child);
				}
			} catch (NotFoundException $e) {
				$this->logger->debug($e->getMessage(), ['exception' => $e]);
			}
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
	public function postInsert(Node $node, bool $recurse = true): void {
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

		$queueFile = new QueueFile();
		if ($node->getMountPoint()->getNumericStorageId() === null) {
			return;
		}
		$queueFile->setStorageId($node->getMountPoint()->getNumericStorageId());
		$queueFile->setRootId($node->getMountPoint()->getStorageRootId());

		if ($this->isIgnored($node)) {
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
	 * @param \OCP\Files\Node $node
	 * @return bool
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function isIgnored(Node $node) : bool {
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

		$relevantIgnorePaths = array_filter($ignoredPaths, static function (string $ignoredPath) use ($node) {
			return stripos($node->getInternalPath(), $ignoredPath ? $ignoredPath . '/' : $ignoredPath) === 0;
		});

		if (count($relevantIgnorePaths) > 0) {
			return true;
		}

		return false;
	}

	private function resetIgnoreCache(Node $node) : void {
		$storageId = $node->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			return;
		}
		$this->ignoreService->clearCacheForStorage($storageId);
	}
}
