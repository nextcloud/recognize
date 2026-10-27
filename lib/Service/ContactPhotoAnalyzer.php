<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Service;

use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Exception\Exception;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;

/**
 * Computes the face embedding of contact photos with the same model that
 * produced the face detections of the user's files, so the two can be compared.
 *
 * Only the built-in classifier can be fed an image that is not stored in
 * Nextcloud. In TaskProcessing mode the embeddings come from an ExApp that
 * only accepts files of the user, so contact photos cannot be analyzed there.
 */
final class ContactPhotoAnalyzer {
	/** Contact photos larger than this are scaled down before they are analyzed. */
	public const MAX_IMAGE_DIMENSION = 1024;
	/**
	 * When a contact photo shows several faces, the largest one is only used when
	 * its area is at least this many times the area of the second largest one.
	 */
	public const MIN_FACE_DOMINANCE = 2.0;

	public function __construct(
		private SettingsService $settingsService,
		private ClusteringFaceClassifier $faceClassifier,
		private ITempManager $tempManager,
		private LoggerInterface $logger,
	) {
	}

	public function isAvailable(): bool {
		return $this->settingsService->getSetting('faces.enabled') === 'true'
			&& $this->settingsService->getSetting('taskprocessing.enabled') !== 'true'
			&& trim($this->settingsService->getSetting('node_binary')) !== '';
	}

	/**
	 * Why {@see isAvailable()} is false, for logs and the admin settings.
	 */
	public function getUnavailableReason(): string {
		if ($this->settingsService->getSetting('faces.enabled') !== 'true') {
			return 'Face recognition is disabled';
		}
		if ($this->settingsService->getSetting('taskprocessing.enabled') === 'true') {
			return 'Contact photos cannot be analyzed by the TaskProcessing backend';
		}
		return 'No Node.js binary is configured';
	}

	/**
	 * The number of dimensions of the embeddings this analyzer produces.
	 */
	public function getDimensions(): int {
		return FaceClusterAnalyzer::DIMENSIONS;
	}

	/**
	 * The largest distance between a contact photo embedding and the centre of a
	 * cluster at which the two are considered to show the same person.
	 */
	public function getMatchThreshold(): float {
		return FaceClusterAnalyzer::MAX_CLUSTER_EDGE_LENGTH;
	}

	/**
	 * Analyze several photos in one classifier run.
	 *
	 * @param array<string, string> $imagesByKey The raw image bytes, keyed by an identifier of the caller's choice
	 * @return array<string, array{vector: list<float>|null, faceCount: int}> One result per key; the vector is
	 *                                                                        null when the photo holds no usable face
	 * @throws Exception When the classifier cannot be run
	 */
	public function analyze(array $imagesByKey): array {
		if (count($imagesByKey) === 0) {
			return [];
		}
		if (!$this->isAvailable()) {
			throw new Exception($this->getUnavailableReason());
		}

		$paths = [];
		$results = [];
		foreach ($imagesByKey as $key => $imageData) {
			$path = $this->writeTemporaryJpeg($imageData);
			if ($path === null) {
				$results[$key] = ['vector' => null, 'faceCount' => 0];
				continue;
			}
			$paths[$key] = $path;
		}

		if (count($paths) > 0) {
			$timeout = $this->settingsService->getSetting('tensorflow.purejs') === 'true'
				? ClusteringFaceClassifier::IMAGE_PUREJS_TIMEOUT
				: ClusteringFaceClassifier::IMAGE_TIMEOUT;
			try {
				$faces = $this->faceClassifier->classifyLocalFiles(ClusteringFaceClassifier::MODEL_NAME, array_values($paths), $timeout);
			} catch (\ErrorException|\RuntimeException $e) {
				throw new Exception('Could not analyze contact photos: ' . $e->getMessage(), 0, $e);
			} finally {
				foreach ($paths as $path) {
					@unlink($path);
				}
			}
			foreach (array_keys($paths) as $i => $key) {
				$results[$key] = $this->pickFace(is_array($faces[$i]) ? $faces[$i] : []);
			}
		}

		return $results;
	}

	/**
	 * Store the image as a JPEG the classifier can read, scaled down when it is too large.
	 *
	 * @return string|null The path, or null when the data is not a readable image
	 */
	private function writeTemporaryJpeg(string $imageData): ?string {
		$image = @imagecreatefromstring($imageData);
		if ($image === false) {
			$this->logger->debug('Contact photo could not be decoded as an image');
			return null;
		}
		$width = imagesx($image);
		$height = imagesy($image);
		if ($width > self::MAX_IMAGE_DIMENSION || $height > self::MAX_IMAGE_DIMENSION) {
			$scale = self::MAX_IMAGE_DIMENSION / max($width, $height);
			$scaled = imagescale($image, (int)round($width * $scale), (int)round($height * $scale));
			if ($scaled !== false) {
				imagedestroy($image);
				$image = $scaled;
			}
		}
		$path = $this->tempManager->getTemporaryFile('.jpg');
		if ($path === false) {
			imagedestroy($image);
			throw new Exception('Could not create a temporary file for a contact photo');
		}
		$written = imagejpeg($image, $path, 90);
		imagedestroy($image);
		if (!$written) {
			@unlink($path);
			return null;
		}
		return $path;
	}

	/**
	 * Pick the face that stands for the contact out of the faces found in the photo.
	 *
	 * @param array<array-key, mixed> $faces The faces the classifier found
	 * @return array{vector: list<float>|null, faceCount: int}
	 */
	private function pickFace(array $faces): array {
		$usable = [];
		foreach ($faces as $face) {
			if (!is_array($face) || !isset($face['vector']) || !is_array($face['vector'])) {
				continue;
			}
			if ((float)($face['score'] ?? 0) < ClusteringFaceClassifier::MIN_FACE_RECOGNITION_SCORE) {
				continue;
			}
			$roll = abs((float)($face['angle']['roll'] ?? 0));
			$yaw = abs((float)($face['angle']['yaw'] ?? 0));
			if ($roll > ClusteringFaceClassifier::MAX_FACE_ROLL || $yaw > ClusteringFaceClassifier::MAX_FACE_YAW) {
				continue;
			}
			$usable[] = $face;
		}

		if (count($usable) === 0) {
			return ['vector' => null, 'faceCount' => 0];
		}

		usort($usable, static function (array $a, array $b): int {
			return self::area($b) <=> self::area($a);
		});

		if (count($usable) > 1 && self::area($usable[0]) < self::MIN_FACE_DOMINANCE * self::area($usable[1])) {
			$this->logger->debug('Contact photo shows several faces of similar size, not using it');
			return ['vector' => null, 'faceCount' => count($usable)];
		}

		/** @var list<float> $vector */
		$vector = array_values(array_map('floatval', $usable[0]['vector']));
		return ['vector' => $vector, 'faceCount' => count($usable)];
	}

	private static function area(array $face): float {
		return (float)($face['width'] ?? 0) * (float)($face['height'] ?? 0);
	}
}
