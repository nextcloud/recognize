<?php

namespace OCA\Recognize\Command;

use OCA\Recognize\Service\ClassifyImagesService;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\TagManager;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetTags extends Command {
    /**
     * @var \OCA\Recognize\Service\TagManager
     */
    private $tagManager;


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
