<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Command;

use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetFaceClusters extends Command {
	private FaceDetectionMapper $faceDetectionMapper;
	private FaceClusterMapper $clusterMapper;

	public function __construct(FaceDetectionMapper $faceDetectionMapper, FaceClusterMapper $clusterMapper) {
		parent::__construct();
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->clusterMapper = $clusterMapper;
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:reset-face-clusters')
			->setDescription('Remove all face clusters. Detected face will stay intact.');
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
		try {
			$this->clusterMapper->deleteAll();
			$this->faceDetectionMapper->removeAllClusters();
		} catch (\Exception $ex) {
			$output->writeln('<error>Failed to reset face clusters</error>');
			$output->writeln($ex->getMessage());
			return 1;
		}

		return 0;
	}
}
