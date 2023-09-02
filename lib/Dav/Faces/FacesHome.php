<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Dav\Faces;

use OC\Metadata\IMetadataManager;
use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IRootFolder;
use OCP\IPreview;
use OCP\ITagManager;
use OCP\IUser;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

class FacesHome implements ICollection {
	private FaceClusterMapper $faceClusterMapper;
	private IUser $user;
	private FaceDetectionMapper $faceDetectionMapper;
	private IRootFolder $rootFolder;
	private array $children = [];
	private ITagManager $tagManager;
	private IMetadataManager $metadataManager;
	private IPreview $previewManager;

	public function __construct(FaceClusterMapper $faceClusterMapper, IUser $user, FaceDetectionMapper $faceDetectionMapper, IRootFolder $rootFolder, ITagManager $tagManager, IMetadataManager $metadataManager, IPreview $previewManager) {
		$this->faceClusterMapper = $faceClusterMapper;
		$this->user = $user;
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->rootFolder = $rootFolder;
		$this->tagManager = $tagManager;
		$this->metadataManager = $metadataManager;
		$this->previewManager = $previewManager;
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
		$entity = new FaceCluster();
		$entity->setUserId($this->user->getUID());
		$entity->setTitle($name);
		$this->faceClusterMapper->insert($entity);
	}

	public function createFile($name, $data = null) {
		throw new Forbidden('Not allowed to create files in this folder');
	}

	public function getChild($name) : FaceRoot {
		if (count($this->children) !== 0) {
			foreach ($this->getChildren() as $child) {
				if ($child->getName() === $name) {
					return $child;
				}
			}
			throw new NotFound();
		}
		try {
			$cluster = $this->faceClusterMapper->findByUserAndTitle($this->user->getUID(), $name);
		} catch (DoesNotExistException $e) {
			try {
				$cluster = $this->faceClusterMapper->find((int) $name);
				if ($cluster->getUserId() !== $this->user->getUID()) {
					throw new NotFound();
				}
			} catch (DoesNotExistException $e) {
				throw new NotFound();
			}
		}

		return new FaceRoot($this->faceClusterMapper, $cluster, $this->user, $this->faceDetectionMapper, $this->rootFolder, $this->metadataManager, $this->tagManager, $this->previewManager);
	}

	/**
	 * @return \OCA\Recognize\Dav\Faces\FaceRoot[]
	 * @throws \OCP\DB\Exception
	 */
	public function getChildren(): array {
		$clusters = $this->faceClusterMapper->findByUserId($this->user->getUID());
		if (count($this->children) === 0) {
			$this->children = array_map(function (FaceCluster $cluster) {
				return new FaceRoot($this->faceClusterMapper, $cluster, $this->user, $this->faceDetectionMapper, $this->rootFolder, $this->metadataManager, $this->tagManager, $this->previewManager);
			}, $clusters);
		}
		return $this->children;
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
