<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Dav\Faces;

use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IRootFolder;
use OCP\IPreview;
use OCP\ITagManager;
use OCP\IUser;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;
use Sabre\DAV\IMoveTarget;
use Sabre\DAV\INode;

class FaceRoot implements ICollection, IMoveTarget {
	private FaceClusterMapper $clusterMapper;
	private FaceCluster $cluster;
	private IUser $user;
	private FaceDetectionMapper $detectionMapper;
	private IRootFolder $rootFolder;
	private ITagManager $tagManager;
	private IPreview $previewManager;
	/**
	 * @var \OCA\Recognize\Dav\Faces\FacePhoto[]
	 */
	private array $children = [];

	public function __construct(FaceClusterMapper $clusterMapper, FaceCluster $cluster, IUser $user, FaceDetectionMapper $detectionMapper, IRootFolder $rootFolder, ITagManager $tagManager, IPreview $previewManager) {
		$this->clusterMapper = $clusterMapper;
		$this->cluster = $cluster;
		$this->user = $user;
		$this->detectionMapper = $detectionMapper;
		$this->rootFolder = $rootFolder;
		$this->tagManager = $tagManager;
		$this->previewManager = $previewManager;
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return $this->cluster->getTitle() !== '' ? $this->cluster->getTitle() : ''.$this->cluster->getId();
	}

	/**
	 * @inheritDoc
	 */
	public function setName($name) {
		try {
			$this->clusterMapper->findByUserAndTitle($this->user->getUID(), $name);
			throw new Forbidden('Not allowed to create duplicate names');
		} catch (DoesNotExistException $e) {
			// pass
		}
		$this->cluster->setTitle(basename($name));
		$this->clusterMapper->update($this->cluster);
	}

	/**
	 * @inheritDoc
	 */
	public function createDirectory($name) {
		throw new Forbidden('Not allowed to create directories in this folder');
	}

	/**
	 * @inheritDoc
	 */
	public function createFile($name, $data = null) {
		throw new Forbidden('Not allowed to create files in this folder');
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function getChildren(): array {
		if (count($this->children) === 0) {
			$detections = $this->detectionMapper->findByClusterId($this->cluster->getId());
			$detectionsWithFile = array_filter($detections, fn (FaceDetection $detection): bool => current($this->rootFolder->getUserFolder($this->user->getUID())->getById($detection->getFileId())) !== false);
			$this->children = array_map(function (FaceDetection $detection) {
				return new FacePhoto($this->detectionMapper, $detection, $this->rootFolder->getUserFolder($this->user->getUID()), $this->tagManager, $this->previewManager);
			}, $detectionsWithFile);
		}
		return $this->children;
	}

	public function getChild($name): FacePhoto {
		if (count($this->children) !== 0) {
			foreach ($this->getChildren() as $child) {
				if ($child->getName() === $name) {
					return $child;
				}
			}
			throw new NotFound("$name not found");
		}
		[$detectionId,] = explode('-', $name);
		try {
			$detection = $this->detectionMapper->find((int)$detectionId);
		} catch (DoesNotExistException $e) {
			throw new NotFound();
		}
		return new FacePhoto($this->detectionMapper, $detection, $this->rootFolder->getUserFolder($this->user->getUID()), $this->tagManager, $this->previewManager);
	}

	public function childExists($name): bool {
		try {
			$this->getChild($name);
			return true;
		} catch (NotFound $e) {
			return false;
		}
	}

	public function moveInto($targetName, $sourcePath, INode $sourceNode) {
		if ($sourceNode instanceof FacePhoto) {
			$sourceNode->getFaceDetection()->setClusterId($this->cluster->getId());
			$this->detectionMapper->update($sourceNode->getFaceDetection());
			return true;
		}
		throw new Forbidden('Not a photo with a detected face, you can only move photos from the faces collection or the unassigned-faces here');
	}

	/**
	 * @inheritDoc
	 * @throws \OCP\DB\Exception
	 */
	public function delete() {
		$this->clusterMapper->delete($this->cluster);
	}

	/**
	 * @inheritDoc
	 */
	public function getLastModified() : int {
		return 0;
	}

	public function getPreviewImage() : string {
		$detection = $this->detectionMapper->findDetectionForPreviewImageByClusterId($this->cluster->getId());
		return json_encode([
			'fileid' => $detection->getFileId(),
			'detection' => $detection->toArray()
		]);
	}
}
