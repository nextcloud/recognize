<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\Exception;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use Psr\Log\LoggerInterface;

abstract class ClassifierJob extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private LoggerInterface $logger,
		private QueueService $queue,
		private IUserMountCache $userMountCache,
		private IJobList $jobList,
		private SettingsService $settingsService,
	) {
		parent::__construct($time);
		$this->setInterval(60 * 5);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
		$this->setAllowParallelRuns($settingsService->getSetting('concurrency.enabled') === 'true');
	}

	protected function runClassifier(string $model, array $argument): void {
		sleep(10);
		if ($this->settingsService->getSetting('concurrency.enabled') !== 'true' && $this->anyOtherClassifierJobsRunning()) {
			$this->logger->debug('Stalling job '.static::class.' with argument ' . var_export($argument, true) . ' because other classifiers are already reserved');
			return;
		}

		/**
		 * @var int $storageId
		 */
		$storageId = $argument['storageId'];
		$rootId = $argument['rootId'];
		if ($this->settingsService->getSetting($model.'.enabled') !== 'true') {
			$this->logger->debug('Not classifying files of storage '.$storageId. ' using '.$model. ' because model is disabled');
			// `static` to get extending subclass name
			$this->jobList->remove(static::class, $argument);
			return;
		}
		$this->logger->debug('Classifying files of storage '.$storageId. ' using '.$model);
		try {
			$this->logger->debug('fetching '.$this->getBatchSize().' files from '.$model.' queue');
			$files = $this->queue->getFromQueue($model, $storageId, $rootId, $this->getBatchSize());
		} catch (Exception $e) {
			$this->settingsService->setSetting($model.'.status', 'false');
			$this->logger->error('Cannot retrieve items from '.$model.' queue', ['exception' => $e]);
			return;
		}

		// Setup Filesystem for a users that can access this mount
		$mounts = array_values(array_filter($this->userMountCache->getMountsForStorageId($storageId), function (ICachedMountInfo $mount) use ($rootId) {
			return $mount->getRootId() === $rootId;
		}));
		if (count($mounts) > 0) {
			\OC_Util::setupFS($mounts[0]->getUser()->getUID());
		}

		try {
			$this->logger->debug('Running ' . $model . ' classifier');
			$this->classify($files);
		} catch (\RuntimeException $e) {
			$this->logger->warning('Temporary problem with ' . $model . ' classifier, trying again soon', ['exception' => $e]);
		} catch (\ErrorException $e) {
			$this->settingsService->setSetting($model.'.status', 'false');
			$this->logger->warning('Problem with ' . $model . ' classifier', ['exception' => $e]);
			$this->logger->debug('Removing '.static::class.' with argument ' . var_export($argument, true) . 'from oc_jobs');
			$this->jobList->remove(static::class, $argument);
			throw $e;
		} catch (\Throwable $e) {
			$this->settingsService->setSetting($model.'.status', 'false');
			throw $e;
		}

		try {
			// If there is at least one file left in the queue, reschedule this job
			$files = $this->queue->getFromQueue($model, $storageId, $rootId, 1);
			if (count($files) === 0) {
				$this->logger->debug('Removing '.static::class.' with argument ' . var_export($argument, true) . 'from oc_jobs');
				// `static` to get extending subclasse name
				$this->jobList->remove(static::class, $argument);
			}
		} catch (Exception $e) {
			$this->settingsService->setSetting($model.'.status', 'false');
			$this->logger->error('Cannot retrieve items from '.$model.' queue', ['exception' => $e]);
			return;
		}
	}

	/**
	 * @return int
	 */
	abstract protected function getBatchSize(): int;

	/**
	 * @param array $files
	 * @return void
	 * @throws \RuntimeException|\ErrorException
	 */
	abstract protected function classify(array $files) : void;

	/**
	 * @return bool
	 */
	private function anyOtherClassifierJobsRunning() {
		foreach ([
			ClassifyFacesJob::class,
			ClassifyImagenetJob::class,
			ClassifyLandmarksJob::class,
			ClassifyMovinetJob::class,
			ClassifyMusicnnJob::class,
		] as $jobClass) {
			if ($jobClass === static::class) {
				continue;
			} else {
				if ($this->jobList->hasReservedJob($jobClass)) {
					return true;
				}
			}
		}
		return false;
	}
}
