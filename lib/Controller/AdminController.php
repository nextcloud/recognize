<?php

declare(strict_types=1);

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Controller;

use OCA\Recognize\BackgroundJobs\ClassifyFacesJob;
use OCA\Recognize\BackgroundJobs\ClassifyImagenetJob;
use OCA\Recognize\BackgroundJobs\ClassifyLandmarksJob;
use OCA\Recognize\BackgroundJobs\ClassifyMovinetJob;
use OCA\Recognize\BackgroundJobs\ClassifyMusicnnJob;
use OCA\Recognize\BackgroundJobs\ClusterFacesJob;
use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\BackgroundJobs\StorageCrawlJob;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\SettingsService;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\QueryException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IBinaryFinder;
use OCP\IRequest;

class AdminController extends Controller {
	private TagManager $tagManager;
	private IJobList $jobList;
	private SettingsService $settingsService;
	private QueueService $queue;
	private FaceClusterMapper $clusterMapper;
	private FaceDetectionMapper $detectionMapper;
	private IAppConfig $config;
	private FaceDetectionMapper $faceDetections;
	private IBinaryFinder $binaryFinder;

	public function __construct(string $appName, IRequest $request, TagManager $tagManager, IJobList $jobList, SettingsService $settingsService, QueueService $queue, FaceClusterMapper $clusterMapper, FaceDetectionMapper $detectionMapper, IAppConfig $config, FaceDetectionMapper $faceDetections, IBinaryFinder $binaryFinder) {
		parent::__construct($appName, $request);
		$this->tagManager = $tagManager;
		$this->jobList = $jobList;
		$this->settingsService = $settingsService;
		$this->queue = $queue;
		$this->clusterMapper = $clusterMapper;
		$this->detectionMapper = $detectionMapper;
		$this->config = $config;
		$this->faceDetections = $faceDetections;
		$this->binaryFinder = $binaryFinder;
	}

	public function reset(): JSONResponse {
		$this->tagManager->resetClassifications();
		return new JSONResponse([]);
	}

	public function clearAllJobs(): JSONResponse {
		$this->queue->clearQueue('imagenet');
		$this->queue->clearQueue('faces');
		$this->queue->clearQueue('landmarks');
		$this->queue->clearQueue('movinet');
		$this->queue->clearQueue('musicnn');
		$this->jobList->remove(ClassifyFacesJob::class);
		$this->jobList->remove(ClassifyImagenetJob::class);
		$this->jobList->remove(ClassifyLandmarksJob::class);
		$this->jobList->remove(ClassifyMusicnnJob::class);
		$this->jobList->remove(ClassifyMovinetJob::class);
		$this->jobList->remove(ClusterFacesJob::class);
		$this->jobList->remove(SchedulerJob::class);
		$this->jobList->remove(StorageCrawlJob::class);
		return new JSONResponse([]);
	}

	public function recrawl(): JSONResponse {
		$this->clearAllJobs();
		$this->jobList->add(SchedulerJob::class);
		return new JSONResponse([]);
	}

	public function resetFaces(): JSONResponse {
		try {
			$this->clusterMapper->deleteAll();
			$this->detectionMapper->deleteAll();
		} catch (Exception $e) {
			return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse([]);
	}

	public function count(): JSONResponse {
		$count = count($this->tagManager->findClassifiedFiles());
		return new JSONResponse(['count' => $count]);
	}

	public function countMissed(): JSONResponse {
		$count = count($this->tagManager->findMissedClassifications());
		return new JSONResponse(['count' => $count]);
	}

	public function countQueued(): JSONResponse {
		$imagenetCount = $this->queue->count('imagenet');
		$facesCount = $this->queue->count('faces');
		$landmarksCount = $this->queue->count('landmarks');
		$movinetCount = $this->queue->count('movinet');
		$musicnnCount = $this->queue->count('musicnn');
		$clusterFacesCount = $this->faceDetections->countUnclustered();
		return new JSONResponse([
			'imagenet' => $imagenetCount,
			'faces' => $facesCount,
			'landmarks' => $landmarksCount,
			'movinet' => $movinetCount,
			'musicnn' => $musicnnCount,
			'clusterFaces' => $clusterFacesCount,
		]);
	}

	public function hasJobs(string $task): JSONResponse {
		$tasks = [
			'faces' => ClassifyFacesJob::class,
			'imagenet' => ClassifyImagenetJob::class,
			'landmarks' => ClassifyLandmarksJob::class,
			'musicnn' => ClassifyMusicnnJob::class,
			'movinet' => ClassifyMovinetJob::class,
			'clusterFaces' => ClusterFacesJob::class,
		];
		if (!isset($tasks[$task])) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$iterator = $this->jobList->getJobsIterator($tasks[$task], null, 0);
		$lastRun = [];
		foreach ($iterator as $job) {
			$lastRun[] = $job->getLastRun();
		}
		$count = count($lastRun);
		$lastRun = $lastRun? max($lastRun) : 0;
		return new JSONResponse([
			'scheduled' => $count,
			'lastRun' => $lastRun,
		]);
	}

	public function avx(): JSONResponse {
		try {
			$cpuinfo = file_get_contents('/proc/cpuinfo');
		} catch (\Throwable $e) {
			return new JSONResponse(['avx' => null]);
		}
		return new JSONResponse(['avx' => $cpuinfo !== false && strpos($cpuinfo, 'avx') !== false]);
	}

	public function platform(): JSONResponse {
		return new JSONResponse(['platform' => php_uname('m')]);
	}

	public function musl(): JSONResponse {
		try {
			exec('ldd /bin/sh' . ' 2>&1', $output, $returnCode);
		} catch (\Throwable $e) {
			return new JSONResponse(['musl' => null]);
		}

		if ($returnCode !== 0) {
			return new JSONResponse(['musl' => null]);
		}

		$ldd = trim(implode("\n", $output));
		return new JSONResponse(['musl' => strpos($ldd, 'musl') !== false]);
	}

	public function nice(): JSONResponse {
		/* use nice binary from settings if available */
		if ($this->config->getAppValueString('nice_binary', '') !== '') {
			$nice_path = $this->config->getAppValueString('nice_binary');
		} else {
			/* returns the path to the nice binary or false if not found */
			$nice_path = $this->binaryFinder->findBinaryPath('nice');
		}

		if ($nice_path !== false) {
			$this->config->setAppValueString('nice_binary', $nice_path);
		} else {
			$this->config->setAppValueString('nice_binary', '');
			return new JSONResponse(['nice' => false]);
		}

		try {
			exec($nice_path . ' true' . ' 2>&1', $output, $returnCode);
		} catch (\Throwable $e) {
			return new JSONResponse(['nice' => false]);
		}

		if ($returnCode !== 0) {
			return new JSONResponse(['nice' => false]);
		}

		return new JSONResponse(['nice' => $nice_path]);
	}

	public function nodejs(): JSONResponse {
		try {
			exec($this->settingsService->getSetting('node_binary') . ' --version' . ' 2>&1', $output, $returnCode);
		} catch (\Throwable $e) {
			return new JSONResponse(['nodejs' => null]);
		}

		if ($returnCode !== 0) {
			return new JSONResponse(['nodejs' => false]);
		}

		$version = trim(implode("\n", $output));
		return new JSONResponse(['nodejs' => $version]);
	}

	public function libtensorflow(): JSONResponse {
		try {
			exec($this->settingsService->getSetting('node_binary') . ' ' . __DIR__ . '/../../src/test_libtensorflow.js' . ' 2>&1', $output, $returnCode);
		} catch (\Throwable $e) {
			return new JSONResponse(['libtensorflow' => false]);
		}

		if ($returnCode !== 0) {
			return new JSONResponse(['libtensorflow' => false]);
		}

		return new JSONResponse(['libtensorflow' => true]);
	}

	public function wasmtensorflow(): JSONResponse {
		try {
			exec($this->settingsService->getSetting('node_binary') . ' ' . __DIR__ . '/../../src/test_wasmtensorflow.js' . ' 2>&1', $output, $returnCode);
		} catch (\Throwable $e) {
			return new JSONResponse(['wasmtensorflow' => false]);
		}

		if ($returnCode !== 0) {
			return new JSONResponse(['wasmtensorflow' => false]);
		}

		return new JSONResponse(['wasmtensorflow' => true]);
	}

	public function gputensorflow(): JSONResponse {
		try {
			exec($this->settingsService->getSetting('node_binary') . ' ' . __DIR__ . '/../../src/test_gputensorflow.js' . ' 2>&1', $output, $returnCode);
		} catch (\Throwable $e) {
			return new JSONResponse(['gputensorflow' => false]);
		}

		if ($returnCode !== 0) {
			return new JSONResponse(['gputensorflow' => false]);
		}

		return new JSONResponse(['gputensorflow' => true]);
	}

	public function cron(): JSONResponse {
		try {
			/** @var IAppConfig $appConfig */
			$appConfig = \OC::$server->getRegisteredAppContainer('core')->get(IAppConfig::class);
			$cron = $appConfig->getAppValueString('backgroundjobs_mode', '');
		} catch (QueryException|AppConfigTypeConflictException $e) {
			return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse(['cron' => $cron]);
	}

	/**
	 * @param string $setting
	 * @param scalar $value
	 * @return JSONResponse
	 */
	public function setSetting(string $setting, float|bool|int|string $value): JSONResponse {
		try {
			$this->settingsService->setSetting($setting, (string) $value);
			return new JSONResponse([], Http::STATUS_OK);
		} catch (\OCA\Recognize\Exception\Exception $e) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	public function getSetting(string $setting): JSONResponse {
		return new JSONResponse(['value' => $this->settingsService->getSetting($setting)]);
	}
}
