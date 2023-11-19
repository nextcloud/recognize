<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Dav\Faces;

use \Rubix\ML\Kernels\Distance\Euclidean;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\FaceClusterAnalyzer;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IPreview;
use OCP\ITagManager;
use OCP\ITags;
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

	/**
	 * @inheritDoc
	 */
	public function getName() {
		$file = $this->getFile();
		$detection = $this->getFaceDetection();
		return $detection->getId() . '-' . $file->getName();
	}

	/**
	 * @inheritDoc
	 * @throws \OCP\DB\Exception
	 */
	public function delete() {
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

	/**
	 * @inheritDoc
	 */
	public function setName($name) {
		throw new Forbidden('Cannot rename photos through faces API');
	}

	/**
	 * @inheritDoc
	 */
	public function put($data) {
		throw new Forbidden('Can\'t write to photos trough the faces api');
	}

	public function getFile() : File {
		if ($this->file === null) {
			$nodes = $this->userFolder->getById($this->faceDetection->getFileId());
			$node = current($nodes);
			if ($node) {
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

	/**
	 * @inheritDoc
	 */
	public function getContentType() {
		return $this->getFile()->getMimeType();
	}

	/**
	 * @inheritDoc
	 */
	public function getETag() {
		return $this->getFile()->getEtag();
	}

	/**
	 * @inheritDoc
	 */
	public function getSize() {
		return $this->getFile()->getSize();
	}

	/**
	 * @inheritDoc
	 */
	public function getLastModified() {
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
