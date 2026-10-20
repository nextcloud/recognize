<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Controller;

use OCA\Recognize\Contacts\ContactCard;
use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Exception\Exception;
use OCA\Recognize\Service\ContactLinkService;
use OCA\Recognize\Service\ContactsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Links between the face clusters of the current user and their contacts.
 */
final class ContactLinkController extends OCSController {
	public const MAX_SEARCH_RESULTS = 200;
	public const MAX_DETECTIONS = 500;
	public const MIN_PREVIEW_SIZE = 32;
	public const MAX_PREVIEW_SIZE = 1024;

	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private FaceClusterMapper $clusterMapper,
		private FaceDetectionMapper $detectionMapper,
		private ContactsService $contactsService,
		private ContactLinkService $contactLinkService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Search the contacts of the current user.
	 *
	 * @param string $search Part of the name or email address, empty for all contacts
	 * @param int $limit Maximum number of contacts
	 * @return DataResponse<Http::STATUS_OK, list<array{uid: string, addressBook: string, addressBookName: string, displayName: string, hasPhoto: bool, readOnly: bool, url: string|null, photoUrl: string|null}>, array{}>
	 */
	#[NoAdminRequired]
	public function searchContacts(string $search = '', int $limit = 50): DataResponse {
		$userId = $this->requireUser();
		$limit = max(1, min($limit, self::MAX_SEARCH_RESULTS));
		$contacts = $this->contactsService->searchContacts($userId, trim($search), $limit);
		return new DataResponse(array_map(fn (ContactCard $contact): array => $this->contactsService->serializeContact($userId, $contact), $contacts));
	}

	/**
	 * Get a contact of the current user along with the face clusters linked to it.
	 *
	 * @param string $addressBook The URI of the address book
	 * @param string $uid The UID of the contact
	 * @return DataResponse<Http::STATUS_OK, array{contact: array, faces: list<array>}, array{}>
	 * @throws OCSNotFoundException The contact does not exist
	 */
	#[NoAdminRequired]
	public function getContact(string $addressBook, string $uid): DataResponse {
		$userId = $this->requireUser();
		$contact = $this->requireContact($userId, $addressBook, $uid);
		$clusters = $this->clusterMapper->findByContact($userId, $addressBook, $uid);
		return new DataResponse([
			'contact' => $this->contactsService->serializeContact($userId, $contact),
			'faces' => array_map(fn (FaceCluster $cluster): array => $this->serializeCluster($cluster), $clusters),
		]);
	}

	/**
	 * List the face clusters of the current user with the contact they are linked to.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array>, array{}>
	 */
	#[NoAdminRequired]
	public function listFaces(): DataResponse {
		$userId = $this->requireUser();
		$clusters = $this->clusterMapper->findByUserId($userId);
		return new DataResponse(array_map(fn (FaceCluster $cluster): array => $this->serializeCluster($cluster), $clusters));
	}

	/**
	 * List the faces that make up a cluster, the largest ones first.
	 *
	 * @param int $clusterId The id of the cluster
	 * @param int $limit Maximum number of faces
	 * @return DataResponse<Http::STATUS_OK, list<array{id: int, fileId: int, x: float, y: float, width: float, height: float}>, array{}>
	 * @throws OCSNotFoundException The cluster does not exist
	 */
	#[NoAdminRequired]
	public function listDetections(int $clusterId, int $limit = 100): DataResponse {
		$userId = $this->requireUser();
		$cluster = $this->requireCluster($userId, $clusterId);
		$limit = max(1, min($limit, self::MAX_DETECTIONS));
		$detections = $this->detectionMapper->findByClusterId($cluster->getId());
		usort($detections, static fn (FaceDetection $a, FaceDetection $b): int => ($b->getWidth() * $b->getHeight()) <=> ($a->getWidth() * $a->getHeight()));
		$detections = array_slice($detections, 0, $limit);
		return new DataResponse(array_map(static fn (FaceDetection $detection): array => self::serializeDetection($detection), $detections));
	}

	/**
	 * Link a face cluster to a contact.
	 *
	 * @param int $clusterId The id of the cluster
	 * @param string $addressBook The URI of the address book of the contact
	 * @param string $uid The UID of the contact
	 * @return DataResponse<Http::STATUS_OK, array, array{}>
	 * @throws OCSNotFoundException The cluster or the contact does not exist
	 */
	#[NoAdminRequired]
	public function linkFace(int $clusterId, string $addressBook, string $uid): DataResponse {
		$userId = $this->requireUser();
		$cluster = $this->requireCluster($userId, $clusterId);
		$contact = $this->requireContact($userId, $addressBook, $uid);
		try {
			$cluster = $this->contactLinkService->linkCluster($cluster, $contact, FaceCluster::LINK_SOURCE_MANUAL);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Could not link face cluster ' . $clusterId . ' to a contact', ['exception' => $e]);
			throw new OCSException('Could not store the link', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse($this->serializeCluster($cluster));
	}

	/**
	 * Remove the link between a face cluster and its contact. The cluster will not be linked automatically again.
	 *
	 * @param int $clusterId The id of the cluster
	 * @return DataResponse<Http::STATUS_OK, array, array{}>
	 * @throws OCSNotFoundException The cluster does not exist
	 */
	#[NoAdminRequired]
	public function unlinkFace(int $clusterId): DataResponse {
		$userId = $this->requireUser();
		$cluster = $this->requireCluster($userId, $clusterId);
		try {
			$cluster = $this->contactLinkService->unlinkCluster($cluster);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Could not unlink face cluster ' . $clusterId, ['exception' => $e]);
			throw new OCSException('Could not remove the link', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse($this->serializeCluster($cluster));
	}

	/**
	 * Get the face of a detection cut out of its photo.
	 *
	 * @param int $detectionId The id of the face detection
	 * @param int $size Width and height of the image in pixels
	 * @return DataDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>
	 * @throws OCSNotFoundException The detection or its photo does not exist
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getDetectionPreview(int $detectionId, int $size = 256): DataDisplayResponse {
		$userId = $this->requireUser();
		$detection = $this->requireDetection($userId, $detectionId);
		$size = max(self::MIN_PREVIEW_SIZE, min($size, self::MAX_PREVIEW_SIZE));
		try {
			$face = $this->contactLinkService->cropFace($userId, $detection, $size);
		} catch (NotFoundException $e) {
			throw new OCSNotFoundException('Photo not found');
		} catch (Exception $e) {
			$this->logger->warning('Could not crop face ' . $detectionId, ['exception' => $e]);
			throw new OCSException('Could not crop the face', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$response = new DataDisplayResponse($face['data'], Http::STATUS_OK, ['Content-Type' => $face['mimeType']]);
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}

	/**
	 * Use the face of a detection as the photo of a contact.
	 *
	 * Without a contact, the photo goes to the contact the cluster of the detection is linked to.
	 *
	 * @param int $detectionId The id of the face detection
	 * @param string|null $addressBook The URI of the address book of the contact
	 * @param string|null $uid The UID of the contact
	 * @return DataResponse<Http::STATUS_OK, array, array{}>
	 * @throws OCSNotFoundException The detection, its photo or the contact does not exist
	 * @throws OCSBadRequestException No contact was given and the cluster is not linked to one
	 * @throws OCSForbiddenException The address book of the contact is read-only
	 */
	#[NoAdminRequired]
	public function setContactPhoto(int $detectionId, ?string $addressBook = null, ?string $uid = null): DataResponse {
		$userId = $this->requireUser();
		$detection = $this->requireDetection($userId, $detectionId);

		if ($addressBook !== null && $uid !== null) {
			$contact = $this->requireContact($userId, $addressBook, $uid);
		} else {
			$clusterId = $detection->getClusterId();
			$cluster = $clusterId !== null && $clusterId > 0 ? $this->requireCluster($userId, $clusterId) : null;
			$contact = $cluster !== null ? $this->contactLinkService->getLinkedContact($cluster) : null;
			if ($contact === null) {
				throw new OCSBadRequestException('This person is not linked to a contact');
			}
		}
		if ($contact->isAddressBookReadOnly()) {
			throw new OCSForbiddenException('The address book of this contact is read-only');
		}

		try {
			$this->contactLinkService->setContactPhotoFromDetection($userId, $detection, $contact);
		} catch (NotFoundException $e) {
			throw new OCSNotFoundException('Photo not found');
		} catch (Exception $e) {
			$this->logger->warning('Could not set the photo of a contact from face ' . $detectionId, ['exception' => $e]);
			throw new OCSException('Could not set the contact photo', Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$updated = $this->contactsService->findContact($userId, $contact->getAddressBookUri(), $contact->getUid()) ?? $contact;
		return new DataResponse($this->contactsService->serializeContact($userId, $updated));
	}

	/**
	 * @throws OCSForbiddenException
	 */
	private function requireUser(): string {
		if ($this->userId === null) {
			throw new OCSForbiddenException('Not logged in');
		}
		return $this->userId;
	}

	/**
	 * @throws OCSNotFoundException
	 */
	private function requireCluster(string $userId, int $clusterId): FaceCluster {
		try {
			$cluster = $this->clusterMapper->find($clusterId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			throw new OCSNotFoundException('Person not found');
		}
		if ($cluster->getUserId() !== $userId) {
			throw new OCSNotFoundException('Person not found');
		}
		return $cluster;
	}

	/**
	 * @throws OCSNotFoundException
	 */
	private function requireDetection(string $userId, int $detectionId): FaceDetection {
		try {
			$detection = $this->detectionMapper->find($detectionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			throw new OCSNotFoundException('Face not found');
		}
		if ($detection->getUserId() !== $userId) {
			throw new OCSNotFoundException('Face not found');
		}
		return $detection;
	}

	/**
	 * @throws OCSNotFoundException
	 */
	private function requireContact(string $userId, string $addressBook, string $uid): ContactCard {
		$contact = $this->contactsService->findContact($userId, $addressBook, $uid);
		if ($contact === null) {
			throw new OCSNotFoundException('Contact not found');
		}
		return $contact;
	}

	/**
	 * @return array{id: int, title: string, name: string, linkSource: string|null, contact: array|null, preview: array{fileId: int, detection: array}|null}
	 */
	private function serializeCluster(FaceCluster $cluster): array {
		$contact = $this->contactLinkService->getLinkedContact($cluster);
		try {
			$previewDetection = $this->detectionMapper->findDetectionForPreviewImageByClusterId($cluster->getId());
			$preview = ['fileId' => $previewDetection->getFileId(), 'detection' => self::serializeDetection($previewDetection)];
		} catch (DoesNotExistException|MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$preview = null;
		}
		return [
			'id' => $cluster->getId(),
			'title' => $cluster->getTitle(),
			// The name of the person on the recognize WebDAV endpoint, which the Photos app uses to address it
			'name' => $cluster->getTitle() !== '' ? $cluster->getTitle() : (string)$cluster->getId(),
			'linkSource' => $cluster->getContactLinkSource(),
			'contact' => $contact !== null ? $this->contactsService->serializeContact($cluster->getUserId(), $contact) : null,
			'preview' => $preview,
		];
	}

	/**
	 * @return array{id: int, fileId: int, x: float, y: float, width: float, height: float}
	 */
	private static function serializeDetection(FaceDetection $detection): array {
		return [
			'id' => $detection->getId(),
			'fileId' => $detection->getFileId(),
			'x' => $detection->getX(),
			'y' => $detection->getY(),
			'width' => $detection->getWidth(),
			'height' => $detection->getHeight(),
		];
	}
}
