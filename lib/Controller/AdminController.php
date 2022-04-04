<?php

namespace OCA\Recognize\Controller;

use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class AdminController extends Controller {
	/**
	 * @var \OCA\Recognize\Service\TagManager
	 */
	private $tagManager;

	public function __construct($appName, IRequest $request, TagManager $tagManager) {
		parent::__construct($appName, $request);
		$this->tagManager = $tagManager;
	}

	public function reset() {
		$this->tagManager->resetClassifications();
		return new JSONResponse([]);
	}

	public function count() {
		$count = count($this->tagManager->findClassifiedFiles());
		return new JSONResponse(['count' => $count]);
	}

	public function countMissed() {
		$count = count($this->tagManager->findMissedClassifications());
		return new JSONResponse(['count' => $count]);
	}

    public function avx() {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        return new JSONResponse(['avx' => str_contains($cpuinfo, 'avx')]);
    }

    public function platform() {
        try {
            exec('lscpu --json' . ' 2>&1', $output, $returnCode);
        } catch (\Throwable $e) {
        }

        if ($returnCode !== 0) {
            return new JSONResponse(['platform' => null]);
        }

        $lscpu = \json_decode(trim(implode("\n", $output)), true);
        return new JSONResponse(['platform' => $lscpu['lscpu'][0]['data']]);
    }

    public function musl() {
        try {
            exec('ldd /bin/ls' . ' 2>&1', $output, $returnCode);
        } catch (\Throwable $e) {
        }

        if ($returnCode !== 0) {
            return new JSONResponse(['platform' => null]);
        }

        $ldd = trim(implode("\n", $output));
        return new JSONResponse(['musl' => str_contains($ldd, 'musl')]);
    }
}
