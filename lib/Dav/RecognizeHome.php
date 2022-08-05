<?php

namespace OCA\Recognize\Dav;

use OCA\Recognize\Dav\Faces\FacesHome;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\Files\IRootFolder;
use OCP\IUser;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

class RecognizeHome implements ICollection {
	private array $principalInfo;
	private FaceClusterMapper $faceClusterMapper;
	private IUser $user;
	/**
	 * @var \OCA\Recognize\Db\FaceDetectionMapper
	 */
	private FaceDetectionMapper $faceDetectionMapper;
	/**
	 * @var \OCP\Files\IRootFolder
	 */
	private IRootFolder $rootFolder;

	public function __construct(array $principalInfo, FaceClusterMapper $faceClusterMapper, IUser $user, FaceDetectionMapper $faceDetectionMapper, IRootFolder $rootFolder) {
		$this->principalInfo = $principalInfo;
		$this->faceClusterMapper = $faceClusterMapper;
		$this->user = $user;
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->rootFolder = $rootFolder;
	}

	public function getName(): string {
		[, $name] = \Sabre\Uri\split($this->principalInfo['uri']);
		return $name;
	}

	public function setName($name) {
		throw new Forbidden('Permission denied to rename this folder');
	}

	public function delete() {
		throw new Forbidden();
	}

	public function createFile($name, $data = null) {
		throw new Forbidden('Not allowed to create files in this folder');
	}

	public function createDirectory($name) {
		throw new Forbidden('Permission denied to create folders in this folder');
	}

	public function getChild($name) {
		if ($name === 'faces') {
			return new FacesHome($this->faceClusterMapper, $this->user, $this->faceDetectionMapper, $this->rootFolder);
		}

		throw new NotFound();
	}

	/**
	 * @return FacesHome[]
	 */
	public function getChildren(): array {
		return [new FacesHome($this->faceClusterMapper, $this->user, $this->faceDetectionMapper, $this->rootFolder)];
	}

	public function childExists($name): bool {
		return $name === 'faces';
	}

	public function getLastModified(): int {
		return 0;
	}
}
