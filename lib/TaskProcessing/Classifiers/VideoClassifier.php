<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Recognize\TaskProcessing\Classifiers;

use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\TaskProcessing\VideoClassificationTaskType;

final class VideoClassifier extends AbstractClassifier {
	protected function getTaskTypeId(): string {
		return VideoClassificationTaskType::ID;
	}

	protected function getModelName(): string {
		return MovinetClassifier::MODEL_NAME;
	}
}