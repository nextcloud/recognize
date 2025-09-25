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
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\FaceDetectionWithTitle;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\Files\DavUtil;
use OCP\IPreview;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

final class PropFindPlugin extends ServerPlugin {
	public const FACE_DETECTIONS_PROPERTYNAME = '{http://nextcloud.org/ns}face-detections';
	public const FILE_NAME_PROPERTYNAME = '{http://nextcloud.org/ns}file-name';
	public const REALPATH_PROPERTYNAME = '{http://nextcloud.org/ns}realpath';
	public const NBITEMS_PROPERTYNAME = '{http://nextcloud.org/ns}nbItems';
	public const FACE_PREVIEW_IMAGE_PROPERTYNAME = '{http://nextcloud.org/ns}face-preview-image';

	public const API_KEY_TIMEOUT = 60 * 60 * 24;

	private Server $server;

	public function __construct(
		private FaceDetectionMapper $faceDetectionMapper,
		private IPreview $previewManager,
		private FaceClusterMapper $faceClusterMapper,
		private ICrypto $crypto,
		private LoggerInterface $logger,
		private ITimeFactory $timeFactory,
	) {
	}

	public function initialize(Server $server) {
		$this->server = $server;

		$this->server->on('propFind', [$this, 'propFind']);
		$this->server->on('beforeMove', [$this, 'beforeMove']);
		$this->server->on('beforeMethod:*', [$this, 'beforeMethod'], 1);
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

	public function beforeMove($source, $target) {
		// recognize/{userId}/faces/{name}
		if (str_starts_with($source, 'recognize') && str_starts_with($target, 'recognize')) {
			$sourceParts = explode('/', $source);
			$targetParts = explode('/', $target);
			if ($sourceParts[2] === 'faces' && $targetParts[2] === 'faces' && count($sourceParts) === 4 && count($targetParts) === 4) {
				try {
					$this->faceClusterMapper->findByUserAndTitle($targetParts[1], $targetParts[3]);
					throw new Forbidden('The target node already exists and cannot be overwritten');
				} catch (DoesNotExistException $e) {
					return true;
				} catch (MultipleObjectsReturnedException|Exception $e) {
					throw $e;
				}
			}
		}
		return true;
	}

	public function beforeMethod(RequestInterface $request, ResponseInterface $response) {
		if (!str_starts_with($request->getPath(), 'recognize')) {
			return;
		}
		$key = $request->getHeader('X-Recognize-Api-Key');
		if ($key === null) {
			throw new Forbidden('You must provide a valid X-Recognize-Api-Key');
		}
		try {
			$json = $this->crypto->decrypt($key);
		} catch (\Exception $e) {
			$this->logger->warning('Failed to decrypt recognize API key. Denying entry.', ['exception' => $e]);
			throw new Forbidden('You must provide a valid X-Recognize-Api-Key');
		}
		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			$this->logger->warning('Failed to decode recognize API key. Denying entry.', ['exception' => $e]);
			throw new Forbidden('You must provide a valid X-Recognize-Api-Key');
		}

		if (!isset($data['type']) || $data['type'] !== 'recognize-api-key' || !isset($data['version']) || $data['version'] !== 1 || !isset($data['timestamp'])) {
			$this->logger->warning('Failed to validate recognize API key.', ['data' => $data]);
			throw new Forbidden('You must provide a valid X-Recognize-Api-Key');
		}

		if ($this->timeFactory->now()->getTimestamp() - (int)$data['timestamp'] < self::API_KEY_TIMEOUT) {
			return;
		}

		$this->logger->info('API key is too old, denying entry', ['data' => $data]);
		throw new Forbidden('You must provide a valid X-Recognize-Api-Key');
	}
}
