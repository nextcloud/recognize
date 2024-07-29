<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
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
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\IDBConnection;

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

	public function __construct(
		private IDBConnection $db,
		private Logger $logger,
		private SystemConfig $systemConfig,
		private IgnoreService $ignoreService,
		private IMimeTypeLoader $mimeTypes,
		private IFilesMetadataManager $metadataManager,
	) {
	}

	/**
	 * @return \Generator<array{root_id: int, override_root: int, storage_id: int}>
	 * @throws \OCP\DB\Exception
	 */
	public function getMounts(): \Generator {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct(['root_id', 'storage_id', 'mount_provider_class']) // to avoid scanning each occurrence of a groupfolder
			->from('mounts')
			->where($qb->expr()->in('mount_provider_class', $qb->createPositionalParameter(self::ALLOWED_MOUNT_TYPES, IQueryBuilder::PARAM_STR_ARRAY)));
		$result = $qb->executeQuery();


		while (
			/** @var array{storage_id:int, root_id:int,mount_provider_class:string} $row */
			$row = $result->fetch()
		) {
			$storageId = (int)$row['storage_id'];
			$rootId = (int)$row['root_id'];
			$overrideRoot = $rootId;
			if (in_array($row['mount_provider_class'], self::HOME_MOUNT_TYPES)) {
				// Only crawl files, not cache or trashbin
				$qb = new CacheQueryBuilder($this->db->getQueryBuilder(), $this->metadataManager);
				try {
					$res = $qb->selectFileCache()
						->andWhere($qb->expr()->eq('filecache.storage', $qb->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
						->andWhere($qb->expr()->eq('filecache.path', $qb->createNamedParameter('files')))
						->executeQuery();
					/** @var array|false $root */
					$root = $res->fetch();
					$res->closeCursor();
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
		$result->closeCursor();
	}

	/**
	 * @param int $storageId
	 * @param int $rootId
	 * @param array $models
	 * @param int $lastFileId
	 * @param int $maxResults
	 * @return \Generator<int,array{fileid:int, image:bool, video:bool, audio:bool},mixed,void>
	 */
	public function getFilesInMount(int $storageId, int $rootId, array $models, int $lastFileId = 0, int $maxResults = 100) : \Generator {
		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger, $this->metadataManager);
		try {
			$result = $qb->selectFileCache()
				->andWhere($qb->expr()->eq('filecache.fileid', $qb->createNamedParameter($rootId, IQueryBuilder::PARAM_INT)))
				->executeQuery();
			/** @var array{path:string}|false $root */
			$root = $result->fetch();
			$result->closeCursor();
		} catch (Exception $e) {
			$this->logger->error('Could not fetch storage root', ['exception' => $e]);
			return;
		}

		if ($root === false) {
			$this->logger->error('Could not fetch storage root');
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

		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger, $this->metadataManager);
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

		while (
			/** @var array */
			$file = $files->fetch()
		) {
			yield [
				'fileid' => (int) $file['fileid'],
				'image' => in_array((int) $file['mimetype'], $imageTypes),
				'video' => in_array((int) $file['mimetype'], $videoTypes),
				'audio' => in_array((int) $file['mimetype'], $audioTypes),
			];
		}

		$files->closeCursor();
	}
}
