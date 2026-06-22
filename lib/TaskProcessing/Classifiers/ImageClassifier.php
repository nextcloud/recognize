<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Recognize\TaskProcessing\Classifiers;

use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\TaskProcessing\ImageClassificationTaskType;

final class ImageClassifier extends AbstractClassifier {
	protected function getTaskTypeId(): string {
		return ImageClassificationTaskType::ID;
	}

	protected function getModelName(): string {
		return ImagenetClassifier::MODEL_NAME;
	}
}