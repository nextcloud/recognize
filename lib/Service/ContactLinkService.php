<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Service;

use OCA\Recognize\Contacts\ContactCard;
use OCA\Recognize\Db\ContactFace;
use OCA\Recognize\Db\ContactFaceMapper;
use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Exception\Exception;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IPreview;
use Psr\Log\LoggerInterface;

/**
 * Links face clusters to contacts.
 *
 * A link is either made by the user, or by matching the photo of a contact
 * against the clusters of the user. Links made by the user, including the
 * decision to have no link, are never changed automatically.
 */
final class ContactLinkService {
	/** Detections sampled per cluster to compute its centre. */
	public const CLUSTER_SAMPLE_SIZE = 50;
	/** Contact photos analyzed per run; the rest is picked up by the next run. */
	public const MAX_PHOTOS_PER_RUN = 200;
	/** Extra room around a face, as a share of the face size, when cropping it out of a photo. */
	public const CROP_MARGIN = 0.5;
	/** Size of the square photo written to a contact. */
	public const CONTACT_PHOTO_SIZE = 512;
	/** Previews are requested in this size when cropping a face, larger for large crops. */
	private const PREVIEW_SIZE_SMALL = 1024;
	private const PREVIEW_SIZE_LARGE = 2048;

	public function __construct(
		private FaceClusterMapper $clusterMapper,
		private FaceDetectionMapper $detectionMapper,
		private ContactFaceMapper $contactFaceMapper,
		private ContactsService $contactsService,
		private ContactPhotoAnalyzer $analyzer,
		private SettingsService $settingsService,
		private IRootFolder $rootFolder,
		private IPreview $previewManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Whether the scheduled matching of contact photos is turned on and can run.
	 */
	public function isAutoLinkingAvailable(): bool {
		return $this->settingsService->getSetting('faces.contactLinking') === 'true' && $this->analyzer->isAvailable();
	}

	/**
	 * Link the unlinked contacts with a photo of a user to their face clusters.
	 *
	 * A contact is linked to a cluster when its photo matches that cluster and no
	 * other one, and no other contact matches that cluster. Everything else is left
	 * for the user to decide.
	 *
	 * @return array{linked: int, analyzed: int, ambiguous: int}
	 * @throws \OCP\DB\Exception
	 * @throws Exception When the contact photos cannot be analyzed
	 */
	public function linkContactsForUser(string $userId): array {
		$report = ['linked' => 0, 'analyzed' => 0, 'ambiguous' => 0];

		if (!$this->analyzer->isAvailable()) {
			$this->logger->debug('Not linking contacts of user ' . $userId . ': ' . $this->analyzer->getUnavailableReason());
			return $report;
		}

		$candidates = [];
		foreach ($this->clusterMapper->findAutoLinkCandidatesByUserId($userId) as $cluster) {
			$candidates[$cluster->getId()] = $cluster;
		}
		if (count($candidates) === 0) {
			$this->logger->debug('No face clusters of user ' . $userId . ' left to link to a contact');
			return $report;
		}

		$dimensions = $this->analyzer->getDimensions();
		$centroids = [];
		foreach ($candidates as $clusterId => $cluster) {
			$sample = $this->detectionMapper->findClusterSample($clusterId, self::CLUSTER_SAMPLE_SIZE);
			if (count($sample) === 0 || count(reset($sample)->getVector()) !== $dimensions) {
				continue;
			}
			$centroids[$clusterId] = FaceClusterAnalyzer::calculateCentroidOfDetections($sample);
		}
		if (count($centroids) === 0) {
			return $report;
		}

		$linkedContacts = [];
		foreach ($this->clusterMapper->findLinkedByUserId($userId) as $cluster) {
			$linkedContacts[self::contactKey((string)$cluster->getContactAddressBook(), (string)$cluster->getContactUid())] = true;
		}

		/** @var array<string, ContactCard> $contacts */
		$contacts = [];
		/** @var array<string, list<float>> $vectors */
		$vectors = [];
		/** @var array<string, array{contact: ContactCard, photo: array{mimeType: string, data: string}, hash: string, cached: ContactFace|null}> $toAnalyze */
		$toAnalyze = [];
		foreach ($this->contactsService->getAddressBooks($userId) as $book) {
			foreach ($this->contactsService->getContacts($userId, $book['uri']) as $contact) {
				$key = self::contactKey($contact->getAddressBookUri(), $contact->getUid());
				if (isset($linkedContacts[$key]) || isset($contacts[$key])) {
					continue;
				}
				$photo = $contact->getPhoto();
				if ($photo === null) {
					continue;
				}
				$contacts[$key] = $contact;
				$hash = sha1($photo['data']);
				$cached = $this->findCachedFace($userId, $contact);
				if ($cached !== null && $cached->getPhotoHash() === $hash) {
					$vector = $cached->getVector();
					if ($vector === null) {
						continue;
					}
					if (count($vector) === $dimensions) {
						$vectors[$key] = $vector;
						continue;
					}
				}
				if (count($toAnalyze) < self::MAX_PHOTOS_PER_RUN) {
					$toAnalyze[$key] = ['contact' => $contact, 'photo' => $photo, 'hash' => $hash, 'cached' => $cached];
				}
			}
		}

		if (count($toAnalyze) > 0) {
			$analyzed = $this->analyzer->analyze(array_map(static fn (array $entry): string => $entry['photo']['data'], $toAnalyze));
			$report['analyzed'] = count($analyzed);
			foreach ($analyzed as $key => $result) {
				$entry = $toAnalyze[$key];
				$this->storeCachedFace($userId, $entry['contact'], $entry['hash'], $result['vector'], $result['faceCount'], $entry['cached']);
				if ($result['vector'] !== null && count($result['vector']) === $dimensions) {
					$vectors[$key] = $result['vector'];
				}
			}
		}

		$threshold = $this->analyzer->getMatchThreshold();
		/** @var array<string, list<int>> $clustersByContact */
		$clustersByContact = [];
		/** @var array<int, list<string>> $contactsByCluster */
		$contactsByCluster = [];
		foreach ($vectors as $key => $vector) {
			foreach ($centroids as $clusterId => $centroid) {
				if (self::distance($vector, $centroid) <= $threshold) {
					$clustersByContact[$key][] = $clusterId;
					$contactsByCluster[$clusterId][] = $key;
				}
			}
		}

		foreach ($clustersByContact as $key => $clusterIds) {
			if (count($clusterIds) !== 1 || count($contactsByCluster[$clusterIds[0]]) !== 1) {
				$report['ambiguous']++;
				$this->logger->debug('Contact ' . $contacts[$key]->getDisplayName() . ' of user ' . $userId . ' matches several face clusters or shares a cluster with another contact, leaving the decision to the user');
				continue;
			}
			$this->linkCluster($candidates[$clusterIds[0]], $contacts[$key], FaceCluster::LINK_SOURCE_AUTO);
			$report['linked']++;
		}

		$this->logger->info('Linked ' . $report['linked'] . ' face clusters of user ' . $userId . ' to contacts (' . $report['analyzed'] . ' contact photos analyzed, ' . $report['ambiguous'] . ' ambiguous)');
		return $report;
	}

	/**
	 * Link a cluster to a contact.
	 *
	 * A cluster without a name takes the name of the contact when linked
	 * automatically, unless the user already has a person of that name.
	 *
	 * @param string $source One of the FaceCluster::LINK_SOURCE_* constants
	 * @throws \OCP\DB\Exception
	 */
	public function linkCluster(FaceCluster $cluster, ContactCard $contact, string $source): FaceCluster {
		$cluster->setContactUid($contact->getUid());
		$cluster->setContactAddressBook($contact->getAddressBookUri());
		$cluster->setContactLinkSource($source);
		if ($source === FaceCluster::LINK_SOURCE_AUTO && $cluster->getTitle() === '') {
			$title = $contact->getDisplayName();
			if ($title !== '' && preg_match('/^[0-9]+$/', $title) !== 1 && !$this->titleExists($cluster->getUserId(), $title)) {
				$cluster->setTitle($title);
			}
		}
		/** @var FaceCluster $cluster */
		$cluster = $this->clusterMapper->update($cluster);
		return $cluster;
	}

	/**
	 * Remove the link of a cluster. The cluster is not linked again automatically.
	 *
	 * @throws \OCP\DB\Exception
	 */
	public function unlinkCluster(FaceCluster $cluster): FaceCluster {
		$cluster->setContactUid(null);
		$cluster->setContactAddressBook(null);
		$cluster->setContactLinkSource(FaceCluster::LINK_SOURCE_MANUAL);
		/** @var FaceCluster $cluster */
		$cluster = $this->clusterMapper->update($cluster);
		return $cluster;
	}

	/**
	 * @return ContactCard|null The linked contact, or null when the cluster is not linked or the contact is gone
	 */
	public function getLinkedContact(FaceCluster $cluster): ?ContactCard {
		if (!$cluster->isLinked()) {
			return null;
		}
		return $this->contactsService->findContact($cluster->getUserId(), (string)$cluster->getContactAddressBook(), (string)$cluster->getContactUid());
	}

	/**
	 * Cut the face of a detection out of its photo, as a square with some room around the face.
	 *
	 * @param int $size Width and height of the returned image
	 * @return array{mimeType: string, data: string}
	 * @throws NotFoundException When the photo is not available to the user
	 * @throws Exception When the photo cannot be processed
	 */
	public function cropFace(string $userId, FaceDetection $detection, int $size): array {
		$file = $this->rootFolder->getUserFolder($userId)->getFirstNodeById($detection->getFileId());
		if (!$file instanceof File) {
			throw new NotFoundException('Photo ' . $detection->getFileId() . ' not found for user');
		}
		$previewSize = $size > 256 ? self::PREVIEW_SIZE_LARGE : self::PREVIEW_SIZE_SMALL;
		try {
			$preview = $this->previewManager->getPreview($file, $previewSize, $previewSize, false, IPreview::MODE_FILL);
			$image = @imagecreatefromstring($preview->getContent());
		} catch (\Throwable $e) {
			throw new Exception('Could not generate a preview of photo ' . $file->getId(), 0, $e);
		}
		if ($image === false) {
			throw new Exception('Could not decode the preview of photo ' . $file->getId());
		}

		$width = imagesx($image);
		$height = imagesy($image);
		$faceWidth = $detection->getWidth() * $width;
		$faceHeight = $detection->getHeight() * $height;
		$centerX = ($detection->getX() + $detection->getWidth() / 2) * $width;
		$centerY = ($detection->getY() + $detection->getHeight() / 2) * $height;

		$side = (int)round(max($faceWidth, $faceHeight) * (1 + 2 * self::CROP_MARGIN));
		$side = max(1, min($side, $width, $height));
		$left = (int)round(min(max($centerX - $side / 2, 0), $width - $side));
		$top = (int)round(min(max($centerY - $side / 2, 0), $height - $side));

		$cropped = imagecrop($image, ['x' => $left, 'y' => $top, 'width' => $side, 'height' => $side]);
		imagedestroy($image);
		if ($cropped === false) {
			throw new Exception('Could not crop the face out of photo ' . $file->getId());
		}
		if ($side !== $size) {
			$scaled = imagescale($cropped, $size, $size);
			imagedestroy($cropped);
			if ($scaled === false) {
				throw new Exception('Could not scale the face of photo ' . $file->getId());
			}
			$cropped = $scaled;
		}

		ob_start();
		imageinterlace($cropped, true);
		$written = imagejpeg($cropped, null, 90);
		$data = (string)ob_get_clean();
		imagedestroy($cropped);
		if (!$written) {
			throw new Exception('Could not encode the face of photo ' . $file->getId());
		}
		return ['mimeType' => 'image/jpeg', 'data' => $data];
	}

	/**
	 * Use the face of a detection as the photo of a contact.
	 *
	 * @throws NotFoundException When the photo is not available to the user
	 * @throws Exception When the photo cannot be processed or the contact cannot be written
	 */
	public function setContactPhotoFromDetection(string $userId, FaceDetection $detection, ContactCard $contact): void {
		$face = $this->cropFace($userId, $detection, self::CONTACT_PHOTO_SIZE);
		$this->contactsService->setPhoto($contact, $face['mimeType'], $face['data']);
	}

	private function findCachedFace(string $userId, ContactCard $contact): ?ContactFace {
		try {
			return $this->contactFaceMapper->findByContact($userId, $contact->getAddressBookUri(), $contact->getUid());
		} catch (DoesNotExistException|MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			return null;
		}
	}

	/**
	 * @param list<float>|null $vector
	 */
	private function storeCachedFace(string $userId, ContactCard $contact, string $hash, ?array $vector, int $faceCount, ?ContactFace $existing): void {
		$face = $existing ?? new ContactFace();
		$face->setUserId($userId);
		$face->setAddressBook($contact->getAddressBookUri());
		$face->setContactUid($contact->getUid());
		$face->setPhotoHash($hash);
		$face->setVector($vector);
		$face->setFaceCount($faceCount);
		try {
			if ($existing === null) {
				$this->contactFaceMapper->insert($face);
			} else {
				$this->contactFaceMapper->update($face);
			}
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Could not cache the face of contact ' . $contact->getUid(), ['exception' => $e]);
		}
	}

	private function titleExists(string $userId, string $title): bool {
		try {
			$this->clusterMapper->findByUserAndTitle($userId, $title);
			return true;
		} catch (DoesNotExistException $e) {
			return false;
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			return true;
		}
	}

	private static function contactKey(string $addressBook, string $uid): string {
		return $addressBook . "\0" . $uid;
	}

	/**
	 * @param list<float> $a
	 * @param list<float> $b
	 */
	private static function distance(array $a, array $b): float {
		$sum = 0.0;
		foreach ($a as $i => $value) {
			$diff = $value - ($b[$i] ?? 0.0);
			$sum += $diff * $diff;
		}
		return sqrt($sum);
	}
}
