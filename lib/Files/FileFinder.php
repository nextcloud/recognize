<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Files;

use OCA\Recognize\Service\Logger;
use OCP\Files\File;
use OCP\Files\Folder;
use Psr\Log\LoggerInterface;

abstract class FileFinder {
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string[] $formats
	 */
	private array $formats;


	private int $maxFileSize = 0;

	/**
	 * @var string[] $ignoreMarkers
	 */
	private array $ignoreMarkers;

	public function __construct(Logger $logger) {
		$this->logger = $logger;
	}

	/**
	 * @param string[] $formats
	 * @return $this
	 */
	public function setFormats(array $formats):self {
		$this->formats = $formats;
		return $this;
	}

	/**
	 * @param int $fileSize
	 * @return $this
	 */
	public function setMaxFileSize(int $fileSize):self {
		$this->maxFileSize = $fileSize;
		return $this;
	}

	/**
	 * @param string[] $markerFilenames
	 * @return $this
	 */
	public function setIgnoreMarkers(array $markerFilenames): self {
		$this->ignoreMarkers = $markerFilenames;
		return $this;
	}

	public function isDirectoryIgnored(Folder $folder): bool {
		$foundMarkers = array_filter($this->ignoreMarkers, static function ($markerFile) use ($folder) {
			return $folder->nodeExists($markerFile);
		});

		return count($foundMarkers) > 0;
	}

	public function isFileEligible(string $user, File $node) : bool {
		if ($node->getMountPoint()->getMountType() === 'shared' && $node->getOwner()->getUID() !== $user) {
			$this->logger->debug('Not original owner of '.$node->getPath());
			return false;
		}
		$mimeType = $node->getMimetype();
		if (!in_array($mimeType, $this->formats)) {
			$this->logger->debug('Not a supported format: '.$node->getPath());
			return false;
		}
		if ($this->maxFileSize !== 0 && $this->maxFileSize < $node->getSize()) {
			$this->logger->debug('File is too large: '.$node->getPath());
			return false;
		}

		return true;
	}

	/**
	 * @param string $user
	 * @param \OCP\Files\File $node
	 * @return void
	 * @throws \Throwable
	 */
	abstract public function foundFile(string $user, File $node) : void;
}
