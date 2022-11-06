<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OC\Files\Cache\CacheQueryBuilder;
use OC\SystemConfig;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Constants;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IMimeTypeLoader;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class StorageCrawlJob extends QueuedJob {
	private LoggerInterface $logger;
	private IMimeTypeLoader $mimeTypes;
	private QueueService $queue;
	private IJobList $jobList;
	private IDBConnection $db;
	private SystemConfig $systemConfig;
	private TagManager $tagManager;

	public function __construct(ITimeFactory $timeFactory, Logger $logger, IMimeTypeLoader $mimeTypes, QueueService $queue, IJobList $jobList, IDBConnection $db, SystemConfig $systemConfig, TagManager $tagManager) {
		parent::__construct($timeFactory);
		$this->logger = $logger;
		$this->mimeTypes = $mimeTypes;
		$this->queue = $queue;
		$this->jobList = $jobList;
		$this->db = $db;
		$this->systemConfig = $systemConfig;
		$this->tagManager = $tagManager;
	}

	private function getDir(int $fileid, array $directoryTypes, bool $recursive = false): array {
		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
		$dir = $qb->selectFileCache()
			->andWhere($qb->expr()->in('mimetype', $qb->createNamedParameter($directoryTypes, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('parent', $qb->createNamedParameter($fileid)))
			->executeQuery()->fetchAll();

		if ($recursive) {
			foreach ($dir as $item) {
				$dir = array_merge($dir, $this->getDir($item['fileid'], $directoryTypes, $recursive));
			}
		}
		return $dir;
	}

	private function getIgnoreFileids(): array {
		$directoryTypes = array_map(fn ($mimeType) => $this->mimeTypes->getId($mimeType), Constants::DIRECTORY_FORMATS);
		$octetStreamTypes = array_map(fn ($mimeType) => $this->mimeTypes->getId($mimeType), Constants::OCTET_STREAM_FORMATS);
		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
		$ignoreFiles = $qb->selectFileCache()
			->andWhere($qb->expr()->in('mimetype', $qb->createNamedParameter($octetStreamTypes, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->in('name', $qb->createNamedParameter(Constants::IGNORE_MARKERS, IQueryBuilder::PARAM_STR_ARRAY)))
			->executeQuery()->fetchAll();
		$ignoreFileids = array_map(fn ($dir) => $dir['parent'], $ignoreFiles);
		foreach ($ignoreFiles as $ignoreFile) {
			$ignoreDir = $this->getDir($ignoreFile['parent'],  $directoryTypes, true);
			$fileids = array_map(fn ($dir) => $dir['fileid'], $ignoreDir);
			$ignoreFileids = array_merge($ignoreFileids, $fileids);
		}
		return $ignoreFileids;
	}

	protected function run($argument): void {
		$storageId = $argument['storage_id'];
		$rootId = $argument['root_id'];
		$overrideRoot = $argument['override_root'];
		$lastFileId = $argument['last_file_id'];
		/**
		 * @var list<string> $models
		 */
		$models = $argument['models'] ?? [
			ClusteringFaceClassifier::MODEL_NAME,
			ImagenetClassifier::MODEL_NAME,
			LandmarksClassifier::MODEL_NAME,
			MovinetClassifier::MODEL_NAME,
			MusicnnClassifier::MODEL_NAME,
		];

		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
		try {
			$root = $qb->selectFileCache()
				->andWhere($qb->expr()->eq('filecache.fileid', $qb->createNamedParameter($overrideRoot, IQueryBuilder::PARAM_INT)))
				->executeQuery()->fetch();
		} catch (Exception $e) {
			$this->logger->error('Could not fetch storage root', ['exception' => $e]);
			return;
		}

		if ($root === false) {
			$this->logger->error('Could not find storage root');
			return;
		}
		

		try {
			$ignoreFileids = $this->getIgnoreFileids(Constants::IGNORE_MARKERS_ALL);
			$ignoreImageFileids = $this->getIgnoreFileids(Constants::IGNORE_MARKERS_IMAGE);
			$ignoreVideoFileids = $this->getIgnoreFileids(Constants::IGNORE_MARKERS_VIDEO);
			$ignoreAudioFileids = $this->getIgnoreFileids(Constants::IGNORE_MARKERS_AUDIO);
		} catch (Exception $e) {
			$this->logger->error('Could not fetch ignore files', ['exception' => $e]);
			return;
		}


		$imageTypes = array_map(fn ($mimeType) => $this->mimeTypes->getId($mimeType), Constants::IMAGE_FORMATS);
		$videoTypes = array_map(fn ($mimeType) => $this->mimeTypes->getId($mimeType), Constants::VIDEO_FORMATS);
		$audioTypes = array_map(fn ($mimeType) => $this->mimeTypes->getId($mimeType), Constants::AUDIO_FORMATS);

		$mimeTypes = [];
		if (in_array(ClusteringFaceClassifier::MODEL_NAME, $models) ||
			in_array(ImagenetClassifier::MODEL_NAME, $models) ||
				in_array(LandmarksClassifier::MODEL_NAME, $models)) {
			$mimeTypes = array_merge($imageTypes, $mimeTypes);
		}
		if (in_array(MovinetClassifier::MODEL_NAME, $models)) {
			$mimeTypes = array_merge($videoTypes, $mimeTypes);
		}
		if (in_array(MusicnnClassifier::MODEL_NAME, $models)) {
			$mimeTypes = array_merge($audioTypes, $mimeTypes);
		}
		if (count($mimeTypes) === 0) {
			// Remove current iteration
			$this->jobList->remove(self::class, $argument);
			return;
		}

		try {
			$path = $root['path'] === '' ? '' :  $root['path'] . '/';
			$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
			$ignoreFileidsChunks = array_chunk($ignoreFileids, 999, true);
			$ignoreFileidsExpr = array_map(fn ($chunk) => $qb->expr()->notIn('parent', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)), $ignoreFileidsChunks);
			$qb->selectFileCache()
				->whereStorageId($storageId)
				->andWhere($qb->expr()->like('path', $qb->createNamedParameter($path . '%')))
				->andWhere($qb->expr()->eq('storage', $qb->createNamedParameter($storageId)))
				->andWhere($qb->expr()->in('mimetype', $qb->createNamedParameter($mimeTypes, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->gt('filecache.fileid', $qb->createNamedParameter($lastFileId)));
			if (count($ignoreFileidsExpr) > 0) {
				$qb->andWhere($qb->expr()->andX(...$ignoreFileidsExpr));
			}
			$files = $qb->orderBy('filecache.fileid', 'ASC')
				->setMaxResults(100)
				->executeQuery();
		} catch (Exception $e) {
			$this->logger->error('Could not fetch files', ['exception' => $e]);
			return;
		}

		// Remove current iteration
		$this->jobList->remove(self::class, $argument);

		$i = 0;
		/** @var array $file */
		while ($file = $files->fetch()) {
			$i++;
			$queueFile = new QueueFile();
			$queueFile->setStorageId($storageId);
			$queueFile->setRootId($rootId);
			$queueFile->setFileId($file['fileid']);
			$queueFile->setUpdate(false);
			try {
				if (in_array($file['mimetype'], $imageTypes) and !in_array($file['parent'], $ignoreImageFileids)) {
					if (in_array(ImagenetClassifier::MODEL_NAME, $models)) {
						$this->queue->insertIntoQueue(ImagenetClassifier::MODEL_NAME, $queueFile);
					}
					if (!in_array(ImagenetClassifier::MODEL_NAME, $models) && in_array(LandmarksClassifier::MODEL_NAME, $models)) {
						$tags = $this->tagManager->getTagsForFiles([$queueFile->getFileId()]);
						/** @var \OCP\SystemTag\ISystemTag[] $fileTags */
						$fileTags = $tags[$queueFile->getFileId()];
						$landmarkTags = array_filter($fileTags, function ($tag) {
							return in_array($tag->getName(), LandmarksClassifier::PRECONDITION_TAGS);
						});
						if (count($landmarkTags) > 0) {
							$this->queue->insertIntoQueue(LandmarksClassifier::MODEL_NAME, $queueFile);
						}
					}
					if (in_array(ClusteringFaceClassifier::MODEL_NAME, $models)) {
						$this->queue->insertIntoQueue(ClusteringFaceClassifier::MODEL_NAME, $queueFile);
					}
				}
				if (in_array($file['mimetype'], $videoTypes) and !in_array($file['parent'], $ignoreVideoFileids)) {
					$this->queue->insertIntoQueue(MovinetClassifier::MODEL_NAME, $queueFile);
				}
				if (in_array($file['mimetype'], $audioTypes) and !in_array($file['parent'], $ignoreAudioFileids)) {
					$this->queue->insertIntoQueue(MusicnnClassifier::MODEL_NAME, $queueFile);
				}
			} catch (Exception $e) {
				$this->logger->error('Failed to add file to queue', ['exception' => $e]);
				return;
			}
		}

		if ($i > 0) {
			// Schedule next iteration
			$this->jobList->add(self::class, [
				'storage_id' => $storageId,
				'root_id' => $rootId,
				'override_root' => $overrideRoot,
				'last_file_id' => $queueFile->getFileId(),
				'models' => $models,
			]);
		}
	}
}
