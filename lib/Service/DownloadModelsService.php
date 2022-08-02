<?php

namespace OCA\Recognize\Service;

use OCA\Recognize\Helper\TAR;
use OCP\Http\Client\IClientService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DownloadModelsService {
	private IClientService $clientService;

	public function __construct(IClientService $clientService) {
		$this->clientService = $clientService;
	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function download() : void {
		$targetPath = __DIR__ . '/../../models';
		if (file_exists($targetPath)) {
			// remove models directory
			$it = new RecursiveDirectoryIterator($targetPath, RecursiveDirectoryIterator::SKIP_DOTS);
			$files = new RecursiveIteratorIterator($it,
				RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($files as $file) {
				if ($file->isDir()) {
					rmdir($file->getRealPath());
				} else {
					unlink($file->getRealPath());
				}
			}
			rmdir($targetPath);
		}

		$archiveUrl = $this->getArchiveUrl($this->getNeededArchiveRef());
		$archivePath = __DIR__ . '/../../models.tar.gz';
		$this->clientService->newClient()->get($archiveUrl, ['sink' => $archivePath, 'timeout' => 60]);
		$tarManager = new TAR($archivePath);
		$tarFiles = $tarManager->getFiles();
		$mainFolder = $tarFiles[0];
		$modelFolder = $mainFolder . 'models';
		$modelFiles = array_filter($tarFiles, function ($path) use ($modelFolder) {
			return strpos($path, $modelFolder . '/') === 0 || strpos($path, $modelFolder) === 0;
		});
		$tarManager->extractList($modelFiles, $targetPath, $modelFolder . '/');
	}

	public function getArchiveUrl(string $ref): string {
		return "https://github.com/nextcloud/recognize/archive/$ref.tar.gz";
	}

	/**
	 * @throws \JsonException
	 */
	public function getRecognizeVersion() : string {
		$packageJson = \json_decode(file_get_contents(__DIR__. '/../../package.json'), true, 512, \JSON_THROW_ON_ERROR);
		return $packageJson['version'];
	}

	public function getNeededArchiveRef() {
		return getenv('GITHUB_REF') ? getenv('GITHUB_REF') : 'refs/tags/v' . $this->getRecognizeVersion();
	}
}
