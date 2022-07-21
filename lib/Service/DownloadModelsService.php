<?php

namespace OCA\Recognize\Service;

use OCA\Recognize\Helper\TAR;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DownloadModelsService {

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

		$archiveURL = $this->getArchiveUrl($this->getNeededArchiveRef());
		$archivePath = __DIR__ . '/../../models.tar.gz';
		$read = fopen($archiveURL, 'r');
		$write = fopen($archivePath, 'w');
		stream_copy_to_stream($read, $write);
		$tarManager = new TAR($archivePath);
		$files = $tarManager->getFiles();
		$mainFolder = $files[0];
		$modelFolder = $mainFolder . 'models';
		$modelFiles = array_filter($files, function ($path) use ($modelFolder) {
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
