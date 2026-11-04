<?php

/*
 * Copyright (c) 2023 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Command;

use OCA\Recognize\Service\QueueService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ClearBackgroundJobs extends Command {
	private LoggerInterface $logger;
	
	private QueueService $queue;

	public function __construct(LoggerInterface $logger, QueueService $queue) {
		parent::__construct();
		$this->logger = $logger;
		$this->queue = $queue;
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:clear-background-jobs')
			->setDescription('Remove all files from all queues and remove all scheduled background jobs');
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
			$this->queue->clearAllJobs();
		} catch (\Exception $ex) {
			$output->writeln('<error>Failed to clear background jobs</error>');
			$output->writeln($ex->getMessage());
			$this->logger->error('Failed to clear background jobs: '.$ex->getMessage(), ['exception' => $ex]);
			return 1;
		}

		return 0;
	}
}
