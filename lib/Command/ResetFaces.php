<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Command;

use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Vendor\Symfony\Component\Console\Command\Command;
use OCA\Recognize\Vendor\Symfony\Component\Console\Input\InputInterface;
use OCA\Recognize\Vendor\Symfony\Component\Console\Output\OutputInterface;

class ResetFaces extends Command {
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
		$this->setName('recognize:reset-faces')
			->setDescription('Remove all face detections from previously classified files');
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
			$this->faceDetectionMapper->deleteAll();
		} catch (\Exception $ex) {
			$output->writeln('<error>Failed to reset faces</error>');
			$output->writeln($ex->getMessage());
			return 1;
		}

		return 0;
	}
}
