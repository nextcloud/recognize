<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Command;

use OCA\Recognize\BackgroundJobs\ClassifyFacesJob;
use OCA\Recognize\BackgroundJobs\ClassifyImagenetJob;
use OCA\Recognize\BackgroundJobs\ClassifyLandmarksJob;
use OCA\Recognize\BackgroundJobs\ClassifyMovinetJob;
use OCA\Recognize\BackgroundJobs\ClassifyMusicnnJob;
use OCA\Recognize\BackgroundJobs\ClusterFacesJob;
use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\BackgroundJobs\StorageCrawlJob;
use OCA\Recognize\Service\QueueService;
use OCP\BackgroundJob\IJobList;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Recrawl extends Command {
	private IJobList $jobList;
	private LoggerInterface $logger;
	private QueueService $queue;

	public function __construct(IJobList $jobList, LoggerInterface $logger, QueueService $queue) {
		parent::__construct();
		$this->jobList = $jobList;
		$this->logger = $logger;
		$this->queue = $queue;
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
			$this->queue->clearQueue('imagenet');
			$this->queue->clearQueue('faces');
			$this->queue->clearQueue('landmarks');
			$this->queue->clearQueue('movinet');
			$this->queue->clearQueue('musicnn');
			$this->jobList->remove(ClassifyFacesJob::class);
			$this->jobList->remove(ClassifyImagenetJob::class);
			$this->jobList->remove(ClassifyLandmarksJob::class);
			$this->jobList->remove(ClassifyMusicnnJob::class);
			$this->jobList->remove(ClassifyMovinetJob::class);
			$this->jobList->remove(ClusterFacesJob::class);
			$this->jobList->remove(SchedulerJob::class);
			$this->jobList->remove(StorageCrawlJob::class);
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
