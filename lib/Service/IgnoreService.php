<?php

namespace OCA\Recognize\Service;

use OC\Files\Cache\CacheQueryBuilder;
use OC\SystemConfig;
use OCA\Recognize\Constants;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IMimeTypeLoader;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class IgnoreService {
	private IDBConnection $db;
	private SystemConfig $systemConfig;
	private LoggerInterface $logger;
	private IMimeTypeLoader $mimeTypes;

	public function __construct(IDBConnection $db, SystemConfig $systemConfig, LoggerInterface $logger, IMimeTypeLoader $mimeTypes) {
		$this->db = $db;
		$this->systemConfig = $systemConfig;
		$this->logger = $logger;
		$this->mimeTypes = $mimeTypes;
	}
	/**
	 * @param int $storageId
	 * @param array $ignoreMarkers
	 * @return list<string>
	 */
	public function getIgnoredDirectories(int $storageId, array $ignoreMarkers): array {
		$directoryTypes = array_map(fn ($mimeType) => $this->mimeTypes->getId($mimeType), Constants::DIRECTORY_FORMATS);
		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
		$result = $qb->selectFileCache()
			->andWhere($qb->expr()->in('name', $qb->createNamedParameter($ignoreMarkers, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->eq('storage', $qb->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		/**
		 * @var list<array> $ignoreFiles
		 */
		$ignoreFiles = $result->fetchAll();
		$ignoredPaths = array_map(fn ($dir): string => dirname($dir['path']), $ignoreFiles);
		return $ignoredPaths;
	}
}
