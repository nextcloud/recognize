<?php

namespace OCA\Recognize\Controller;

use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\IConfig;
use OCP\IRequest;

class AdminController extends Controller {
	private TagManager $tagManager;
	private IJobList $jobList;
	private IConfig $config;
	private QueueService $queue;
	private FaceClusterMapper $clusterMapper;
	private FaceDetectionMapper $detectionMapper;

	public function __construct(string $appName, IRequest $request, TagManager $tagManager, IJobList $jobList, IConfig $config, QueueService $queue, FaceClusterMapper $clusterMapper, FaceDetectionMapper $detectionMapper) {
		parent::__construct($appName, $request);
		$this->tagManager = $tagManager;
		$this->jobList = $jobList;
		$this->config = $config;
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

	public function setSetting(string $setting, $value): void {
		if ($value === true && $this->config->getAppValue('recognize', $setting, 'false') === 'false') {
			// Additional model enabled: Schedule new crawl run for the affected mime types
			switch ($setting) {
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
		$this->config->setAppValue('recognize', $setting, $value);
	}

	public function getSetting(string $setting):JSONResponse {
		return new JSONResponse(['value' => $this->config->getAppValue('recognize', $setting)]);
	}
}
