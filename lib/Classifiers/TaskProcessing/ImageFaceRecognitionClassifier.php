<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Recognize\Classifiers\TaskProcessing;

use OCA\Recognize\Classifiers\AbstractTaskProcessingClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\TaskProcessing\ImageFaceRecognitionTaskType;
use OCP\Files\Config\IUserMountCache;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use Psr\Log\LoggerInterface;

final class ImageFaceRecognitionClassifier extends AbstractTaskProcessingClassifier {
	public const MIN_FACE_RECOGNITION_SCORE = 0.5;

	public const MIN_DATASET_SIZE = 120;
	public const MIN_DETECTION_SIZE = 0.03;
	public const MIN_CLUSTER_SEPARATION = 0.7;
	public const MAX_CLUSTER_EDGE_LENGTH = 1.0;
	public const DIMENSIONS = 512;
	public const MAX_OVERLAP_NEW_CLUSTER = 0.1;
	public const MIN_OVERLAP_EXISTING_CLUSTER = 0.5;

	public function __construct(
		LoggerInterface $logger,
		ITaskProcessingManager $taskProcessingManager,
		IUserMountCache $userMountCache,
		QueueService $queue,
		private FaceDetectionMapper $faceDetections,
	) {
		parent::__construct($logger, $taskProcessingManager, $userMountCache, $queue);
	}

	protected function getTaskTypeId(): string {
		return ImageFaceRecognitionTaskType::ID;
	}

	protected function getModelName(): string {
		return ClusteringFaceClassifier::MODEL_NAME;
	}

	/**
	 * Skip files that already have face detections, so that re-crawling doesn't
	 * create a second set of detections for the same faces.
	 *
	 * @param list<QueueFile> $queueFiles
	 * @return list<QueueFile>
	 */
	protected function filterQueueFiles(array $queueFiles): array {
		try {
			$processed = array_flip($this->faceDetections->findFileIdsWithDetections(
				array_map(static fn (QueueFile $qf): int => $qf->getFileId(), $queueFiles),
			));
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Could not look up existing face detections; scheduling all files', ['exception' => $e]);
			return $queueFiles;
		}
		return array_values(array_filter($queueFiles, static fn (QueueFile $qf): bool => !isset($processed[$qf->getFileId()])));
	}
}
