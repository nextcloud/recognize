<?php

namespace OCA\Recognize\Dav\Faces;

use OCA\DAV\Connector\Sabre\File;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\FaceDetectionWithTitle;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class PropFindPlugin extends ServerPlugin {
	private Server $server;
	private FaceDetectionMapper $faceDetectionMapper;
	private FaceClusterMapper $clusterMapper;

	public const INTERNAL_FILEID_PROPERTYNAME = '{http://owncloud.org/ns}fileid';
	public const FILE_METADATA_SIZE = '{http://nextcloud.org/ns}file-metadata-size';
	public const HAS_PREVIEW_PROPERTYNAME = '{http://nextcloud.org/ns}has-preview';
	public const FAVORITE_PROPERTYNAME = '{http://owncloud.org/ns}favorite';

	public function __construct(FaceDetectionMapper $faceDetectionMapper, FaceClusterMapper $clusterMapper) {
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->clusterMapper = $clusterMapper;
	}

	public function initialize(Server $server) {
		$this->server = $server;

		$this->server->on('propFind', [$this, 'propFind']);
	}


	public function propFind(PropFind $propFind, INode $node) {
		if ($node instanceof FacePhoto) {
			$propFind->handle('{http://nextcloud.org/ns}file-name', function () use ($node) {
				return $node->getFile()->getName();
			});

			$propFind->handle('{http://nextcloud.org/ns}face-detections', function () use ($node) {
				return json_encode(array_map(fn (FaceDetectionWithTitle $face) => $face->toArray(), array_values(array_filter($this->faceDetectionMapper->findByFileIdWithTitle($node->getFile()->getId()), fn (FaceDetection $face) => $face->getClusterId() !== null))));
			});

			$propFind->handle(self::INTERNAL_FILEID_PROPERTYNAME, fn () => $node->getFile()->getId());
			$propFind->handle('{http://nextcloud.org/ns}file-name', fn () => $node->getFile()->getName());
			$propFind->handle('{http://nextcloud.org/ns}realpath', fn () => $node->getFile()->getPath());
			$propFind->handle(self::FILE_METADATA_SIZE, fn () => json_encode($node->getMetadata()));
			$propFind->handle(self::HAS_PREVIEW_PROPERTYNAME, fn () => json_encode($node->hasPreview()));
			$propFind->handle(self::FAVORITE_PROPERTYNAME, fn () => $node->isFavorite() ? 1 : 0);
		}

		if ($node instanceof File) {
			$propFind->handle('{http://nextcloud.org/ns}face-detections', function () use ($node) {
				return json_encode(array_map(fn (FaceDetectionWithTitle $face) => $face->toArray(), array_values(array_filter($this->faceDetectionMapper->findByFileIdWithTitle($node->getId()), fn (FaceDetection $face) => $face->getClusterId() !== null))));
			});
		}
	}
}
