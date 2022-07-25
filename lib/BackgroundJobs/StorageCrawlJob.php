<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OC\Files\Cache\CacheQueryBuilder;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IMimeTypeLoader;
use Psr\Log\LoggerInterface;

class StorageCrawlJob extends Job {
	private LoggerInterface $logger;
	private CacheQueryBuilder $cacheQueryBuilder;
	private IMimeTypeLoader $mimeTypes;
	private QueueService $queue;
	private IJobList $jobList;

	public function __construct(ITimeFactory $timeFactory, Logger $logger, CacheQueryBuilder $cacheQueryBuilder, IMimeTypeLoader $mimeTypes, QueueService $queue, IJobList $jobList) {
		parent::__construct($timeFactory);
		$this->logger = $logger;
		$this->cacheQueryBuilder = $cacheQueryBuilder;
		$this->mimeTypes = $mimeTypes;
		$this->queue = $queue;
		$this->jobList = $jobList;
	}

	protected function run($argument): void {
		$storageId = $argument['storage_id'];
		$rootId = $argument['root_id'];
		$lastFileId = $argument['last_file_id'];
		$qb = $this->cacheQueryBuilder;
		try {
			$root = $qb->selectFileCache()
				->whereStorageId($storageId)
				->where($qb->expr()->eq('fileid', $qb->createPositionalParameter($rootId, IQueryBuilder::PARAM_INT)))
				->executeQuery()->fetchOne();
		} catch (Exception $e) {
			$this->logger->error('Could not fetch storage root', ['exception' => $e]);
			return;
		}

		$imageType = $this->mimeTypes->getId('image');
		$videoType = $this->mimeTypes->getId('video');
		$audioType = $this->mimeTypes->getId('audio');

		try {
			$files = $qb->selectFileCache()
				->whereStorageId($storageId)
				->where($qb->expr()->like('path', $qb->createPositionalParameter($root['path'] . '%')))
				->andWhere($qb->expr()->in('mimetype', $qb->createPositionalParameter([
					$imageType, $videoType, $audioType
				])))
				->andWhere($qb->expr()->gt('fileid', $lastFileId))
				->setMaxResults(100)
				->executeQuery();
		} catch (Exception $e) {
			$this->logger->error('Could not fetch files', ['exception' => $e]);
			return;
		}

		if ($files->rowCount() === 0) {
			return;
		}

		while ($file = $files->fetchOne()) {
			$queueFile = new QueueFile();
			$queueFile->setStorageId($storageId);
			$queueFile->setRootId($rootId);
			$queueFile->setFileId($file['fileid']);
			$queueFile->setUpdate(false);
			try {
				switch ($file['mimetype']) {
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

		$this->jobList->add(self::class, [
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'last_file_id' => $file['fileid']
		]);
	}
}
