<?php

namespace OCA\Recognize\Command;

use OCA\Recognize\Service\ClassifyService;
use OCA\Recognize\Service\ImagesFinderService;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Classify extends Command {
    /**
     * @var ClassifyService
     */
    private $classifier;
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

    public function __construct(ClassifyService $classifier, IRootFolder $rootFolder, IUserManager $userManager, ImagesFinderService $imagesFinder)
    {
        parent::__construct();
        $this->classifier = $classifier;
        $this->rootFolder = $rootFolder;
        $this->userManager = $userManager;
        $this->imagesFinder = $imagesFinder;
    }

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure()
    {
        // get the number of CPUs
        $ncpu = 1;
        if(is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $ncpu = count($matches[0]);
        }

        $this->setName('recognize:classify')
            ->setDescription('Classify all photos in this installation')
            ->addOption('processors', 'p', InputOption::VALUE_REQUIRED, 'How many processors to use (Default is as many as the machine has to offer)', $ncpu);
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
        $processors = (int) $input->getOption('processors');
        $returns = [];
        try {

            $users = [];
            $this->userManager->callForSeenUsers(function(IUser $user) use (&$users) {
                $users[] = $user->getUID();
            });

            foreach ($users as $user) {
                if (!$user) {
                    $output->writeln('No users left, whose photos could be classified');
                    return 0;
                }
                $images = $this->imagesFinder->findImagesInFolder($this->rootFolder->getUserFolder($user));
                if (count($images) === 0) {
                    continue;
                }
                $output->writeln('Classifying photos of user '.$user);
                $returns[] = $this->classifier->classifyParallel($images, $processors, $output);
            }

        } catch (\Exception $ex) {
            $output->writeln('<error>Failed to classify images</error>');
            $output->writeln($ex->getMessage());
            return 1;
        }

        return array_sum($returns) > 0;
    }
}
