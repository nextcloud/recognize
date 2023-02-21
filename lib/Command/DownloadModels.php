<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Command;

use OCA\Recognize\Service\DownloadModelsService;
use OCA\Recognize\Vendor\OCA\Recognize\Vendor\Symfony\Component\Console\Command\Command;
use OCA\Recognize\Vendor\OCA\Recognize\Vendor\Symfony\Component\Console\Input\InputInterface;
use OCA\Recognize\Vendor\OCA\Recognize\Vendor\Symfony\Component\Console\Output\OutputInterface;

class DownloadModels extends Command {
	private DownloadModelsService $downloader;


	public function __construct(DownloadModelsService $downloader) {
		parent::__construct();
		$this->downloader = $downloader;
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:download-models')
			->setDescription('Download the necessary machine learning models');
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
			$this->downloader->download();
		} catch (\Exception $ex) {
			$output->writeln('<error>Failed to download models</error>');
			$output->writeln($ex->getMessage());
			return 1;
		}

		return 0;
	}
}
