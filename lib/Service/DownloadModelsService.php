<?php

namespace OCA\Recognize\Service;

use OCA\Recognize\Helper\TAR;

class DownloadModelsService {

    /**
     * @return void
     * @throws \Exception
     */
    public function download() : void {
        $targetPath = __DIR__ . '/../../models';
        if (file_exists($targetPath)) {
            return;
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
        $modelFiles = array_filter($files, function($path) use ($modelFolder) {
            return str_starts_with($path, $modelFolder . '/') || str_starts_with($path, $modelFolder);
        });
        $tarManager->extractList($modelFiles, $targetPath, $modelFolder . '/');
    }

    public function getArchiveUrl(string $ref): string {
        return "https://github.com/nextcloud/recognize/archive/$ref.tar.gz";
    }

    public function getRecognizeVersion() : string {
        $packageJson = \json_decode(file_get_contents(__DIR__. '/../../package.json'), true);
        return $packageJson['version'];
    }

    public function getNeededArchiveRef() {
        return getenv('GITHUB_REF') ? getenv('GITHUB_REF') : 'refs/tags/v' . $this->getRecognizeVersion();
    }
}
