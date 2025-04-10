<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Command;

use OCA\Recognize\Service\TagManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ResetTags extends Command {
	private TagManager $tagManager;

	public function __construct(TagManager $tagManager) {
		parent::__construct();
		$this->tagManager = $tagManager;
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:reset-tags')
			->setDescription('Remove all tags from previously classified files');
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
			$this->tagManager->resetClassifications();
		} catch (\Exception $ex) {
			$output->writeln('<error>Failed to reset tags</error>');
			$output->writeln($ex->getMessage());
			return 1;
		}

		return 0;
	}
}
