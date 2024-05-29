<?php
/*
 * Copyright (c) 2021-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Classifiers\Images;

use OCA\Recognize\BackgroundJobs\ClusterFacesJob;
use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IPreview;
use OCP\ITempManager;
use OCP\Share\IManager;

class ClusteringFaceClassifier extends Classifier {
	public const IMAGE_TIMEOUT = 120; // seconds
	public const IMAGE_PUREJS_TIMEOUT = 360; // seconds
	public const MIN_FACE_RECOGNITION_SCORE = 0.9;

	public const MAX_FACE_YAW = 50;
	public const MAX_FACE_ROLL = 30;

	public const MODEL_NAME = 'faces';

	public function __construct(
		Logger $logger,
		IAppConfig $config,
		private FaceDetectionMapper $faceDetections,
		QueueService $queue, IRootFolder $rootFolder,
		private IUserMountCache $userMountCache, private IJobList $jobList, ITempManager $tempManager, IPreview $previewProvider,
		private IManager $shareManager) {
		parent::__construct($logger, $config, $rootFolder, $queue, $tempManager, $previewProvider);
	}

	/**
	 * @param Node $node
	 * @return list<string>
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	private function getUsersWithFileAccess(Node $node): array {
		/** @var array{users:array<string,array{node_id:int, node_path: string}>, remote: array<string,array{node_id:int, node_path: string}>, mail: array<string,array{node_id:int, node_path: string}>} $accessList */
		$accessList = $this->shareManager->getAccessList($node, true, true);
		$userIds = array_map(fn ($id) => strval($id), array_keys($accessList['users']));

		$mountInfos = $this->userMountCache->getMountsForFileId($node->getId());
		$userIds += array_map(static function (ICachedMountInfo $mountInfo) {
			return $mountInfo->getUser()->getUID();
		}, $mountInfos);

		return array_values(array_unique($userIds));
	}

	/**
	 * @param string $user
	 * @param \OCA\Recognize\Db\QueueFile[] $queueFiles
	 * @throws \ErrorException|\RuntimeException
	 * @return void
	 */
	public function classify(array $queueFiles): void {
		if ($this->config->getAppValueString('tensorflow.purejs', 'false') === 'true') {
			$timeout = self::IMAGE_PUREJS_TIMEOUT;
		} else {
			$timeout = self::IMAGE_TIMEOUT;
		}

		$filteredQueueFiles = [];
		foreach ($queueFiles as $queueFile) {
			try {
				$facesByFileCount = count($this->faceDetections->findByFileId($queueFile->getFileId()));
			} catch (Exception $e) {
				$this->logger->debug('finding faces by file '.$queueFile->getFileId().' failed', ['exception' => $e]);
				$facesByFileCount = 1;
			}
			if ($facesByFileCount !== 0) {
				try {
					$this->logger->debug('Remove file with existing faces from queue '.$queueFile->getFileId());
					$this->queue->removeFromQueue(self::MODEL_NAME, $queueFile);
				} catch (Exception $e) {
					$this->logger->error('Could not remove file from queue', ['exception' => $e]);
				}
				continue;
			}
			$filteredQueueFiles[] = $queueFile;
		}

		$usersToCluster = [];
		$classifierProcess = $this->classifyFiles(self::MODEL_NAME, $filteredQueueFiles, $timeout);

		/**
		 * @var list<array> $faces
		 */
		foreach ($classifierProcess as $queueFile => $faces) {
			$this->logger->debug('Face results for '.$queueFile->getFileId().' are in');
			foreach ($faces as $face) {
				if ($face['score'] < self::MIN_FACE_RECOGNITION_SCORE) {
					$this->logger->debug('Face score too low. continuing with next face.');
					continue;
				}
				if (abs($face['angle']['roll']) > self::MAX_FACE_ROLL || abs($face['angle']['yaw']) > self::MAX_FACE_YAW) {
					$this->logger->debug('Face is not straight. continuing with next face.');
					continue;
				}

				try {
					$userIds = $this->getUsersWithFileAccess($this->rootFolder->getFirstNodeById($queueFile->getFileId()));
				} catch (InvalidPathException|NotFoundException $e) {
					$userIds = [];
				}

				// Insert face detection for all users with access
				foreach ($userIds as $userId) {
					$this->logger->debug('preparing face detection for user '.$userId);
					$faceDetection = new FaceDetection();
					$faceDetection->setX($face['x']);
					$faceDetection->setY($face['y']);
					$faceDetection->setWidth($face['width']);
					$faceDetection->setHeight($face['height']);
					$faceDetection->setVector($face['vector']);
					$faceDetection->setFileId($queueFile->getFileId());
					$faceDetection->setUserId($userId);
					try {
						$this->faceDetections->insert($faceDetection);
					} catch (Exception $e) {
						$this->logger->error('Could not store face detection in database', ['exception' => $e]);
						continue;
					}
					$usersToCluster[$userId] = true;
				}
				$this->config->setAppValueString(self::MODEL_NAME.'.status', 'true');
				$this->config->setAppValueString(self::MODEL_NAME.'.lastFile', (string)time());
			}
		}

		$usersToCluster = array_keys($usersToCluster);
		foreach ($usersToCluster as $userId) {
			if (!$this->jobList->has(ClusterFacesJob::class, ['userId' => $userId])) {
				$this->logger->debug('scheduling ClusterFacesJob for user '.$userId);
				$this->jobList->add(ClusterFacesJob::class, ['userId' => $userId]);
			}
		}
		$this->logger->debug('face classifier end');
	}
}
