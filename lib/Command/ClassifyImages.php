<?php

namespace OCA\Recognize\Command;

use OCA\Recognize\Service\ClassifyImagenetService;
use OCA\Recognize\Service\ClassifyFacesService;
use OCA\Recognize\Service\ClassifyImagesService;
use OCA\Recognize\Service\ImagesFinderService;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClassifyImages extends Command {
    /**
     * @var ClassifyImagenetService
     */
    private $imagenet;
    /**
     * @var ClassifyFacesService
     */
    private $facenet;
    /**
     * @var ImagesFinderService
     */
    private $imagesFinder;
    /**
     * @var IRootFolder
     */
    private $rootFolder;
    /**
     * @var IUserManager
     */
    private $userManager;
    /**
     * @var \OCA\Recognize\Service\ReferenceFacesFinderService
     */
    private $referenceFacesFinder;
    /**
     * @var \OCA\Recognize\Service\ClassifyImagesService
     */
    private $imageClassifier;

    public function __construct(IUserManager $userManager, ClassifyImagesService $imageClassifier)
    {
        parent::__construct();
        $this->userManager = $userManager;
        $this->imageClassifier = $imageClassifier;
    }

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure()
    {
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $users = [];
            $this->userManager->callForSeenUsers(function(IUser $user) use (&$users) {
                $users[] = $user->getUID();
            });

            foreach ($users as $user) {
                $this->imageClassifier->run($user);
            }
        } catch (\Exception $ex) {
            $output->writeln('<error>Failed to classify images</error>');
            $output->writeln($ex->getMessage());
            return 1;
        }

        return 0;
    }
}
