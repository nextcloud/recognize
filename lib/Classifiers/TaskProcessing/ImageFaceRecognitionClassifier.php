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
	protected function getTaskTypeId(): string {
		return ImageFaceRecognitionTaskType::ID;
	}

	protected function getModelName(): string {
		return ClusteringFaceClassifier::MODEL_NAME;
	}
}
