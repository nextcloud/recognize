<?php

namespace OCA\Recognize\Command;

use OCA\Recognize\Service\ClassifyVideoService;
use OCA\Recognize\Service\FileCrawlerService;
use OCA\Recognize\Service\Logger;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClassifyVideo extends Command {
	private IUserManager $userManager;

	private ClassifyVideoService $videoClassifier;

	private Logger $logger;

	private IConfig $config;

	private FileCrawlerService $fileCrawlerService;

	public function __construct(IUserManager $userManager, ClassifyVideoService $videoClassifier, Logger $logger, IConfig $config, FileCrawlerService $fileCrawlerService) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->videoClassifier = $videoClassifier;
		$this->logger = $logger;
		$this->config = $config;
		$this->fileCrawlerService = $fileCrawlerService;
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:classify-video')
			->setDescription('Classify all videos in this installation');
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
				if ($this->config->getUserValue($user, 'recognize', 'crawl.done', 'false') === 'false') {
					$this->fileCrawlerService->crawlForUser($user);
					$this->config->setUserValue($user, 'recognize', 'crawl.done', 'true');
				}

				do {
					$anythingClassified = $this->videoClassifier->run($user, 500);
					if ($anythingClassified) {
						$this->config->setAppValue('recognize', 'video.status', 'true');
					}
				} while ($anythingClassified);
			}
		} catch (\Exception $ex) {
			$this->config->setAppValue('recognize', 'video.status', 'false');
			$output->writeln('<error>Failed to classify audio</error>');
			$output->writeln($ex->getMessage());
			return 1;
		}

		return 0;
	}
}
