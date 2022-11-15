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
	 * @param int $fileid
	 * @param array $directoryTypes
	 * @param bool $recursive
	 * @return list<array>
	 */
	public function getDir(int $fileid, array $directoryTypes, bool $recursive = false): array {
		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
		$result = $qb->selectFileCache()
			->andWhere($qb->expr()->in('mimetype', $qb->createNamedParameter($directoryTypes, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('parent', $qb->createNamedParameter($fileid)))
			->executeQuery();
		/**
		 * @var list<array> $dir
		 */
		$dir = $result->fetchAll();

		if ($recursive) {
			foreach ($dir as $item) {
				$dir = array_merge($dir, $this->getDir((int) $item['fileid'], $directoryTypes, $recursive));
			}
		}
		return $dir;
	}

	/**
	 * @param int $storageId
	 * @param array $ignoreMarkers
	 * @return array
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
		$ignoreFileIds = array_map(fn ($dir): int => (int)$dir['parent'], $ignoreFiles);
		foreach ($ignoreFiles as $ignoreFile) {
			$ignoreDir = $this->getDir((int) $ignoreFile['parent'], $directoryTypes, true);
			$fileIds = array_map(fn ($dir): int => (int) $dir['fileid'], $ignoreDir);
			$ignoreFileIds = array_merge($ignoreFileIds, $fileIds);
		}
		return $ignoreFileIds;
	}
}
