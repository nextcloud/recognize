<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Recognize\Classifiers;

use OCA\Recognize\AppInfo\Application;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\QueueService;
use OCP\DB\Exception;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\Task;
use Psr\Log\LoggerInterface;

abstract class AbstractTaskProcessingClassifier {
	public function __construct(
		protected LoggerInterface $logger,
		protected ITaskProcessingManager $taskProcessingManager,
		protected IUserMountCache $userMountCache,
		protected QueueService $queue,
	) {
	}

	/**
	 * The TaskProcessing task type id this classifier schedules.
	 */
	abstract protected function getTaskTypeId(): string;

	/**
	 * The queue model name this classifier consumes.
	 */
	abstract protected function getModelName(): string;

	/**
	 * Schedule a TaskProcessing task for the given batch of queue files.
	 *
	 * Results will be applied asynchronously by {@see \OCA\Recognize\TaskProcessing\TaskResultListener}
	 * when the provider emits a TaskSuccessfulEvent.
	 *
	 * @param list<QueueFile> $queueFiles
	 */
	public function classify(array $queueFiles): void {
		if (count($queueFiles) === 0) {
			return;
		}

		$storageId = $queueFiles[0]->getStorageId();
		$rootId = $queueFiles[0]->getRootId();
		$userId = $this->findUserForStorage($storageId, $rootId);
		if ($userId === null) {
			$this->logger->warning('No user with access for storage ' . $storageId . '/' . $rootId . ' found; dropping ' . count($queueFiles) . ' files from ' . $this->getModelName() . ' queue');
			$this->dropFromQueue($queueFiles);
			return;
		}

		$fileIds = array_values(array_unique(array_map(static fn (QueueFile $qf): int => $qf->getFileId(), $queueFiles)));

		$task = new Task(
			$this->getTaskTypeId(),
			['input' => $fileIds],
			Application::APP_ID,
			$userId,
			$this->getModelName(),
		);

		try {
			$this->taskProcessingManager->scheduleTask($task);
		} catch (\Throwable $e) {
			// Leave files in the queue so they can be retried on the next job run
			$this->logger->error('Failed to schedule ' . $this->getTaskTypeId() . ' task', ['exception' => $e]);
			throw new \RuntimeException('Could not schedule ' . $this->getTaskTypeId() . ' task', 0, $e);
		}

		/**
		 * @psalm-suppress PossiblyNullOperand
		 * @psalm-suppress InvalidOperand
		 */
		$this->logger->debug('Scheduled ' . $this->getTaskTypeId() . ' task #' . $task->getId() . ' for ' . count($fileIds) . ' files');

		// Once scheduled, files leave the queue. The TaskResultListener applies results when the task completes.
		$this->dropFromQueue($queueFiles);
	}

	private function findUserForStorage(int $storageId, int $rootId): ?string {
		$mounts = array_values(array_filter(
			$this->userMountCache->getMountsForStorageId($storageId),
			static fn (ICachedMountInfo $m): bool => $m->getRootId() === $rootId,
		));
		if (count($mounts) === 0) {
			return null;
		}
		return $mounts[0]->getUser()->getUID();
	}

	/**
	 * @param list<QueueFile> $queueFiles
	 */
	private function dropFromQueue(array $queueFiles): void {
		foreach ($queueFiles as $qf) {
			try {
				$this->queue->removeFromQueue($this->getModelName(), $qf);
			} catch (Exception $e) {
				$this->logger->warning('Could not remove file ' . $qf->getFileId() . ' from ' . $this->getModelName() . ' queue', ['exception' => $e]);
			}
		}
	}
}
