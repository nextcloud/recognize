<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagObjectMapper;
use Psr\Log\LoggerInterface;

class FileFinderService {
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var ISystemTagObjectMapper
	 */
	private $objectMapper;
	/**
	 * @var ISystemTag
	 */
	private $recognizedTag;

	/**
	 * @var string[] $formats
	 */
	private $formats;

	/**
	 * @var int
	 */
	private $maxFileSize = 0;

	/**
	 * @var string[] $ignoreMarkers
	 */
	private $ignoreMarkers;

	public function __construct(Logger $logger, ISystemTagObjectMapper $objectMapper, TagManager $tagManager) {
		$this->logger = $logger;
		$this->objectMapper = $objectMapper;
		$this->recognizedTag = $tagManager->getProcessedTag();
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
	public function setIgnoreMarkers(array $markerFilenames):self {
		$this->ignoreMarkers = $markerFilenames;
		return $this;
	}

	/**
	 * @throws NotFoundException|InvalidPathException
	 */
	public function findFilesInFolder(Folder $folder, &$results = []):array {
		$this->logger->debug('Searching '.$folder->getInternalPath());

		$foundMarkers = array_filter($this->ignoreMarkers, static function ($markerFile) use ($folder) {
			return $folder->nodeExists($markerFile);
		});

		if (count($foundMarkers) > 0) {
			$this->logger->debug('Passing '.$folder->getInternalPath());
			return $results;
		}

		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			if ($node instanceof Folder) {
				$this->findFilesInFolder($node, $results);
			} elseif ($node instanceof File) {
				if ($this->objectMapper->haveTag([$node->getId()], 'files', $this->recognizedTag->getId())) {
					continue;
				}
				$mimeType = $node->getMimetype();
				if (!in_array($mimeType, $this->formats)) {
					continue;
				}
				if ($this->maxFileSize !== 0 && $this->maxFileSize < $node->getSize()) {
					continue;
				}
				$this->logger->debug('Found '.$node->getPath());
				$results[] = $node;
			}
		}
		return $results;
	}
}
