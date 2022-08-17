<?php

namespace OCA\Recognize\Dav\Faces;

use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\Files\IRootFolder;
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

	public function __construct(FaceClusterMapper $clusterMapper, FaceCluster $cluster, IUser $user, FaceDetectionMapper $detectionMapper, IRootFolder $rootFolder) {
		$this->clusterMapper = $clusterMapper;
		$this->cluster = $cluster;
		$this->user = $user;
		$this->detectionMapper = $detectionMapper;
		$this->rootFolder = $rootFolder;
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return $this->cluster->getTitle() !== '' ? $this->cluster->getTitle() : 'Person '.$this->cluster->getId();
	}

	/**
	 * @inheritDoc
	 */
	public function setName($name) {
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
		return array_map(function (FaceDetection $detection) {
			return new FacePhoto($this->detectionMapper, $this->cluster, $detection, $this->rootFolder->getUserFolder($this->user->getUID()));
		}, $this->detectionMapper->findByClusterId($this->cluster->getId()));
	}

	public function getChild($name): FacePhoto {
		foreach ($this->getChildren() as $child) {
			if ($child->getName() === $name) {
				return $child;
			}
		}
		throw new NotFound("$name not found");
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
		throw new Forbidden('Not a photo with a detected face, you can only move photos from the faces collection here');
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
}
