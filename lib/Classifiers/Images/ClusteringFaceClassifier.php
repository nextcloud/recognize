<?php
/*
 * Copyright (c) 2021-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Classifiers\Images;

use OCA\Recognize\BackgroundJobs\ClusterFacesJob;
use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IPreview;
use OCP\ITempManager;

class ClusteringFaceClassifier extends Classifier {
	public const IMAGE_TIMEOUT = 120; // seconds
	public const IMAGE_PUREJS_TIMEOUT = 360; // seconds
	public const MIN_FACE_RECOGNITION_SCORE = 0.9;

	public const MAX_FACE_YAW = 50;
	public const MAX_FACE_ROLL = 30;

	public const MODEL_NAME = 'faces';

	private FaceDetectionMapper $faceDetections;
	private IUserMountCache $userMountCache;
	private IJobList $jobList;

	public function __construct(Logger $logger, IConfig $config, FaceDetectionMapper $faceDetections, QueueService $queue, IRootFolder $rootFolder, IUserMountCache $userMountCache, IJobList $jobList, ITempManager $tempManager, IPreview $previewProvider) {
		parent::__construct($logger, $config, $rootFolder, $queue, $tempManager, $previewProvider);
		$this->faceDetections = $faceDetections;
		$this->userMountCache = $userMountCache;
		$this->jobList = $jobList;
	}

	/**
	 * @param string $user
	 * @param \OCA\Recognize\Db\QueueFile[] $queueFiles
	 * @throws \ErrorException|\RuntimeException
	 * @return void
	 */
	public function classify(array $queueFiles): void {
		if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true') {
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

				$mountInfos = $this->userMountCache->getMountsForFileId($queueFile->getFileId());
				$userIds = array_map(static function (ICachedMountInfo $mountInfo) {
					return $mountInfo->getUser()->getUID();
				}, $mountInfos);

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
					$usersToCluster[] = $userId;
				}
				$this->config->setAppValue('recognize', self::MODEL_NAME.'.status', 'true');
				$this->config->setAppValue('recognize', self::MODEL_NAME.'.lastFile', time());
			}
		}

		$usersToCluster = array_unique($usersToCluster);
		foreach ($usersToCluster as $userId) {
			if (!$this->jobList->has(ClusterFacesJob::class, ['userId' => $userId])) {
				$this->logger->debug('scheduling ClusterFacesJob for user '.$userId);
				$this->jobList->add(ClusterFacesJob::class, ['userId' => $userId]);
			}
		}
		$this->logger->debug('face classifier end');
	}
}
