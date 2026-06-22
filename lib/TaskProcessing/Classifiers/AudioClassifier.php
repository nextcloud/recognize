<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Recognize\TaskProcessing\Classifiers;

use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\TaskProcessing\AudioClassificationTaskType;

final class AudioClassifier extends AbstractClassifier {
	protected function getTaskTypeId(): string {
		return AudioClassificationTaskType::ID;
	}

	protected function getModelName(): string {
		return MusicnnClassifier::MODEL_NAME;
	}
}
