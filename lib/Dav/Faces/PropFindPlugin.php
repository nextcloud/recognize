<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Dav\Faces;

use \OCA\DAV\Connector\Sabre\FilesPlugin;
use \OCA\DAV\Connector\Sabre\TagsPlugin;
use OCA\DAV\Connector\Sabre\File;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\FaceDetectionWithTitle;
use OCP\Files\DavUtil;
use OCP\IPreview;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

final class PropFindPlugin extends ServerPlugin {
	public const FACE_DETECTIONS_PROPERTYNAME = '{http://nextcloud.org/ns}face-detections';
	public const FILE_NAME_PROPERTYNAME = '{http://nextcloud.org/ns}file-name';
	public const REALPATH_PROPERTYNAME = '{http://nextcloud.org/ns}realpath';
	public const NBITEMS_PROPERTYNAME = '{http://nextcloud.org/ns}nbItems';
	public const FACE_PREVIEW_IMAGE_PROPERTYNAME = '{http://nextcloud.org/ns}face-preview-image';

	private Server $server;

	public function __construct(
		private FaceDetectionMapper $faceDetectionMapper,
		private IPreview $previewManager,
	) {
	}

	public function initialize(Server $server) {
		$this->server = $server;

		$this->server->on('propFind', [$this, 'propFind']);
	}


	public function propFind(PropFind $propFind, INode $node) {
		if ($node instanceof FacePhoto) {
			$propFind->handle(self::FACE_DETECTIONS_PROPERTYNAME, function () use ($node) {
				return json_encode(
					array_map(
						fn (FaceDetectionWithTitle $face) => $face->toArray(),
						$this->faceDetectionMapper->findByFileIdWithTitle($node->getFile()->getId())
					)
				);
			});
			$propFind->handle(self::FILE_NAME_PROPERTYNAME, fn () => $node->getFile()->getName());
			$propFind->handle(self::REALPATH_PROPERTYNAME, fn () => $node->getFile()->getPath());
			$propFind->handle(FilesPlugin::INTERNAL_FILEID_PROPERTYNAME, fn () => $node->getFile()->getId());
			$propFind->handle(FilesPlugin::GETETAG_PROPERTYNAME, fn () => $node->getETag());
			$propFind->handle(TagsPlugin::FAVORITE_PROPERTYNAME, fn () => $node->isFavorite() ? 1 : 0);
			$propFind->handle(FilesPlugin::HAS_PREVIEW_PROPERTYNAME, fn () => json_encode($this->previewManager->isAvailable($node->getFile()->getFileInfo())));
			$propFind->handle(FilesPlugin::PERMISSIONS_PROPERTYNAME, function () use ($node): string {
				$permissions = DavUtil::getDavPermissions($node->getFile()->getFileInfo());
				$filteredPermissions = str_replace('R', '', $permissions);
				return $filteredPermissions;
			});

			foreach ($node->getFile()->getFileInfo()->getMetadata() as $metadataKey => $metadataValue) {
				$propFind->handle(FilesPlugin::FILE_METADATA_PREFIX.$metadataKey, $metadataValue);
			}
		}

		if ($node instanceof FaceRoot || $node instanceof UnassignedFacesHome) {
			$propFind->handle(self::NBITEMS_PROPERTYNAME, fn () => count($node->getChildren()));
		}

		if ($node instanceof FaceRoot) {
			$propFind->handle(self::FACE_PREVIEW_IMAGE_PROPERTYNAME, fn () => $node->getPreviewImage());
		}

		if ($node instanceof File) {
			$propFind->handle(self::FACE_DETECTIONS_PROPERTYNAME, function () use ($node) {
				return json_encode(
					array_map(
						fn (FaceDetectionWithTitle $face) => $face->toArray(),
						$this->faceDetectionMapper->findByFileIdWithTitle($node->getId()),
					)
				);
			});
		}
	}
}
