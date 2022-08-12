<?php

namespace OCA\Recognize\Dav\Faces;

use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\Files\File;
use OCP\Files\Folder;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\IFile;

class FacePhoto implements IFile {
	private FaceDetectionMapper $detectionMapper;
	private FaceDetection $faceDetection;
	private Folder $userFolder;

	public const TAG_FAVORITE = '_$!<Favorite>!$_';

	public function __construct(FaceDetectionMapper $detectionMapper, FaceDetection $faceDetection, Folder $userFolder) {
		$this->detectionMapper = $detectionMapper;
		$this->faceDetection = $faceDetection;
		$this->userFolder = $userFolder;
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		$file = $this->getFile();
		return $file->getId() . '-' . $file->getName();
	}

	/**
	 * @inheritDoc
	 * @throws \OCP\DB\Exception
	 */
	public function delete() {
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
		$nodes = $this->userFolder->getById($this->faceDetection->getFileId());
		$node = current($nodes);
		if ($node) {
			if ($node instanceof File) {
				return $node;
			} else {
				throw new NotFound("Photo is a folder");
			}
		} else {
			throw new NotFound("Photo not found for user");
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


	public function getFileInfo() {
		$nodes = $this->userFolder->getById($this->getFile()->getId());
		$node = current($nodes);
		if ($node) {
			return $node->getFileInfo();
		} else {
			throw new NotFound("Photo not found for user");
		}
	}

	public function getMetadata(): array {
		$metadataManager = \OCP\Server::get(\OC\Metadata\IMetadataManager::class);
		$file = $this->getFile();
		$sizeMetadata = $metadataManager->fetchMetadataFor('size', [$file->getId()])[$file->getId()];
		return $sizeMetadata->getMetadata();
	}

	public function hasPreview(): bool {
		$previewManager = \OCP\Server::get(\OCP\IPreview::class);
		return $previewManager->isAvailable($this->getFileInfo());
	}

	public function isFavorite(): bool {
		$tagManager = \OCP\Server::get(\OCP\ITagManager::class);
		$tagger = $tagManager->load('files');
		$tags = $tagger->getTagsForObjects([$this->getFile()->getId()]);

		if ($tags === false || empty($tags)) {
			return false;
		}

		return array_search(self::TAG_FAVORITE, current($tags)) !== false;
	}
}
