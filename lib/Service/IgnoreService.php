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

	public function getDir(int $fileid, array $directoryTypes, bool $recursive = false): array {
		/** @var \OCP\DB\QueryBuilder\IQueryBuilder $qb */
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

	public function getIgnoredDirectories(int $storageId, array $ignoreMarkers): array {
		$directoryTypes = array_map(fn ($mimeType) => $this->mimeTypes->getId($mimeType), Constants::DIRECTORY_FORMATS);
		/** @var \OCP\DB\QueryBuilder\IQueryBuilder $qb */
		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
		$ignoreFiles = $qb->selectFileCache()
			->andWhere($qb->expr()->in('name', $qb->createNamedParameter($ignoreMarkers, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->eq('storage', $qb->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
			->executeQuery()->fetchAll();
		$ignoreFileids = array_map(fn ($dir) => $dir['parent'], $ignoreFiles);
		foreach ($ignoreFiles as $ignoreFile) {
			$ignoreDir = $this->getDir($ignoreFile['parent'], $directoryTypes, true);
			$fileids = array_map(fn ($dir) => $dir['fileid'], $ignoreDir);
			$ignoreFileids = array_merge($ignoreFileids, $fileids);
		}
		return $ignoreFileids;
	}
}
