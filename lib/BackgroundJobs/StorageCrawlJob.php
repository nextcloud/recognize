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
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
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

	public function __construct(ITimeFactory $timeFactory, Logger $logger, IMimeTypeLoader $mimeTypes, QueueService $queue, IJobList $jobList, IDBConnection $db, SystemConfig $systemConfig) {
		parent::__construct($timeFactory);
		$this->logger = $logger;
		$this->mimeTypes = $mimeTypes;
		$this->queue = $queue;
		$this->jobList = $jobList;
		$this->db = $db;
		$this->systemConfig = $systemConfig;
	}

	protected function run($argument): void {
		$storageId = $argument['storage_id'];
		$rootId = $argument['root_id'];
		$lastFileId = $argument['last_file_id'];
		$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
		try {
			$root = $qb->selectFileCache()
				->whereStorageId($storageId)
				->andWhere($qb->expr()->eq('filecache.fileid', $qb->createNamedParameter($rootId, IQueryBuilder::PARAM_INT)))
				->executeQuery()->fetch();
		} catch (Exception $e) {
			$this->logger->error('Could not fetch storage root', ['exception' => $e]);
			return;
		}

		$imageType = $this->mimeTypes->getId('image');
		$videoType = $this->mimeTypes->getId('video');
		$audioType = $this->mimeTypes->getId('audio');

		try {
			$qb = new CacheQueryBuilder($this->db, $this->systemConfig, $this->logger);
			$files = $qb->selectFileCache()
				->whereStorageId($storageId)
				->andWhere($qb->expr()->like('path', $qb->createNamedParameter($root['path'] . '%')))
				->andWhere($qb->expr()->in('mimepart', $qb->createNamedParameter([
					$imageType, $videoType, $audioType
				], IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->gt('filecache.fileid', $qb->createNamedParameter($lastFileId)))
				->orderBy('filecache.fileid', 'ASC')
				->setMaxResults(100)
				->executeQuery();
		} catch (Exception $e) {
			$this->logger->error('Could not fetch files', ['exception' => $e]);
			return;
		}

		// Remove current iteration
		$this->jobList->remove(self::class, $argument);

		if ($files->rowCount() === 0) {
			return;
		}

		while ($file = $files->fetch()) {
			$queueFile = new QueueFile();
			$queueFile->setStorageId($storageId);
			$queueFile->setRootId($rootId);
			$queueFile->setFileId($file['fileid']);
			$queueFile->setUpdate(false);
			try {
				switch ($file['mimepart']) {
					case $imageType:
						$this->queue->insertIntoQueue(ImagenetClassifier::MODEL_NAME, $queueFile);
						$this->queue->insertIntoQueue(ClusteringFaceClassifier::MODEL_NAME, $queueFile);
						break;
					case $videoType:
						$this->queue->insertIntoQueue(MovinetClassifier::MODEL_NAME, $queueFile);
						break;
					case $audioType:
						$this->queue->insertIntoQueue(MusicnnClassifier::MODEL_NAME, $queueFile);
				}
			} catch (Exception $e) {
				$this->logger->error('Failed to add file to queue', ['exception' => $e]);
				return;
			}
		}

		// Schedule next iteration
		$this->jobList->add(self::class, [
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'last_file_id' => $queueFile->getFileId()
		]);
	}
}
