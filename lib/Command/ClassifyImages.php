<?php

namespace OCA\Recognize\Command;

use OCA\Recognize\Service\ClassifyImagesService;
use OCA\Recognize\Service\FaceClusterAnalyzer;
use OCA\Recognize\Service\Logger;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClassifyImages extends Command {
	private IUserManager $userManager;

	private ClassifyImagesService $imageClassifier;

	private Logger $logger;

	private IConfig $config;
    /**
     * @var \OCA\Recognize\Service\FaceClusterAnalyzer
     */
    private FaceClusterAnalyzer $faceClusterAnalyzer;


    public function __construct(IUserManager $userManager, ClassifyImagesService $imageClassifier, Logger $logger, IConfig $config, FaceClusterAnalyzer $faceClusterAnalyzer) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->imageClassifier = $imageClassifier;
		$this->logger = $logger;
		$this->config = $config;
        $this->faceClusterAnalyzer = $faceClusterAnalyzer;
    }

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:classify-images')
			->setDescription('Classify all photos in this installation');
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
					$anythingClassified = $this->imageClassifier->run($user, 500);
					if ($anythingClassified) {
						$this->config->setAppValue('recognize', 'images.status', 'true');
					}
				} while ($anythingClassified);
                $this->faceClusterAnalyzer->findClusters($user);
			}
		} catch (\Exception $ex) {
			$this->config->setAppValue('recognize', 'images.status', 'false');
			$output->writeln('<error>Failed to classify images</error>');
			$output->writeln($ex->getMessage());
			return 1;
		}

		return 0;
	}
}
