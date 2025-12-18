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

final class RecognizeHome implements ICollection {
	/**
	 * @param array{uri: string} $principalInfo
	 */
	public function __construct(
		private readonly array $principalInfo,
		private readonly FaceClusterMapper $faceClusterMapper,
		private readonly IUser $user,
		private readonly FaceDetectionMapper $faceDetectionMapper,
		private readonly IRootFolder $rootFolder,
		private readonly ITagManager $tagManager,
		private readonly IPreview $previewManager,
	) {
	}

	public function getName(): string {
		/** @var string $name */
		[, $name] = \Sabre\Uri\split($this->principalInfo['uri']);
		return $name;
	}

	public function setName($name): never {
		throw new Forbidden('Permission denied to rename this folder');
	}

	public function delete(): never {
		throw new Forbidden();
	}

	public function createFile($name, $data = null): never {
		throw new Forbidden('Not allowed to create files in this folder');
	}

	public function createDirectory($name): never {
		throw new Forbidden('Permission denied to create folders in this folder');
	}

	private function getFacesHome(): FacesHome {
		return new FacesHome($this->faceClusterMapper, $this->user, $this->faceDetectionMapper, $this->rootFolder, $this->tagManager, $this->previewManager);
	}

	private function getUnassignedFacesHome(): UnassignedFacesHome {
		return new UnassignedFacesHome($this->user, $this->faceDetectionMapper, $this->rootFolder, $this->tagManager, $this->previewManager);
	}

	public function getChild($name): UnassignedFacesHome|FacesHome {
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
