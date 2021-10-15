<?php

namespace OCA\Recognize\Command;

use OCA\Recognize\Service\AudioFinderService;
use OCA\Recognize\Service\ClassifyMusicService;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClassifyAudio extends Command {
    /**
     * @var ClassifyMusicService
     */
    private $musicnn;
    /**
     * @var AudioFinderService
     */
    private $audioFinder;
    /**
     * @var IRootFolder
     */
    private $rootFolder;
    /**
     * @var IUserManager
     */
    private $userManager;

    public function __construct(ClassifyMusicService $musicnn, IRootFolder $rootFolder, IUserManager $userManager, AudioFinderService $audioFinder)
    {
        parent::__construct();
        $this->musicnn = $musicnn;
        $this->rootFolder = $rootFolder;
        $this->userManager = $userManager;
        $this->audioFinder = $audioFinder;
    }

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('recognize:classify-audio')
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
                if (!$user) {
                    $output->writeln('No users left, whose audio could be classified');
                    return 0;
                }
                $images = $this->audioFinder->findAudioInFolder($this->rootFolder->getUserFolder($user));
                if (count($images) === 0) {
                    continue;
                }
                $output->writeln('Classifying audios of user '.$user);
                $this->musicnn->classify($images);
            }

        } catch (\Exception $ex) {
            $output->writeln('<error>Failed to classify audio</error>');
            $output->writeln($ex->getMessage());
            return 1;
        }

        return 0;
    }
}
