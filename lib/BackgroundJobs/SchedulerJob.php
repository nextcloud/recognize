<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\BackgroundJobs;

use OCA\Recognize\Service\Logger;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class SchedulerJob extends QueuedJob {
	public const INTERVAL = 30 * 60; // 30 minutes
	public const ALLOWED_MOUNT_TYPES = [
		'OC\Files\Mount\LocalHomeMountProvider',
		'OC\Files\Mount\ObjectHomeMountProvider',
		'OCA\Files_External\Config\ConfigAdapter',
		'OCA\GroupFolders\Mount\MountProvider'
	];

	private LoggerInterface $logger;
	private IDBConnection $db;
	private IJobList $jobList;

	public function __construct(ITimeFactory $timeFactory, Logger $logger, IDBConnection $db, IJobList $jobList) {
		parent::__construct($timeFactory);
		$this->logger = $logger;
		$this->db = $db;
		$this->jobList = $jobList;
	}

	protected function run($argument): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('root_id', 'storage_id')
			->from('mounts')
			->where($qb->expr()->in('mount_provider_class', $qb->createPositionalParameter(self::ALLOWED_MOUNT_TYPES, IQueryBuilder::PARAM_STR_ARRAY)));
		$result = $qb->executeQuery();

		while ($row = $result->fetch()) {
			$this->jobList->add(StorageCrawlJob::class, [
				'storage_id' => (int)$row['storage_id'],
				'root_id' => (int)$row['root_id'],
				'last_file_id' => 0,
			]);
		}

		$this->jobList->remove(self::class);
	}
}
