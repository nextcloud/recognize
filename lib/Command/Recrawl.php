<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Command;

use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCP\BackgroundJob\IJobList;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Recrawl extends Command {
	private IJobList $jobList;
	private LoggerInterface $logger;

	public function __construct(IJobList $jobList, LoggerInterface $logger) {
		parent::__construct();
		$this->jobList = $jobList;
		$this->logger = $logger;
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:recrawl')
			->setDescription('Go through all files again');
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
			$this->jobList->add(SchedulerJob::class);
		} catch (\Exception $ex) {
			$output->writeln('<error>Failed to schedule recrawl</error>');
			$output->writeln($ex->getMessage());
			$this->logger->error('Failed to schedule recrawl: '.$ex->getMessage(), ['exception' => $ex]);
			return 1;
		}

		return 0;
	}
}
