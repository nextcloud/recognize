<?php

namespace OCA\Recognize\Controller;

use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IRequest;

class AdminController extends Controller {
	private TagManager $tagManager;
	/**
	 * @var \OCP\BackgroundJob\IJobList
	 */
	private IJobList $jobList;
	/**
	 * @var \OCP\IConfig
	 */
	private IConfig $config;
	/**
	 * @var \OCA\Recognize\Service\QueueService
	 */
	private QueueService $queue;

	public function __construct($appName, IRequest $request, TagManager $tagManager, IJobList $jobList, IConfig $config, QueueService $queue) {
		parent::__construct($appName, $request);
		$this->tagManager = $tagManager;
		$this->jobList = $jobList;
		$this->config = $config;
		$this->queue = $queue;
	}

	public function reset(): JSONResponse {
		$this->tagManager->resetClassifications();
		return new JSONResponse([]);
	}

	public function recrawl(): JSONResponse {
		$this->jobList->add(SchedulerJob::class);
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
		return new JSONResponse(['avx' => str_contains($cpuinfo, 'avx')]);
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
			exec('ldd /bin/ls' . ' 2>&1', $output, $returnCode);
		} catch (\Throwable $e) {
			return new JSONResponse(['musl' => null]);
		}

		if ($returnCode !== 0) {
			return new JSONResponse(['musl' => null]);
		}

		$ldd = trim(implode("\n", $output));
		return new JSONResponse(['musl' => str_contains($ldd, 'musl')]);
	}

	public function setSetting(string $setting, $value) {
		$this->config->setAppValue('recognize', $setting, $value);
	}

	public function getSetting(string $setting) {
		return new JSONResponse(['value' => $this->config->getAppValue('recognize', $setting)]);
	}
}
