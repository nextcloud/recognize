<?php

namespace OCA\Recognize\Dav\Faces;

use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\Files\IRootFolder;
use OCP\IUser;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

class FacesHome implements ICollection {
	private FaceClusterMapper $faceClusterMapper;
	private IUser $user;
	private FaceDetectionMapper $faceDetectionMapper;
	private IRootFolder $rootFolder;

	public function __construct(FaceClusterMapper $faceClusterMapper, IUser $user, FaceDetectionMapper $faceDetectionMapper, IRootFolder $rootFolder) {
		$this->faceClusterMapper = $faceClusterMapper;
		$this->user = $user;
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->rootFolder = $rootFolder;
	}

	public function delete() {
		throw new Forbidden();
	}

	public function getName(): string {
		return 'faces';
	}

	public function setName($name) {
		throw new Forbidden('Permission denied to rename this folder');
	}

	public function createDirectory($name) {
		throw new Forbidden('Not allowed to create directories in this folder');
	}

	public function createFile($name, $data = null) {
		throw new Forbidden('Not allowed to create files in this folder');
	}

	public function getChild($name) {
		foreach ($this->getChildren() as $child) {
			if ($child->getName() === $name) {
				return $child;
			}
		}

		throw new NotFound();
	}

	/**
	 * @return \OCA\Recognize\Dav\Faces\FaceRoot[]
	 * @throws \OCP\DB\Exception
	 */
	public function getChildren(): array {
		$clusters = $this->faceClusterMapper->findByUserId($this->user->getUID());
		return array_map(function (FaceCluster $cluster) {
			return new FaceRoot($this->faceClusterMapper, $cluster, $this->user, $this->faceDetectionMapper, $this->rootFolder);
		}, $clusters);
	}

	public function childExists($name): bool {
		try {
			$this->getChild($name);
			return true;
		} catch (NotFound $e) {
			return false;
		}
	}

	public function getLastModified(): int {
		return 0;
	}
}
