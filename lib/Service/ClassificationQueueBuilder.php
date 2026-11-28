<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Service;

use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\QueueFile;

/**
 * Distributes the files of one page to the queues of the enabled classifiers.
 *
 * The face classifier does not tag the files it processes and tracks its own
 * progress in the face detection table, so it keeps its independent retry
 * behaviour: files that already carry the processed tag are still queued for
 * face classification and are only skipped for the tag-based classifiers.
 */
final class ClassificationQueueBuilder {
	/**
	 * @param list<array{fileid: int, image: bool, video: bool, audio: bool}> $files
	 * @param array<array-key, true> $processedFileIds lookup of file ids that already carry the processed tag
	 * @param list<string> $models enabled model names
	 * @return array<string, list<QueueFile>>
	 */
	public function build(array $files, array $processedFileIds, array $models, int $storageId, int $rootId): array {
		$queues = [
			ImagenetClassifier::MODEL_NAME => [],
			ClusteringFaceClassifier::MODEL_NAME => [],
			MovinetClassifier::MODEL_NAME => [],
			MusicnnClassifier::MODEL_NAME => [],
		];
		foreach ($files as $file) {
			$queueFile = new QueueFile();
			$queueFile->setStorageId($storageId);
			$queueFile->setRootId($rootId);
			$queueFile->setFileId($file['fileid']);
			$queueFile->setUpdate(false);

			if ($file['image'] && in_array(ClusteringFaceClassifier::MODEL_NAME, $models, true)) {
				$queues[ClusteringFaceClassifier::MODEL_NAME][] = $queueFile;
			}
			if (isset($processedFileIds[(string)$file['fileid']])) {
				continue;
			}
			if ($file['image'] && in_array(ImagenetClassifier::MODEL_NAME, $models, true)) {
				$queues[ImagenetClassifier::MODEL_NAME][] = $queueFile;
			}
			if ($file['video'] && in_array(MovinetClassifier::MODEL_NAME, $models, true)) {
				$queues[MovinetClassifier::MODEL_NAME][] = $queueFile;
			}
			if ($file['audio'] && in_array(MusicnnClassifier::MODEL_NAME, $models, true)) {
				$queues[MusicnnClassifier::MODEL_NAME][] = $queueFile;
			}
		}
		return $queues;
	}
}
