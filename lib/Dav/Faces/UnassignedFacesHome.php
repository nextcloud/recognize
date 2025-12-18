<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Dav\Faces;

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

final class UnassignedFacesHome implements ICollection {
	private IUser $user;
	private FaceDetectionMapper $faceDetectionMapper;
	private IRootFolder $rootFolder;
	/** @var list<UnassignedFacePhoto>  */
	private array $children = [];
	private ITagManager $tagManager;
	private IPreview $previewManager;

	public function __construct(IUser $user, FaceDetectionMapper $faceDetectionMapper, IRootFolder $rootFolder, ITagManager $tagManager, IPreview $previewManager) {
		$this->user = $user;
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->rootFolder = $rootFolder;
		$this->tagManager = $tagManager;
		$this->previewManager = $previewManager;
	}

	public function delete(): never {
		throw new Forbidden();
	}

	public function getName(): string {
		return 'unassigned-faces';
	}

	public function setName($name): never {
		throw new Forbidden('Permission denied to rename this folder');
	}

	public function createDirectory($name): never {
		throw new Forbidden('Not allowed to create directories in this folder');
	}

	public function createFile($name, $data = null): never {
		throw new Forbidden('Not allowed to create files in this folder');
	}

	/**
	 * @throws NotFound
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function getChild($name): UnassignedFacePhoto {
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
			$detection = $this->faceDetectionMapper->find((int)$detectionId);
		} catch (DoesNotExistException $e) {
			throw new NotFound();
		}
		if ($detection->getClusterId() !== -1) {
			throw new NotFound();
		}

		return new UnassignedFacePhoto($this->faceDetectionMapper, $detection, $this->rootFolder->getUserFolder($this->user->getUID()), $this->tagManager, $this->previewManager);
	}

	public function childExists($name): bool {
		try {
			$this->getChild($name);
			return true;
		} catch (NotFound $e) {
			return false;
		}
	}

	/**
	 * @return list<UnassignedFacePhoto>
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function getChildren(): array {
		if (count($this->children) !== 0) {
			return $this->children;
		}

		$detections = $this->faceDetectionMapper->findRejectedByUserId($this->user->getUID());
		$detectionsWithFile = array_filter($detections, fn (FaceDetection $detection): bool => current($this->rootFolder->getUserFolder($this->user->getUID())->getById($detection->getFileId())) !== false);
		$this->children = array_values(array_map(fn (FaceDetection $detection): UnassignedFacePhoto
			=> new UnassignedFacePhoto($this->faceDetectionMapper, $detection, $this->rootFolder->getUserFolder($this->user->getUID()), $this->tagManager, $this->previewManager),
			$detectionsWithFile));

		return $this->children;
	}

	public function getLastModified(): int {
		return 0;
	}
}
