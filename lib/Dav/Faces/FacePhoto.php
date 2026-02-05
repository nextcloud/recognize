<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Dav\Faces;

use \OCA\Recognize\Vendor\Rubix\ML\Kernels\Distance\Euclidean;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\FaceClusterAnalyzer;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IPreview;
use OCP\ITagManager;
use OCP\ITags;
use Override;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\IFile;

class FacePhoto implements IFile {
	private FaceDetectionMapper $detectionMapper;
	private FaceDetection $faceDetection;
	private Folder $userFolder;
	private ?File $file = null;
	private ITagManager $tagManager;
	private IPreview $preview;

	public function __construct(FaceDetectionMapper $detectionMapper, FaceDetection $faceDetection, Folder $userFolder, ITagManager $tagManager, IPreview $preview) {
		$this->detectionMapper = $detectionMapper;
		$this->faceDetection = $faceDetection;
		$this->userFolder = $userFolder;
		$this->tagManager = $tagManager;
		$this->preview = $preview;
	}

	#[Override]
	public function getName(): string {
		$file = $this->getFile();
		$detection = $this->getFaceDetection();
		return $detection->getId() . '-' . $file->getName();
	}

	#[Override]
	public function delete(): void {
		$detections = $this->detectionMapper->findByClusterId($this->faceDetection->getClusterId());
		if (count($detections) > 1) {
			$centroid = FaceClusterAnalyzer::calculateCentroidOfDetections($detections);
			$distance = new Euclidean();
			$distanceValue = $distance->compute($centroid, $this->faceDetection->getVector());
			// Set threshold to avoid recreating the same mistake
			$this->faceDetection->setThreshold($distanceValue);
		}
		$this->faceDetection->setClusterId(null);
		$this->detectionMapper->update($this->faceDetection);
	}

	#[Override]
	public function setName($name): never {
		throw new Forbidden('Cannot rename photos through faces API');
	}

	#[Override]
	public function put($data): never {
		throw new Forbidden('Can\'t write to photos trough the faces api');
	}

	public function getFile() : File {
		if ($this->file === null) {
			$node = $this->userFolder->getFirstNodeById($this->faceDetection->getFileId());
			if ($node !== null) {
				if ($node instanceof File) {
					return $this->file = $node;
				} else {
					throw new NotFound("Photo is a folder");
				}
			} else {
				throw new NotFound("Photo ".$this->faceDetection->getFileId()." not found for user");
			}
		} else {
			return $this->file;
		}
	}

	public function getFaceDetection() : FaceDetection {
		return $this->faceDetection;
	}

	/**
	 * @inheritDoc
	 * @throws \Sabre\DAV\Exception\NotFound
	 */
	public function get() {
		return $this->getFile()->fopen('r');
	}

	#[Override]
	public function getContentType(): string {
		return $this->getFile()->getMimeType();
	}

	#[Override]
	public function getETag(): string {
		return $this->getFile()->getEtag();
	}

	#[Override]
	public function getSize(): float|int {
		return $this->getFile()->getSize();
	}

	#[Override]
	public function getLastModified(): int {
		return $this->getFile()->getMTime();
	}

	public function hasPreview(): bool {
		return $this->preview->isAvailable($this->getFile());
	}

	public function isFavorite(): bool {
		$tagger = $this->tagManager->load('files');
		$tags = $tagger->getTagsForObjects([$this->getFile()->getId()]);

		if ($tags === false || empty($tags)) {
			return false;
		}

		return array_search(ITags::TAG_FAVORITE, current($tags)) !== false;
	}
}
