<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Recognize\TaskProcessing;

use OCA\Recognize\AppInfo\Application;
use OCP\IL10N;
use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\ITaskType;
use OCP\TaskProcessing\ShapeDescriptor;

final class VideoClassificationTaskType implements ITaskType {
	public const ID = Application::APP_ID . ':video:classification';

	public function __construct(
		private IL10N $l,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l->t('Video classification');
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string {
		return $this->l->t('Classify videos into categories.');
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return self::ID;
	}

	/**
	 * @return ShapeDescriptor[]
	 */
	public function getInputShape(): array {
		return [
			'input' => new ShapeDescriptor(
				$this->l->t('Videos'),
				$this->l->t('Provide videos to classify'),
				EShapeType::ListOfVideos,
			),
		];
	}

	/**
	 * @return ShapeDescriptor[]
	 */
	public function getOutputShape(): array {
		return [
			'output' => new ShapeDescriptor(
				$this->l->t('Categories'),
				$this->l->t('The classified categories. Each input video is mapped to a text containing a comma separated list of categories.'),
				EShapeType::ListOfTexts,
			),
		];
	}
}
