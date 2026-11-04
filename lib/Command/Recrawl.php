<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Command;

use OCA\Recognize\Service\QueueService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Recrawl extends Command {
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
		$this->setName('recognize:recrawl')
			->setDescription('Trigger a full classification run in the background');
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
			$this->queue->scheduleRecrawl();
		} catch (\Exception $ex) {
			$output->writeln('<error>Failed to schedule recrawl</error>');
			$output->writeln($ex->getMessage());
			$this->logger->error('Failed to schedule recrawl: '.$ex->getMessage(), ['exception' => $ex]);
			return 1;
		}

		return 0;
	}
}
