<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Exception\Exception;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;

class SettingsService {
	private const DEFAULTS = [
		'tensorflow.cores' => 0,
		'tensorflow.gpu' => false,
		'tensorflow.purejs' => false,
		'geo.enabled' => false,
		'imagenet.enabled' => false,
		'landmarks.enabled' => false,
		'faces.enabled' => false,
		'musicnn.enabled' => false,
		'movinet.enabled' => false,
		'node_binary' => '',
		'faces.status' => null,
		'imagenet.status' => null,
		'landmarks.status' => null,
		'movinet.status' => null,
		'musicnn.status' => null,
		'faces.lastFile' => 0,
		'imagenet.lastFile' => 0,
		'landmarks.lastFile' => 0,
		'movinet.lastFile' => 0,
		'musicnn.lastFile' => 0,
		'faces.batchSize' => 500,
		'imagenet.batchSize' => 100,
		'landmarks.batchSize' => 100,
		'movinet.batchSize' => 20,
		'musicnn.batchSize' => 100,
	];

	private const PUREJS_DEFAULTS = [
		'faces.batchSize' => 50,
		'imagenet.batchSize' => 20,
		'landmarks.batchSize' => 20,
		'movinet.batchSize' => 5,
		'musicnn.batchSize' => 20,
	];

	private IConfig $config;
	private IJobList $jobList;

	public function __construct(IConfig $config, IJobList $jobList) {
		$this->config = $config;
		$this->jobList = $jobList;
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public function getSetting(string $key): string {
		if (strpos($key, 'batchSize') !== false) {
			return $this->config->getAppValue('recognize', $key, $this->getSetting('tensorflow.purejs') === 'false' ? json_encode(self::DEFAULTS[$key]) : json_encode(self::PUREJS_DEFAULTS[$key]));
		}
		return $this->config->getAppValue('recognize', $key, json_encode(self::DEFAULTS[$key]));
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
		if ($value === 'true' && $this->config->getAppValue('recognize', $key, 'false') === 'false') {
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
		$this->config->setAppValue('recognize', $key, $value);
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
