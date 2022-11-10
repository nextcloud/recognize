<?php

namespace OCA\Recognize\Controller;

use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\SettingsService;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\IRequest;

class AdminController extends Controller {
	private TagManager $tagManager;
	private IJobList $jobList;
	private SettingsService $settingsService;
	private QueueService $queue;
	private FaceClusterMapper $clusterMapper;
	private FaceDetectionMapper $detectionMapper;

	public function __construct(string $appName, IRequest $request, TagManager $tagManager, IJobList $jobList, SettingsService $settingsService, QueueService $queue, FaceClusterMapper $clusterMapper, FaceDetectionMapper $detectionMapper) {
		parent::__construct($appName, $request);
		$this->tagManager = $tagManager;
		$this->jobList = $jobList;
		$this->settingsService = $settingsService;
		$this->queue = $queue;
		$this->clusterMapper = $clusterMapper;
		$this->detectionMapper = $detectionMapper;
	}

	public function reset(): JSONResponse {
		$this->tagManager->resetClassifications();
		return new JSONResponse([]);
	}

	public function recrawl(): JSONResponse {
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
		return new JSONResponse([
			'imagenet' => $imagenetCount,
			'faces' => $facesCount,
			'landmarks' => $landmarksCount,
			'movinet' => $movinetCount,
			'musicnn' => $musicnnCount
		]);
	}

	public function avx(): JSONResponse {
		try {
			$cpuinfo = file_get_contents('/proc/cpuinfo');
		} catch (\Throwable $e) {
			return new JSONResponse(['avx' => null]);
		}
		return new JSONResponse(['avx' => strpos($cpuinfo, 'avx') !== false]);
	}

	public function platform(): JSONResponse {
		try {
			exec('lscpu --json' . ' 2>&1', $output, $returnCode);
		} catch (\Throwable $e) {
			return new JSONResponse(['platform' => null]);
		}

		if ($returnCode !== 0) {
			return new JSONResponse(['platform' => null]);
		}

		$lscpu = \json_decode(trim(implode("\n", $output)), true);
		return new JSONResponse(['platform' => $lscpu['lscpu'][0]['data']]);
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

	public function setSetting(string $setting, $value): JSONResponse {
		try {
			$this->settingsService->setSetting($setting, $value);
			return new JSONResponse([], Http::STATUS_OK);
		} catch (\OCA\Recognize\Exception\Exception $e) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	public function getSetting(string $setting):JSONResponse {
		return new JSONResponse(['value' => $this->settingsService->getSetting($setting)]);
	}
}
