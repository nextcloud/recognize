<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Recognize\Classifiers\TaskProcessing;

use OCA\Recognize\Classifiers\AbstractTaskProcessingClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\TaskProcessing\ImageFaceRecognitionTaskType;

final class ImageFaceRecognitionClassifier extends AbstractTaskProcessingClassifier {
	public const MIN_FACE_RECOGNITION_SCORE = 0.5;

	public const MIN_DATASET_SIZE = 120;
	public const MIN_DETECTION_SIZE = 0.03;
	public const MIN_CLUSTER_SEPARATION = 0.7;
	public const MAX_CLUSTER_EDGE_LENGTH = 1.0;
	public const DIMENSIONS = 512;
	public const MAX_OVERLAP_NEW_CLUSTER = 0.1;
	public const MIN_OVERLAP_EXISTING_CLUSTER = 0.5;

	protected function getTaskTypeId(): string {
		return ImageFaceRecognitionTaskType::ID;
	}

	protected function getModelName(): string {
		return ClusteringFaceClassifier::MODEL_NAME;
	}
}
