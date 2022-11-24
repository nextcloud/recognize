<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Dav\Faces;

use OCA\DAV\Connector\Sabre\File;
use \OCA\DAV\Connector\Sabre\FilesPlugin;
use \OCA\DAV\Connector\Sabre\TagsPlugin;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\FaceDetectionWithTitle;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class PropFindPlugin extends ServerPlugin {
	public const FACE_DETECTIONS_PROPERTYNAME = '{http://nextcloud.org/ns}face-detections';
	public const FILE_NAME_PROPERTYNAME = '{http://nextcloud.org/ns}file-name';
	public const REALPATH_PROPERTYNAME = '{http://nextcloud.org/ns}realpath';
	public const NBITEMS_PROPERTYNAME = '{http://nextcloud.org/ns}nbItems';

	private Server $server;
	private FaceDetectionMapper $faceDetectionMapper;

	public function __construct(FaceDetectionMapper $faceDetectionMapper) {
		$this->faceDetectionMapper = $faceDetectionMapper;
	}

	public function initialize(Server $server) {
		$this->server = $server;

		$this->server->on('propFind', [$this, 'propFind']);
	}


	public function propFind(PropFind $propFind, INode $node) {
		if ($node instanceof FacePhoto) {
			$propFind->handle(self::FILE_NAME_PROPERTYNAME, function () use ($node) {
				return $node->getFile()->getName();
			});

			$propFind->handle(self::FACE_DETECTIONS_PROPERTYNAME, function () use ($node) {
				return json_encode(array_map(fn (FaceDetectionWithTitle $face) => $face->toArray(), array_values(array_filter($this->faceDetectionMapper->findByFileIdWithTitle($node->getFile()->getId()), fn (FaceDetection $face) => $face->getClusterId() !== null))));
			});

			$propFind->handle(FilesPlugin::INTERNAL_FILEID_PROPERTYNAME, fn () => $node->getFile()->getId());
			$propFind->handle(self::FILE_NAME_PROPERTYNAME, fn () => $node->getFile()->getName());
			$propFind->handle(self::REALPATH_PROPERTYNAME, fn () => $node->getFile()->getPath());
			$propFind->handle(FilesPlugin::FILE_METADATA_SIZE, fn () => json_encode($node->getMetadata()));
			$propFind->handle(FilesPlugin::HAS_PREVIEW_PROPERTYNAME, fn () => json_encode($node->hasPreview()));
			$propFind->handle(TagsPlugin::FAVORITE_PROPERTYNAME, fn () => $node->isFavorite() ? 1 : 0);
		}

		if ($node instanceof FaceRoot) {
			$propFind->handle(self::NBITEMS_PROPERTYNAME, fn () => count($node->getChildren()));
		}

		if ($node instanceof File) {
			$propFind->handle(self::FACE_DETECTIONS_PROPERTYNAME, function () use ($node) {
				return json_encode(array_map(fn (FaceDetectionWithTitle $face) => $face->toArray(), array_values(array_filter($this->faceDetectionMapper->findByFileIdWithTitle($node->getId()), fn (FaceDetection $face) => $face->getClusterId() !== null))));
			});
		}
	}
}
