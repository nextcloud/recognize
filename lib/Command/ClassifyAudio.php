<?php

namespace OCA\Recognize\Command;

use OCA\Recognize\Service\ClassifyAudioService;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClassifyAudio extends Command {
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var \OCA\Recognize\Service\ClassifyAudioService
	 */
	private $audioClassifier;

	public function __construct(IUserManager $userManager, ClassifyAudioService $audioClassifier, LoggerInterface $logger) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->audioClassifier = $audioClassifier;
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:classify-audio')
			->setDescription('Classify all audio in this installation');
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
			$users = [];
			$this->userManager->callForSeenUsers(function (IUser $user) use (&$users) {
				$users[] = $user->getUID();
			});

			foreach ($users as $user) {
				$this->audioClassifier->run($user);
			}
		} catch (\Exception $ex) {
			$output->writeln('<error>Failed to classify audio</error>');
			$output->writeln($ex->getMessage());
			return 1;
		}

		return 0;
	}
}
