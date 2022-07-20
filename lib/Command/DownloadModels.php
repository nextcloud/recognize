<?php

namespace OCA\Recognize\Command;

use OCA\Recognize\Service\DownloadModelsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
