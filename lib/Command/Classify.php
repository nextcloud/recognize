<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Command;

use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Exception\Exception;
use OCA\Recognize\Service\ClassificationQueueBuilder;
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\SettingsService;
use OCA\Recognize\Service\StorageService;
use OCA\Recognize\Service\TagManager;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class Classify extends Command {
	/** @var array<string, Classifier>  */
	private array $classifiers = [];

	public function __construct(
		private StorageService $storageService,
		private TagManager $tagManager,
		private Logger $logger,
		ImagenetClassifier $imagenet,
		ClusteringFaceClassifier $faces,
		MovinetClassifier $movinet,
		MusicnnClassifier $musicnn,
		private IUserMountCache $userMountCache,
		private SettingsService $settings,
		private ClearBackgroundJobs $clearBackgroundJobs,
		private ClassificationQueueBuilder $queueBuilder,
	) {
		parent::__construct();
		$this->classifiers[ImagenetClassifier::MODEL_NAME] = $imagenet;
		$this->classifiers[ClusteringFaceClassifier::MODEL_NAME] = $faces;
		$this->classifiers[MusicnnClassifier::MODEL_NAME] = $musicnn;
		$this->classifiers[MovinetClassifier::MODEL_NAME] = $movinet;
		// Landmarks are currently processed out of band in a background job, because imagenet schedules it directly
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:classify')
			->setDescription('Classify all files with the current settings in one go on the terminal (will likely take a long time; doesn\'t work with TaskProcessing mode)')
			->addOption('retry', null, InputOption::VALUE_NONE, "Only classify untagged images");
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws ExceptionInterface
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->logger->setCliOutput($output);

		// pop "retry" flag from parameters passed to clear background jobs
		$clearBackgroundJobs = new ArrayInput([]);
		$this->clearBackgroundJobs->run($clearBackgroundJobs, $output);

		$models = array_values(array_filter([
			ClusteringFaceClassifier::MODEL_NAME,
			ImagenetClassifier::MODEL_NAME,
			MovinetClassifier::MODEL_NAME,
			MusicnnClassifier::MODEL_NAME,
		], fn ($modelName) => $this->settings->getSetting($modelName . '.enabled') === 'true'));

		foreach ($this->storageService->getMounts() as $mount) {
			$this->logger->info('Processing storage ' . $mount['storage_id'] . ' with root ID ' . $mount['override_root']);

			// Setup Filesystem for a users that can access this mount
			$mounts = array_values(array_filter($this->userMountCache->getMountsForStorageId($mount['storage_id']), function (ICachedMountInfo $mountInfo) use ($mount) {
				return $mountInfo->getRootId() === $mount['root_id'];
			}));
			if (count($mounts) > 0) {
				\OC_Util::setupFS($mounts[0]->getUser()->getUID());
			}

			$lastFileId = 0;
			do {
				$files = iterator_to_array(
					$this->storageService->getFilesInMount($mount['storage_id'], $mount['override_root'], $models, $lastFileId),
					false
				);
				$i = count($files);
				// Fetch the processed tag state of the whole page with a single query
				$fileIds = array_map(static fn (array $file): string => (string)$file['fileid'], $files);
				$processedFileIds = $input->getOption('retry') && count($fileIds) > 0
					? array_fill_keys($this->tagManager->getProcessedFileIds($fileIds), true)
					: [];
				$queues = $this->queueBuilder->build($files, $processedFileIds, $models, $mount['storage_id'], $mount['root_id']);
				if ($i > 0) {
					$lastFileId = $files[$i - 1]['fileid'];
				}

				foreach ($this->classifiers as $modelName => $classifier) {
					if (!in_array($modelName, $models)) {
						continue;
					}
					try {
						$classifier->setMaxExecutionTime(0);
						$classifier->classify($queues[$modelName]);
					} catch (Exception $e) {
						$this->logger->warning($e->getMessage(), ['exception' => $e]);
					} catch (\RuntimeException $e) {
						$this->logger->info('Temporary error while running ' . $modelName . 'classifier', ['exception' => $e]);
					} catch (\ErrorException $e) {
						$this->logger->info('Error while running ' . $modelName . 'classifier', ['exception' => $e]);
					}
				}
			} while ($i > 0);
			\OC_Util::tearDownFS();
		}
		return 0;
	}
}
