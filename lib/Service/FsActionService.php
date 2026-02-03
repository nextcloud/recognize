<?php

namespace OCA\Recognize\Service;

use OC\Files\SetupManager;
use OC\User\NoUserException;
use OCA\Recognize\BackgroundJobs\ClusterFacesJob;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Constants;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\FsAccessUpdate;
use OCA\Recognize\Db\FsActionMapper;
use OCA\Recognize\Db\FsCreation;
use OCA\Recognize\Db\FsDeletion;
use OCA\Recognize\Db\FsMove;
use OCA\Recognize\Db\QueueFile;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Psr\Log\LoggerInterface;

final class FsActionService {
	public const BATCH_SIZE = 1000;
	public function __construct(
		private FsActionMapper      $fsActionMapper,
		private LoggerInterface     $logger,
		private StorageService      $storageService,
		private IUserMountCache     $userMountCache,
		private IJobList            $jobList,
		private FaceDetectionMapper $faceDetectionMapper,
		private IRootFolder         $rootFolder,
		private QueueService        $queue,
		private IgnoreService       $ignoreService,
	) {
	}

	/**
	 * @param class-string<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $className
	 */
	public function processActionsByClassAndStorageId(string $className, int $storageId): void {
		try {
			$actions = $this->fsActionMapper->findByStorageId($className, $storageId, self::BATCH_SIZE);
		} catch (Exception|\Exception $e) {
			$this->logger->error('Failed to retrieve access actions: ' . $e->getMessage(), ['exception' => $e]);
			return;
		}
		$this->processActions($actions);
	}

	/**
	 * @param class-string<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $className
	 */
	public function processActionsByClass(string $className): void {
		try {
			$actions = $this->fsActionMapper->find($className, self::BATCH_SIZE);
		} catch (Exception|\Exception $e) {
			$this->logger->error('Failed to retrieve access actions: ' . $e->getMessage(), ['exception' => $e]);
			return;
		}
		$this->processActions($actions);
	}

	/**
	 * @param array<FsCreation|FsDeletion|FsMove|FsAccessUpdate> $actions
	 */
	public function processActions(array $actions): void {
		$lastUserId = null;
		foreach ($actions as $action) {
			switch ($action::class) {
				case FsCreation::class:
					$this->logger->debug('Processing FsCreation action for storageId ' . $action->getStorageId() . ' and rootId ' . $action->getRootId());
					// Tear down to avoid memory leaks and OOMs
					// The fs event table is sorted by user ID, so we only need to tear down when the user ID changes
					$actionUserId = $action->getUserId();
					if ($actionUserId !== $lastUserId) {
						$lastUserId = $actionUserId;
						$setupManager = \OCP\Server::get(SetupManager::class);
						$setupManager->tearDown();
					}
					try {
						$rootNode = $this->rootFolder->getUserFolder($actionUserId)->getFirstNodeById($action->getRootId());
					} catch (NotPermittedException|NoUserException $e) {
						$this->logger->warning('Failed to find root node for creation action: ' . $e->getMessage(), ['exception' => $e]);
						break;
					}
					if ($rootNode === null) {
						$this->logger->info('Failed to find root node for creation action', ['nodeId' => $action->getRootId(), 'storageId' => $action->getStorageId()]);
						break;
					}
					try {
						$this->onCreation($rootNode); // todo add mimetypes filter here
					} catch (InvalidPathException $e) {
						$this->logger->warning('Failed to process creation action: ' . $e->getMessage() . ' Continuing.', ['exception' => $e]);
					}
					break;

				case FsDeletion::class:
					$this->logger->debug('Processing FsDeletion action for nodeId ' . $action->getNodeId());
					$this->onDeletion($action->getNodeId()); // todo add mimetypes filter here
					break;
				case FsAccessUpdate::class:
					$this->logger->debug('Processing FsAccessUpdate action for storageId ' . $action->getStorageId() . ' and rootId ' . $action->getRootId());
					try {
						$this->onAccessUpdate($action->getStorageId(), $action->getRootId());
					} catch (Exception|InvalidPathException|NotFoundException $e) {
						$this->logger->warning('Failed to process access update action: ' . $e->getMessage() . ' Continuing.', ['exception' => $e]);
					}
					break;
				case FsMove::class:
					$this->logger->debug('Processing FsMove action for nodeId ' . $action->getNodeId());
					$node = $this->rootFolder->getFirstNodeById($action->getNodeId());
					if ($node === null) {
						$this->logger->info('Failed to find root node for move action', ['nodeId' => $action->getNodeId()]);
						break;
					}
					try {
						$this->onMove($action->getOwner(), $action->getAddedUsers(), $action->getTargetUsers(), $node);
					} catch (Exception|InvalidPathException|NotFoundException $e) {
						$this->logger->warning('Failed to process move action: ' . $e->getMessage() . ' Continuing.', ['exception' => $e]);
					}
					break;
				default:
					$this->logger->error('Failed to process action: Unkown action type ' . $action::class);
					break;
			}

			try {
				$this->fsActionMapper->delete($action);
			} catch (Exception $e) {
				$this->logger->error('Failed to delete access action: ' . $e->getMessage(), ['exception' => $e]);
			}
		}
	}


	/**
	 * @param int $nodeId
	 * @return list<string>
	 */
	private function getUsersWithFileAccess(int $nodeId): array {
		$this->userMountCache->clear();
		$mountInfos = $this->userMountCache->getMountsForFileId($nodeId);
		$userIds = array_map(static function (ICachedMountInfo $mountInfo) {
			return $mountInfo->getUser()->getUID();
		}, $mountInfos);

		return array_values(array_unique($userIds));
	}

	/**
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 * @throws Exception
	 */
	private function onAccessUpdate(int $storageId, int $rootId): void {
		$userIds = $this->getUsersWithFileAccess($rootId);
		$files = $this->storageService->getFilesInMount($storageId, $rootId, [ClusteringFaceClassifier::MODEL_NAME], 0, 0);
		$userIdsToScheduleClustering = [];
		foreach ($files as $fileInfo) {
			$node = $this->rootFolder->getFirstNodeById($fileInfo['fileid']) ?: null;
			$ownerId = $node?->getOwner()?->getUID();
			if ($ownerId === null) {
				continue;
			}
			$detectionsForFile = $this->faceDetectionMapper->findByFileId($fileInfo['fileid']);
			$userHasDetectionForFile = [];
			foreach ($detectionsForFile as $detection) {
				$userHasDetectionForFile[$detection->getUserId()] = true;
			}
			foreach ($userIds as $userId) {
				if ($userId === $ownerId) {
					continue;
				}
				if ($userHasDetectionForFile[$userId] ?? false) {
					continue;
				}
				$this->faceDetectionMapper->copyDetectionsForFileFromUserToUser($fileInfo['fileid'], $ownerId, $userId);
				$userIdsToScheduleClustering[$userId] = true;
			}
			$this->faceDetectionMapper->removeDetectionsForFileFromUsersNotInList($fileInfo['fileid'], $userIds);
		}
		foreach (array_keys($userIdsToScheduleClustering) as $userId) {
			$this->jobList->add(ClusterFacesJob::class, ['userId' => $userId]);
		}
	}

	/**
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function onCreation(Node $node, bool $recurse = true, ?array $mimeTypes = null): void {
		if ($node->getType() === FileInfo::TYPE_FOLDER) {
			if (!$recurse) {
				return;
			}
			// For normal inserts we probably get one event per node, but, when removing an ignore file,
			// we only get the folder passed here, so we recurse.
			try {
				/** @var Folder $node */
				foreach ($node->getDirectoryListing() as $child) {
					$this->onCreation($child);
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
		$storageId = $node->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			$this->logger->debug('Storage ID is null for node ' . $node->getId());
			return;
		}
		$queueFile->setStorageId($storageId);
		$queueFile->setRootId($node->getMountPoint()->getStorageRootId());

		if ($this->isFileIgnored($node)) {
			$this->logger->debug('File ignored, skipping: ' . $node->getId());
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

	public function onDeletion(int $nodeId, ?array $mimeTypes = null): void {
		// Try Deleting possibly existing face detections
		try {
			/**
			 * @var \OCA\Recognize\Db\FaceDetection[] $faceDetections
			 */
			$faceDetections = $this->faceDetectionMapper->findByFileId($nodeId);
			foreach ($faceDetections as $detection) {
				$this->logger->debug('Delete face detection ' . $detection->getId());
				$this->faceDetectionMapper->delete($detection);
			}
		} catch (Exception $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
		}

		// Try removing file from possibly existing queue entries
		try {
			$this->queue->removeFileFromAllQueues($nodeId);
		} catch (Exception $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
		}
	}

	/**
	 * @param string $ownerId
	 * @param list<string> $usersToAdd
	 * @param list<string> $targetUserIds
	 * @param Node $node
	 * @return void
	 * @throws Exception|InvalidPathException|NotFoundException
	 */
	private function onMove(string $ownerId, array $usersToAdd, array $targetUserIds, Node $node): void {
		if ($node instanceof Folder) {
			try {
				foreach ($node->getDirectoryListing() as $n) {
					if (!in_array($n->getMimetype(), Constants::IMAGE_FORMATS)) {
						continue;
					}
					$this->onMove($ownerId, $usersToAdd, $targetUserIds, $n);
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
}
