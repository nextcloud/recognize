<?php

namespace OCA\Recognize\Service;

use OCA\Recognize\BackgroundJobs\ClusterFacesJob;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Db\AccessUpdateMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

class AccessUpdateService {
	public const BATCH_SIZE = 1000;
	public function __construct(
		private AccessUpdateMapper $accessUpdateMapper,
		private LoggerInterface $logger,
		private StorageService  $storageService,
		private IUserMountCache $userMountCache,
		private IJobList $jobList,
		private FaceDetectionMapper $faceDetectionMapper,
		private IRootFolder $rootFolder,
	) {
	}

	public function processAccessUpdates(int $storageId): void {
		try {
			$updates = $this->accessUpdateMapper->findByStorageId($storageId, self::BATCH_SIZE);
		} catch (Exception $e) {
			$this->logger->error('Failed to retrieve access updates: ' . $e->getMessage(), ['exception' => $e]);
			return;
		}

		foreach ($updates as $update) {
			try {
				$this->onAccessUpdate($update->getStorageId(), $update->getRootId());
			} catch (Exception|InvalidPathException|NotFoundException $e) {
				$this->logger->warning('Failed to process access update: ' . $e->getMessage() . ' Continuing.', ['exception' => $e]);
			}
			try {
				$this->accessUpdateMapper->delete($update);
			} catch (Exception $e) {
				$this->logger->error('Failed to delete access update: ' . $e->getMessage(), ['exception' => $e]);
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
			$node = current($this->rootFolder->getById($fileInfo['fileid'])) ?: null;
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
}
