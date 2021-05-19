<?php

namespace OCA\Recognize\Command;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IUser;
use OCP\SystemTag\ISystemTagObjectMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Classify extends Command {

    /**
     * @var \OCA\Recognize\Service\ClassifyService
     */
    private $classifier;
    /**
     * @var \OCP\Files\IRootFolder
     */
    private $rootFolder;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var \OCP\IUserManager
     */
    private $userManager;
    /**
     * @var \OCP\SystemTag\ISystemTag
     */
    private $recognizedTag;
    /**
     * @var ISystemTagObjectMapper
     */
    private $objectMapper;

    public function __construct(\OCA\Recognize\Service\ClassifyService $classifier, \OCP\Files\IRootFolder $rootFolder, \Psr\Log\LoggerInterface $logger, \OCP\IUserManager $userManager, ISystemTagObjectMapper $objectMapper)
    {
        parent::__construct();
        $this->classifier = $classifier;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
        $this->userManager = $userManager;

        $this->recognizedTag = $this->classifier->getProcessedTag();
        $this->objectMapper = $objectMapper;
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
        $processors = $input->getOption('processors');

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
                $images = $this->findImagesInFolder($this->rootFolder->getUserFolder($user));
                if (count($images) === 0) {
                    continue;
                }

                $output->writeln('Classifying photos of user '.$user);
                $this->classifier->classifyParallel($images, $processors, $output);
            }


        } catch (\Exception $ex) {
            $output->writeln('<error>Failed to classify images</error>');
            $output->writeln($ex->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * @throws \OCP\Files\NotFoundException|\OCP\Files\InvalidPathException
     */
    protected function findImagesInFolder(Folder $folder, &$results = []):array {
        $this->logger->debug('Searching '.$folder->getInternalPath());
        $nodes = $folder->getDirectoryListing();
        foreach ($nodes as $node) {
            if ($node instanceof Folder) {
                $this->findImagesInFolder($node, $results);
            }
            else if ($node instanceof File) {
                if ($this->objectMapper->haveTag([$node->getId()], 'files', $this->recognizedTag->getId())) {
                    continue;
                }
                $mimeType = $node->getMimetype();
                if ($mimeType === 'image/jpeg') {
                    $this->logger->debug('Found '.$node->getPath());
                    $results[] = $node;
                }
            }
        }
        return $results;
    }
}
