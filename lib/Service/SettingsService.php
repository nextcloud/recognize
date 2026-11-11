<?php

declare(strict_types=1);

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\Recognize\AuditLog\AuditEventFactory;
use OCA\Recognize\AuditLog\Operation;
use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Classifiers\TaskProcessing\ImageFaceRecognitionClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Exception\Exception;
use OCP\AppFramework\Services\IAppConfig;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception as DBException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\Server;

final class SettingsService {
	/**
	 * App id of the ExApp that provides TaskProcessing classifiers; when installed
	 * and enabled, taskprocessing mode is on by default, unless that would mix
	 * face embeddings of different models (see getTaskProcessingDefault()).
	 */
	public const RECOGNIZE_BACKEND_APP_ID = 'recognize_backend';

	/** @var array<string,string>  */
	private const DEFAULTS = [
		'tensorflow.cores' => '0',
		'tensorflow.gpu' => 'false',
		'tensorflow.purejs' => 'false',
		'geo.enabled' => 'false',
		'imagenet.enabled' => 'false',
		'landmarks.enabled' => 'false',
		'faces.enabled' => 'false',
		'musicnn.enabled' => 'false',
		'movinet.enabled' => 'false',
		'taskprocessing.enabled' => '',
		'node_binary' => '',
		'clusterFaces.status' => 'null',
		'faces.status' => 'null',
		'imagenet.status' => 'null',
		'landmarks.status' => 'null',
		'movinet.status' => 'null',
		'musicnn.status' => 'null',
		'clusterFaces.lastRun' => '0',
		'faces.lastFile' => '0',
		'imagenet.lastFile' => '0',
		'landmarks.lastFile' => '0',
		'movinet.lastFile' => '0',
		'musicnn.lastFile' => '0',
		'faces.batchSize' => '500',
		'imagenet.batchSize' => '100',
		'landmarks.batchSize' => '100',
		'movinet.batchSize' => '20',
		'musicnn.batchSize' => '100',
		'nice_binary' => '',
		'nice_value' => '0',
		'concurrency.enabled' => 'false',
		'clusterFaces.concurrency' => '1',
		'ffmpeg_binary' => '',
	];

	/** @var array<string,string>  */
	private const PUREJS_DEFAULTS = [
		'faces.batchSize' => '50',
		'imagenet.batchSize' => '20',
		'landmarks.batchSize' => '20',
		'movinet.batchSize' => '5',
		'musicnn.batchSize' => '20',
	];
	public const LAZY_SETTINGS = [
		'tensorflow.purejs',
		'node_binary',
		'nice_binary',
		'nice_value',
		'ffmpeg_binary',
		'clusterFaces.status',
		'faces.status',
		'imagenet.status',
		'landmarks.status',
		'movinet.status',
		'musicnn.status',
		'faces.lastFile',
		'imagenet.lastFile',
		'landmarks.lastFile',
		'movinet.lastFile',
		'musicnn.lastFile',
		'clusterFaces.lastRun',
		'faces.batchSize',
		'imagenet.batchSize',
		'landmarks.batchSize',
		'movinet.batchSize',
		'musicnn.batchSize',
		'concurrency.enabled',
		'clusterFaces.concurrency',
	];

	/**
	 * Progress state written by the background jobs, not configuration
	 */
	private const STATE_SETTINGS = [
		'clusterFaces.status',
		'faces.status',
		'imagenet.status',
		'landmarks.status',
		'movinet.status',
		'musicnn.status',
		'clusterFaces.lastRun',
		'faces.lastFile',
		'imagenet.lastFile',
		'landmarks.lastFile',
		'movinet.lastFile',
		'musicnn.lastFile',
	];

	private IAppConfig $config;
	private IJobList $jobList;
	private IEventDispatcher $eventDispatcher;
	private IDBConnection $db;

	public function __construct(IAppConfig $config, IJobList $jobList, IEventDispatcher $eventDispatcher, IDBConnection $db) {
		$this->config = $config;
		$this->jobList = $jobList;
		$this->eventDispatcher = $eventDispatcher;
		$this->db = $db;
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public function getSetting(string $key): string {
		if (strpos($key, 'batchSize') !== false) {
			return $this->config->getAppValueString($key, $this->getSetting('tensorflow.purejs') === 'false' ? self::DEFAULTS[$key] : self::PUREJS_DEFAULTS[$key]);
		}
		if ($key === 'taskprocessing.enabled') {
			$value = $this->config->getAppValueString($key, '');
			if ($value !== '') {
				return $value;
			}
			return $this->getTaskProcessingDefault() ? 'true' : 'false';
		}
		$lazy = false;
		if (in_array($key, self::LAZY_SETTINGS, true)) {
			$lazy = true;
		}
		return $this->config->getAppValueString($key, self::DEFAULTS[$key], lazy: $lazy);
	}

	/**
	 * TaskProcessing mode defaults to on when the recognize_backend ExApp is installed.
	 * The local face model and the TaskProcessing face model produce embeddings of
	 * different dimensions that cannot be clustered together, so we don't switch
	 * the mode implicitly when the existing face detections stem from the local model.
	 */
	private function getTaskProcessingDefault(): bool {
		if (!$this->isRecognizeBackendInstalled()) {
			return false;
		}
		$dimensions = $this->getFaceEmbeddingDimensions();
		return $dimensions === null || $dimensions === ImageFaceRecognitionClassifier::DIMENSIONS;
	}

	/**
	 * Returns the dimensionality of the most recently stored face embedding,
	 * or null if there are no face detections.
	 */
	public function getFaceEmbeddingDimensions(): ?int {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('face_vector')
				->from('recognize_face_detections')
				->orderBy('id', 'DESC')
				->setMaxResults(1);
			$result = $qb->executeQuery();
			/** @var string|false|null $vector */
			$vector = $result->fetchOne();
			$result->closeCursor();
		} catch (DBException $e) {
			return null;
		}
		if (!is_string($vector) || $vector === '') {
			return null;
		}
		try {
			/** @var array|string|int|float|bool|null $decoded */
			$decoded = json_decode($vector, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			return null;
		}
		return is_array($decoded) ? count($decoded) : null;
	}

	/**
	 * Whether the recognize_backend ExApp is installed and enabled. The lookup
	 * goes through app_api's PublicFunctions service so we don't impose a hard
	 * dependency on app_api: if it isn't installed, this returns false.
	 */
	public function isRecognizeBackendInstalled(): bool {
		try {
			/**
			 * @var \OCA\AppAPI\PublicFunctions $publicFunctions
			 */
			$publicFunctions = Server::get(\OCA\AppAPI\PublicFunctions::class);
		} catch (\Throwable $e) {
			return false;
		}
		try {
			$exApp = $publicFunctions->getExApp(self::RECOGNIZE_BACKEND_APP_ID);
		} catch (\Throwable $e) {
			return false;
		}
		return $exApp !== null && (bool)($exApp['enabled'] ?? false);
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @return void
	 * @throws \OCA\Recognize\Exception\Exception
	 */
	public function setSetting(string $key, string $value): void {
		if (!array_key_exists($key, self::DEFAULTS)) {
			throw new Exception('Unknown settings key '.$key);
		}
		$isConfiguration = !in_array($key, self::STATE_SETTINGS, true);
		$oldValue = $isConfiguration ? $this->getSetting($key) : null;
		if ($value === 'true' && $this->config->getAppValueString($key, 'false') === 'false') {
			// Additional model enabled: Schedule new crawl run for the affected mime types
			switch ($key) {
				case ClusteringFaceClassifier::MODEL_NAME . '.enabled':
					$this->jobList->add(SchedulerJob::class, ['models' => [ClusteringFaceClassifier::MODEL_NAME]]);
					break;
				case ImagenetClassifier::MODEL_NAME . '.enabled':
					$this->jobList->add(SchedulerJob::class, ['models' => [ImagenetClassifier::MODEL_NAME]]);
					break;
				case LandmarksClassifier::MODEL_NAME . '.enabled':
					$this->jobList->add(SchedulerJob::class, ['models' => [LandmarksClassifier::MODEL_NAME]]);
					break;
				case MovinetClassifier::MODEL_NAME . '.enabled':
					$this->jobList->add(SchedulerJob::class, ['models' => [MovinetClassifier::MODEL_NAME]]);
					break;
				case MusicnnClassifier::MODEL_NAME . '.enabled':
					$this->jobList->add(SchedulerJob::class, ['models' => [MusicnnClassifier::MODEL_NAME]]);
					break;
				default:
					break;
			}
		}
		$lazy = false;
		if (in_array($key, self::LAZY_SETTINGS, true)) {
			$lazy = true;
		}
		$this->config->setAppValueString($key, $value, lazy: $lazy);

		// The admin UI saves all settings at once, so only actual changes are logged
		if ($isConfiguration && $oldValue !== $value) {
			$this->eventDispatcher->dispatchTyped(AuditEventFactory::create(
				Operation::SettingChanged,
				'Recognize setting "%s" was changed to "%s"',
				['key' => $key, 'value' => $value, 'oldValue' => $oldValue],
			));
		}
	}

	/**
	 * @return array
	 */
	public function getAll(): array {
		$settings = [];
		foreach (array_keys(self::DEFAULTS) as $key) {
			$settings[$key] = $this->getSetting($key);
		}
		return $settings;
	}
}
