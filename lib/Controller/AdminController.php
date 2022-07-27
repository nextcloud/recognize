<?php

namespace OCA\Recognize\Controller;

use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;

class AdminController extends Controller {
	private TagManager $tagManager;
	/**
	 * @var \OCP\BackgroundJob\IJobList
	 */
	private IJobList $jobList;

	public function __construct($appName, IRequest $request, TagManager $tagManager, IJobList $jobList) {
		parent::__construct($appName, $request);
		$this->tagManager = $tagManager;
		$this->jobList = $jobList;
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
}
