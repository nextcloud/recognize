<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Service;

use FilesystemIterator;
use OCA\Recognize\Helper\TAR;
use OCP\Http\Client\IClientService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function Safe\mkdir;

final class DownloadModelsService {
	private IClientService $clientService;
	private bool $isCLI;
	private SettingsService $settingsService;

	public function __construct(IClientService $clientService, bool $isCLI, SettingsService $settingsService) {
		$this->clientService = $clientService;
		$this->isCLI = $isCLI;
		$this->settingsService = $settingsService;
	}

	/**
	 * @param callable(string): void|null $log
	 * @return void
	 * @throws \Exception
	 */

	public function download() : void {
		$log ??= static function (string $message): void {
		};
		$targetPath = $this->settingsService->getSetting('models_target_path');
		if (!file_exists($targetPath)) {
			mkdir($targetPath, recursive: true);
		}
		$modelPath = $targetPath . '/models';
		if (file_exists($modelPath)) {
			$log('Removing existing models directory at ' . $targetPath);
			// remove models directory
			$it = new RecursiveDirectoryIterator($modelPath, FilesystemIterator::SKIP_DOTS);
			$files = new RecursiveIteratorIterator($it,
				RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($files as $file) {
				if ($file->isDir()) {
					rmdir($file->getRealPath());
				} else {
					unlink($file->getRealPath());
				}
			}
			rmdir($modelPath);
		}

		$archiveUrl = $this->getArchiveUrl($this->getNeededArchiveRef());

		$archivePath = $targetPath . '/models.tar.gz';
		$log('Downloading models archive from ' . $archiveUrl);
		$log('Saving archive to ' . $archivePath);
		$timeout = $this->isCLI ? 0 : 480;
		$this->clientService->newClient()->get($archiveUrl, ['sink' => $archivePath, 'timeout' => $timeout]);
		$log('Extracting models to ' . $targetPath);
		$tarManager = new TAR($archivePath);
		$tarFiles = $tarManager->getFiles();
		$mainFolder = $tarFiles[0];
		$modelFolder = $mainFolder . 'models';
		$modelFiles = array_values(array_filter($tarFiles, function ($path) use ($modelFolder) {
			return strpos($path, $modelFolder . '/') === 0 || strpos($path, $modelFolder) === 0;
		}));
		$tarManager->extractList($modelFiles, $targetPath, $modelFolder . '/');
		unlink($archivePath);
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

	public function getNeededArchiveRef() : string {
		return getenv('GITHUB_REF') ?: 'refs/tags/v' . $this->getRecognizeVersion();
	}
}
