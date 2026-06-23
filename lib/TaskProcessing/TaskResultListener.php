<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Recognize\TaskProcessing;

use OCA\Recognize\AppInfo\Application;
use OCA\Recognize\BackgroundJobs\ClusterFacesJob;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Classifiers\TaskProcessing\ImageFaceRecognitionClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\IUserManager;
use OCP\TaskProcessing\Events\TaskFailedEvent;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use OCP\TaskProcessing\Task;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Stores results of TaskProcessing tasks scheduled by this app:
 *
 *  - image / video / audio classification → assigns category tags via {@see TagManager}
 *  - face recognition → inserts {@see FaceDetection} rows and schedules a {@see ClusterFacesJob}
 *
 * @template-implements IEventListener<Event>
 */
final class TaskResultListener implements IEventListener {
	public function __construct(
		private LoggerInterface $logger,
		private TagManager $tagManager,
		private FaceDetectionMapper $faceDetections,
		private IUserMountCache $userMountCache,
		private IAppConfig $config,
		private IJobList $jobList,
		private QueueService $queue,
		private IUserSession $userSession,
		private IUserManager $userManager,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof TaskFailedEvent) {
			$this->handleFailure($event);
			return;
		}
		if ($event instanceof TaskSuccessfulEvent) {
			$this->handleSuccess($event);
		}
	}

	private function handleFailure(TaskFailedEvent $event): void {
		$task = $event->getTask();
		if (!$this->isOwnTask($task)) {
			return;
		}
		$model = $this->modelForTaskType($task->getTaskTypeId());
		$this->logger->warning('TaskProcessing task ' . $task->getTaskTypeId() . ' (id=' . $task->getId() . ') failed: ' . $event->getErrorMessage());
		if ($model !== null) {
			$this->config->setAppValueString($model . '.status', 'false');
		}
	}

	private function handleSuccess(TaskSuccessfulEvent $event): void {
		$task = $event->getTask();
		if (!$this->isOwnTask($task)) {
			return;
		}

		$input = $task->getInput()['input'] ?? null;
		$output = ($task->getOutput() ?? [])['output'] ?? null;
		if (!is_array($input) || !is_array($output)) {
			$this->logger->warning('TaskProcessing task ' . $task->getTaskTypeId() . ' (id=' . $task->getId() . ') has unexpected input/output shape');
			return;
		}

		$fileIds = array_map('intval', array_values($input));
		$results = array_values($output);

		$this->userSession->setUser($this->userManager->get($task->getUserId()));

		switch ($task->getTaskTypeId()) {
			case ImageClassificationTaskType::ID:
				$this->applyTagResults($fileIds, $results, ImagenetClassifier::MODEL_NAME, false);
				break;
			case VideoClassificationTaskType::ID:
				$this->applyTagResults($fileIds, $results, MovinetClassifier::MODEL_NAME, false);
				break;
			case AudioClassificationTaskType::ID:
				$this->applyTagResults($fileIds, $results, MusicnnClassifier::MODEL_NAME, false);
				break;
			case ImageFaceRecognitionTaskType::ID:
				$this->applyFaceResults($fileIds, $results);
				break;
			default:
				return;
		}
	}

	private function isOwnTask(Task $task): bool {
		return $task->getAppId() === Application::APP_ID
			&& in_array($task->getTaskTypeId(), [
				ImageClassificationTaskType::ID,
				VideoClassificationTaskType::ID,
				AudioClassificationTaskType::ID,
				ImageFaceRecognitionTaskType::ID,
			], true);
	}

	private function modelForTaskType(string $taskTypeId): ?string {
		return match ($taskTypeId) {
			ImageClassificationTaskType::ID => ImagenetClassifier::MODEL_NAME,
			VideoClassificationTaskType::ID => MovinetClassifier::MODEL_NAME,
			AudioClassificationTaskType::ID => MusicnnClassifier::MODEL_NAME,
			ImageFaceRecognitionTaskType::ID => ClusteringFaceClassifier::MODEL_NAME,
			default => null,
		};
	}

	/**
	 * @param list<int> $fileIds
	 * @param list<mixed> $results
	 */
	private function applyTagResults(array $fileIds, array $results, string $model, bool $forwardToLandmarks): void {
		foreach ($fileIds as $i => $fileId) {
			if (!isset($results[$i])) {
				continue;
			}
			$raw = (string)$results[$i];
			$tags = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $t): bool => $t !== ''));
			if (count($tags) === 0) {
				$this->logger->debug('No tags returned for file ' . $fileId . ' from ' . $model);
				continue;
			}
			try {
				$this->tagManager->assignTags($fileId, $tags);
			} catch (\Throwable $e) {
				$this->logger->warning('Could not assign ' . $model . ' tags for file ' . $fileId, ['exception' => $e]);
				continue;
			}
			$this->config->setAppValueString($model . '.status', 'true', lazy: true);
			$this->config->setAppValueString($model . '.lastFile', (string)time(), lazy: true);

			if ($forwardToLandmarks) {
				$landmarkTags = array_values(array_filter($tags, static fn (string $tag): bool => in_array($tag, LandmarksClassifier::PRECONDITION_TAGS, true)));
				if (count($landmarkTags) > 0) {
					$this->enqueueForLandmarks($fileId);
				}
			}
		}
	}

	/**
	 * @param list<int> $fileIds
	 * @param list<mixed> $results
	 */
	private function applyFaceResults(array $fileIds, array $results): void {
		$model = ClusteringFaceClassifier::MODEL_NAME;
		$scheduledClusterJobsFor = [];
		foreach ($fileIds as $i => $fileId) {
			if (!isset($results[$i])) {
				continue;
			}
			$raw = (string)$results[$i];
			$userIds = $this->getUsersWithFileAccess($fileId);
			foreach (explode("\n", $raw) as $line) {
				$line = trim($line);
				if ($line === '') {
					continue;
				}
				try {
					$face = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
				} catch (\JsonException $e) {
					$this->logger->warning('Invalid face JSON for file ' . $fileId, ['exception' => $e]);
					continue;
				}
				if (!is_array($face)) {
					continue;
				}
				if (isset($face['score']) && (float)$face['score'] < ImageFaceRecognitionClassifier::MIN_FACE_RECOGNITION_SCORE) {
					continue;
				}
				// Accept either a full face object {x,y,width,height,score,vector,angle}
				// or a bare embedding vector (list of numbers).
				$isBareVector = array_is_list($face) && count($face) > 0 && is_numeric($face[0]);
				$vector = $isBareVector ? $face : ($face['vector'] ?? null);
				if (!is_array($vector)) {
					$this->logger->warning('Face entry without embedding vector for file ' . $fileId);
					continue;
				}
				foreach ($userIds as $userId) {
					$detection = new FaceDetection();
					$detection->setFileId($fileId);
					$detection->setUserId($userId);
					$detection->setX((float)($face['x'] ?? 0));
					$detection->setY((float)($face['y'] ?? 0));
					$detection->setWidth((float)($face['width'] ?? 0));
					$detection->setHeight((float)($face['height'] ?? 0));
					$detection->setVector($vector);
					try {
						$this->faceDetections->insert($detection);
					} catch (\Throwable $e) {
						$this->logger->error('Could not store face detection for file ' . $fileId, ['exception' => $e]);
						continue;
					}
					if (!isset($scheduledClusterJobsFor[$userId])) {
						$this->jobList->add(ClusterFacesJob::class, ['userId' => $userId]);
						$scheduledClusterJobsFor[$userId] = true;
					}
				}
			}
			$this->config->setAppValueString($model . '.status', 'true', lazy: true);
			$this->config->setAppValueString($model . '.lastFile', (string)time(), lazy: true);
		}
	}

	private function enqueueForLandmarks(int $fileId): void {
		$mounts = $this->userMountCache->getMountsForFileId($fileId);
		if (count($mounts) === 0) {
			return;
		}
		$mount = $mounts[0];
		$queueFile = new QueueFile();
		$queueFile->setFileId($fileId);
		$queueFile->setStorageId($mount->getStorageId());
		$queueFile->setRootId($mount->getRootId());
		$queueFile->setUpdate(false);
		try {
			$this->queue->insertIntoQueue(LandmarksClassifier::MODEL_NAME, $queueFile);
		} catch (\Throwable $e) {
			$this->logger->warning('Could not enqueue file ' . $fileId . ' for landmark detection', ['exception' => $e]);
		}
	}

	/**
	 * @return list<string>
	 */
	private function getUsersWithFileAccess(int $fileId): array {
		try {
			$mountInfos = $this->userMountCache->getMountsForFileId($fileId);
		} catch (\Throwable $e) {
			$this->logger->warning('Could not look up users with access for file ' . $fileId, ['exception' => $e]);
			return [];
		}
		return array_values(array_unique(array_map(
			static fn (ICachedMountInfo $m): string => $m->getUser()->getUID(),
			$mountInfos,
		)));
	}
}
