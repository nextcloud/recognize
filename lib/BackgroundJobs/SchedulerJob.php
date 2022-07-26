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
use OCP\BackgroundJob\TimedJob;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class SchedulerJob extends TimedJob {
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

		$this->setInterval(self::INTERVAL);
		$this->logger = $logger;
		$this->db = $db;
		$this->jobList = $jobList;
	}

	protected function run($argument): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('root_id', 'storage_id')
			->from('oc_mounts')
			->where($qb->expr()->in('mount_provider_class', self::ALLOWED_MOUNT_TYPES));
		$result = $qb->executeQuery();
		while ($row = $result->fetchOne()) {
			$this->jobList->add(StorageCrawlJob::class, [
				'storage_id' => $row['storage_id'],
				'root_id' => $row['root_id'],
				'last_file_id' => 0,
			]);
		}
	}
}
