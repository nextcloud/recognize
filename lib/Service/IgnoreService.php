<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OC\Files\Cache\CacheQueryBuilder;
use OC\SystemConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class IgnoreService {
	private IDBConnection $db;
	private SystemConfig $systemConfig;
	private LoggerInterface $logger;
	private array $inMemoryCache = [];
	private ICache $localCache;

	public function __construct(IDBConnection $db, SystemConfig $systemConfig, LoggerInterface $logger, ICacheFactory $cacheFactory) {
		$this->db = $db;
		$this->systemConfig = $systemConfig;
		$this->logger = $logger;
		$this->localCache = $cacheFactory->createLocal('recognize-ignored-directories');
	}

	/**
	 * @param int $storageId
	 * @param array $ignoreMarkers
	 * @return list<string>
	 * @throws \OCP\DB\Exception
	 */
	public function getIgnoredDirectories(int $storageId, array $ignoreMarkers): array {
		$cacheKey = $storageId . '-' . implode(',', $ignoreMarkers);
		if (isset($this->inMemoryCache[$cacheKey])) {
			return $this->inMemoryCache[$cacheKey];
		}
		$directories = $this->localCache->get($cacheKey);
		if ($directories !== null) {
			return $directories;
		}

		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
		$result = $qb->selectFileCache()
			->andWhere($qb->expr()->in('name', $qb->createNamedParameter($ignoreMarkers, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->eq('storage', $qb->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		/**
		 * @var list<array{path: string}> $ignoreFiles
		 */
		$ignoreFiles = $result->fetchAll();
		$directories = array_map(static fn ($file): string => dirname($file['path']), $ignoreFiles);
		$this->inMemoryCache[$cacheKey] = $directories;
		$this->localCache->set($cacheKey, $directories);
		return $directories;
	}
}
