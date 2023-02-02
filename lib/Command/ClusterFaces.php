<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Command;

use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\FaceClusterAnalyzer;
use OCA\Recognize\Service\Logger;
use OCP\DB\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClusterFaces extends Command {
	private Logger $logger;

	private FaceDetectionMapper $detectionMapper;

	private FaceClusterAnalyzer $clusterAnalyzer;

	public function __construct(Logger $logger, FaceDetectionMapper $detectionMapper, FaceClusterAnalyzer $clusterAnalyzer) {
		parent::__construct();
		$this->logger = $logger;
		$this->detectionMapper = $detectionMapper;
		$this->clusterAnalyzer = $clusterAnalyzer;
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:cluster-faces')
			->setDescription('Cluster detected faces per user');
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->logger->setCliOutput($output);

		try {
			$userIds = $this->detectionMapper->findUserIds();
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return 1;
		}

		foreach ($userIds as $userId) {
			$this->logger->info('Clustering face detections for user ' . $userId);
			try {
				$this->clusterAnalyzer->calculateClusters($userId);
			} catch (\JsonException|Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}
		return 0;
	}
}
