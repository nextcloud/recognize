<?php

namespace OCA\Recognize\Files;

use OCA\Recognize\Service\Logger;
use OCP\Files\File;
use OCP\Files\Folder;

class FileCrawler {
	private Logger $logger;

	public function __construct(Logger $logger) {
		$this->logger = $logger;
	}

	/**
	 * @param string $user
	 * @param $folder
	 * @param \OCA\Recognize\Files\FileFinder[] $finders
	 * @return void
	 */
	public function crawlFolder(string $user, $folder, array $finders) {
		$interestedFinders = array_filter($finders, function (FileFinder $finder) use ($folder) {
			return !$finder->isDirectoryIgnored($folder);
		});

		if (count($interestedFinders) === 0) {
			return;
		}

		try {
			$nodes = $folder->getDirectoryListing();
		} catch (\Exception $e) {
			$this->logger->debug('Error reading directory '.$folder->getInternalPath().': '.$e->getMessage());
			return;
		}

		foreach ($nodes as $node) {
			if ($node instanceof Folder) {
				$this->crawlFolder($user, $node, $interestedFinders);
			} elseif ($node instanceof File) {
				$wantedFinders = array_filter($interestedFinders, function (FileFinder $finder) use ($user, $node) {
					return $finder->isFileEligible($user, $node);
				});

				foreach ($wantedFinders as $finder) {
					try {
						$finder->foundFile($user, $node);
					} catch (\Throwable $e) {
						$this->logger->debug('Error crawling file '.$node->getPath());
						$this->logger->debug($e->getMessage());
					}
				}
			}
		}
	}
}
