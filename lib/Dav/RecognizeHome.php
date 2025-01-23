<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Dav;

use OCA\Recognize\Dav\Faces\FacesHome;
use OCA\Recognize\Dav\Faces\UnassignedFacesHome;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\Files\IRootFolder;
use OCP\IPreview;
use OCP\ITagManager;
use OCP\IUser;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

class RecognizeHome implements ICollection {
	private array $principalInfo;
	private FaceClusterMapper $faceClusterMapper;
	private IUser $user;
	private FaceDetectionMapper $faceDetectionMapper;
	private IRootFolder $rootFolder;
	private ITagManager $tagManager;
	private IPreview $previewManager;

	public function __construct(array $principalInfo, FaceClusterMapper $faceClusterMapper, IUser $user, FaceDetectionMapper $faceDetectionMapper, IRootFolder $rootFolder, ITagManager $tagManager, IPreview $previewManager) {
		$this->principalInfo = $principalInfo;
		$this->faceClusterMapper = $faceClusterMapper;
		$this->user = $user;
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->rootFolder = $rootFolder;
		$this->tagManager = $tagManager;
		$this->previewManager = $previewManager;
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

	private function getFacesHome() {
		return new FacesHome($this->faceClusterMapper, $this->user, $this->faceDetectionMapper, $this->rootFolder, $this->tagManager, $this->previewManager);
	}

	private function getUnassignedFacesHome() {
		return new UnassignedFacesHome($this->user, $this->faceDetectionMapper, $this->rootFolder, $this->tagManager, $this->previewManager);
	}

	public function getChild($name) {
		if ($name === 'faces') {
			return $this->getFacesHome();
		}
		if ($name === 'unassigned-faces') {
			return $this->getUnassignedFacesHome();
		}

		throw new NotFound();
	}

	/**
	 * @return FacesHome[]
	 */
	public function getChildren(): array {
		return [$this->getFacesHome(), $this->getUnassignedFacesHome()];
	}

	public function childExists($name): bool {
		return $name === 'faces' || $name === 'unassigned-faces';
	}

	public function getLastModified(): int {
		return 0;
	}
}
