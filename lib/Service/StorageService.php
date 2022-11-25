<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OC\Files\Cache\CacheQueryBuilder;
use OC\SystemConfig;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Constants;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IMimeTypeLoader;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class StorageService {
	public const ALLOWED_MOUNT_TYPES = [
		'OC\Files\Mount\LocalHomeMountProvider',
		'OC\Files\Mount\ObjectHomeMountProvider',
		'OCA\Files_External\Config\ConfigAdapter',
		'OCA\GroupFolders\Mount\MountProvider'
	];

	public const HOME_MOUNT_TYPES = [
		'OC\Files\Mount\LocalHomeMountProvider',
		'OC\Files\Mount\ObjectHomeMountProvider',
	];

	private IDBConnection $db;
	private LoggerInterface $logger;
	private SystemConfig $systemConfig;
	private IgnoreService $ignoreService;
	private IMimeTypeLoader $mimeTypes;

	public function __construct(IDBConnection $db, Logger $logger, SystemConfig $systemConfig, IgnoreService $ignoreService, IMimeTypeLoader $mimeTypes) {
		$this->db = $db;
		$this->logger = $logger;
		$this->systemConfig = $systemConfig;
		$this->ignoreService = $ignoreService;
		$this->mimeTypes = $mimeTypes;
	}

	/**
	 * @return \Generator
	 * @throws \OCP\DB\Exception
	 */
	public function getMounts(): \Generator {
		$qb = $this->db->getQueryBuilder();
		$qb->select('root_id', 'storage_id', 'mount_provider_class')
			->from('mounts')
			->where($qb->expr()->in('mount_provider_class', $qb->createPositionalParameter(self::ALLOWED_MOUNT_TYPES, IQueryBuilder::PARAM_STR_ARRAY)));
		$result = $qb->executeQuery();

		/** @var array $row */
		while ($row = $result->fetch()) {
			$storageId = intval($row['storage_id']);
			$rootId = intval($row['root_id']);
			$overrideRoot = $rootId;
			if (in_array($row['mount_provider_class'], self::HOME_MOUNT_TYPES)) {
				// Only crawl files, not cache or trashbin
				$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
				try {
					/** @var array|false $root */
					$root = $qb->selectFileCache()
						->andWhere($qb->expr()->eq('filecache.storage', $qb->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
						->andWhere($qb->expr()->eq('filecache.path', $qb->createNamedParameter('files')))
						->executeQuery()->fetch();
					if ($root !== false) {
						$overrideRoot = intval($root['fileid']);
					}
				} catch (Exception $e) {
					$this->logger->error('Could not fetch home storage files root for storage '.$storageId, ['exception' => $e]);
					continue;
				}
			}
			yield [
				'storage_id' => $storageId,
				'root_id' => $rootId,
				'override_root' => $overrideRoot,
			];
		}
	}

	/**
	 * @param int $storageId
	 * @param int $rootId
	 * @param int $lastFileId
	 * @param int $maxResults
	 * @return \Generator|void
	 */
	public function getFilesInMount(int $storageId, int $rootId, array $models, int $lastFileId = 0, int $maxResults = 100) {
		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
		try {
			$root = $qb->selectFileCache()
				->andWhere($qb->expr()->eq('filecache.fileid', $qb->createNamedParameter($rootId, IQueryBuilder::PARAM_INT)))
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
			$ignorePathsAll = $this->ignoreService->getIgnoredDirectories($storageId, Constants::IGNORE_MARKERS_ALL);
			$ignorePathsImage = $this->ignoreService->getIgnoredDirectories($storageId, Constants::IGNORE_MARKERS_IMAGE);
			$ignorePathsVideo = $this->ignoreService->getIgnoredDirectories($storageId, Constants::IGNORE_MARKERS_VIDEO);
			$ignorePathsAudio = $this->ignoreService->getIgnoredDirectories($storageId, Constants::IGNORE_MARKERS_AUDIO);
		} catch (Exception $e) {
			$this->logger->error('Could not fetch ignore files', ['exception' => $e]);
			return;
		}

		$imageTypes = array_map(fn ($mimeType) => $this->mimeTypes->getId($mimeType), Constants::IMAGE_FORMATS);
		$videoTypes = array_map(fn ($mimeType) => $this->mimeTypes->getId($mimeType), Constants::VIDEO_FORMATS);
		$audioTypes = array_map(fn ($mimeType) => $this->mimeTypes->getId($mimeType), Constants::AUDIO_FORMATS);

		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
		$ignoreFileidsExpr = [];
		if (count(array_intersect([ClusteringFaceClassifier::MODEL_NAME, ImagenetClassifier::MODEL_NAME, LandmarksClassifier::MODEL_NAME], $models)) > 0) {
			$expr = array_map(fn (string $path): string => $qb->expr()->notLike('path', $qb->createNamedParameter($path ? $path . '/%' : '%')), $ignorePathsImage);
			$ignoreFileidsExpr[] = $qb->expr()->andX($qb->expr()->in('mimetype', $qb->createNamedParameter($imageTypes, IQueryBuilder::PARAM_INT_ARRAY)), ...$expr);
		}
		if (in_array(MovinetClassifier::MODEL_NAME, $models)) {
			$expr = array_map(fn (string $path): string => $qb->expr()->notLike('path', $qb->createNamedParameter($path ? $path . '/%' : '%')), $ignorePathsVideo);
			$ignoreFileidsExpr[] = $qb->expr()->andX($qb->expr()->in('mimetype', $qb->createNamedParameter($videoTypes, IQueryBuilder::PARAM_INT_ARRAY)), ...$expr);
		}
		if (in_array(MusicnnClassifier::MODEL_NAME, $models)) {
			$expr = array_map(fn (string $path): string => $qb->expr()->notLike('path', $qb->createNamedParameter($path ? $path . '/%' : '%')), $ignorePathsAudio);
			$ignoreFileidsExpr[] = $qb->expr()->andX($qb->expr()->in('mimetype', $qb->createNamedParameter($audioTypes, IQueryBuilder::PARAM_INT_ARRAY)), ...$expr);
		}
		if (count($ignoreFileidsExpr) === 0) {
			return;
		}

		try {
			$path = $root['path'] === '' ? '' :  $root['path'] . '/';
			$ignoreExprAll = array_map(fn (string $path): string => $qb->expr()->notLike('path', $qb->createNamedParameter($path ? $path . '/%' : '%')), $ignorePathsAll);

			$qb->selectFileCache()
				->whereStorageId($storageId)
				->andWhere($qb->expr()->like('path', $qb->createNamedParameter($path . '%')))
				->andWhere($qb->expr()->eq('storage', $qb->createNamedParameter($storageId)))
				->andWhere($qb->expr()->gt('filecache.fileid', $qb->createNamedParameter($lastFileId)))
				->andWhere($qb->expr()->orX(...$ignoreFileidsExpr));
			if (count($ignoreExprAll) > 0) {
				$qb->andWhere($qb->expr()->andX(...$ignoreExprAll));
			}
			if ($maxResults !== 0) {
				$qb->setMaxResults($maxResults);
			}
			$files = $qb->orderBy('filecache.fileid', 'ASC')
				->executeQuery();
		} catch (Exception $e) {
			$this->logger->error('Could not fetch files', ['exception' => $e]);
			return;
		}

		/** @var array $file */
		while ($file = $files->fetch()) {
			yield [
				'fileid' => $file['fileid'],
				'image' => in_array($file['mimetype'], $imageTypes),
				'video' => in_array($file['mimetype'], $videoTypes),
				'audio' => in_array($file['mimetype'], $audioTypes),
			];
		}
	}
}
