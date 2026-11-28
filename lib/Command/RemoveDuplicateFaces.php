<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Command;

use OCA\Recognize\Migration\RemoveDuplicateFaceDetections;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exposes the duplicate face detection repair step as a manually runnable command.
 */
final class RemoveDuplicateFaces extends Command {
	public function __construct(
		private RemoveDuplicateFaceDetections $repairStep,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('recognize:remove-duplicate-faces')
			->setDescription('Remove face detections that describe the same face of the same user in the same file more than once')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report the number of duplicate detections instead of removing them');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$dryRun = (bool) $input->getOption('dry-run');

		try {
			$count = $this->repairStep->removeDuplicates($dryRun);
		} catch (\Throwable $e) {
			$output->writeln('<error>Failed to remove duplicate face detections</error>');
			$output->writeln($e->getMessage());
			return 1;
		}

		if ($count === 0) {
			$output->writeln('No duplicate face detections found.');
		} elseif ($dryRun) {
			$output->writeln('Would remove ' . $count . ' duplicate face detections. Run without --dry-run to remove them.');
		} else {
			$output->writeln('Removed ' . $count . ' duplicate face detections.');
		}

		return 0;
	}
}
