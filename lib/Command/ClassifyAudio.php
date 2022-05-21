<?php

namespace OCA\Recognize\Command;

use OCA\Recognize\Service\ClassifyAudioService;
use OCA\Recognize\Service\Logger;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClassifyAudio extends Command {
	private IUserManager $userManager;

	private ClassifyAudioService $audioClassifier;


	private Logger $logger;

	private IConfig $config;

	public function __construct(IUserManager $userManager, ClassifyAudioService $videoClassifier, Logger $logger, IConfig $config) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->audioClassifier = $videoClassifier;
		$this->logger = $logger;
		$this->config = $config;
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
			$this->logger->setCliOutput($output);
			foreach ($users as $user) {
				do {
					$anythingClassified = $this->audioClassifier->run($user, 500);
					if ($anythingClassified) {
						$this->config->setAppValue('recognize', 'images.status', 'true');
					}
				} while ($anythingClassified);
			}
		} catch (\Exception $ex) {
			$this->config->setAppValue('recognize', 'audio.status', 'false');
			$output->writeln('<error>Failed to classify audio</error>');
			$output->writeln($ex->getMessage());
			return 1;
		}

		return 0;
	}
}
