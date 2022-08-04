<?php

namespace OCA\Recognize\Command;

use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCP\BackgroundJob\IJobList;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Recrawl extends Command {
	private IJobList $jobList;

	public function __construct(IJobList $jobList) {
		parent::__construct();
		$this->jobList = $jobList;
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
			return 1;
		}

		return 0;
	}
}
