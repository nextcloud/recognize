<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Recognize\Classifiers\TaskProcessing;

use OCA\Recognize\Classifiers\AbstractTaskProcessingClassifier;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\TaskProcessing\AudioClassificationTaskType;

final class AudioClassifier extends AbstractTaskProcessingClassifier {
	protected function getTaskTypeId(): string {
		return AudioClassificationTaskType::ID;
	}

	protected function getModelName(): string {
		return MusicnnClassifier::MODEL_NAME;
	}
}
