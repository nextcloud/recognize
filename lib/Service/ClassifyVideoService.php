<?php

namespace OCA\Recognize\Service;

use OC\User\NoUserException;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Files\VideoFinder;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IConfig;

class ClassifyVideoService {
	private VideoFinder $videoFinder;

	private IRootFolder $rootFolder;
	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	private $logger;
	/**
	 * @var \OCP\IConfig
	 */
	private IConfig $config;
	/**
	 * @var MovinetClassifier
	 */
	private MovinetClassifier $movinet;

	public function __construct(IRootFolder $rootFolder, VideoFinder $videoFinder, Logger $logger, IConfig $config, MovinetClassifier $movinet) {
		$this->rootFolder = $rootFolder;
		$this->videoFinder = $videoFinder;
		$this->logger = $logger;
		$this->config = $config;
		$this->movinet = $movinet;
	}

	/**
	 * Run image classifiersMusicnnClassifier
	 *
	 * @param string $user
	 * @param int $n The number of images to process at max, 0 for no limit (default)
	 * @return bool whether any photos were processed
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws NotPermittedException
	 * @throws NoUserException
	 */
	public function run(string $user, int $n = 0): bool {
		if ($this->config->getAppValue('recognize', 'movinet.enabled', 'false') !== 'true') {
			return false;
		}
		$this->logger->debug('Collecting video files of user '.$user);
		$files = $this->videoFinder->findFilesInFolder($user, $this->rootFolder->getUserFolder($user));
		if (count($files) === 0) {
			$this->logger->debug('No video files found of user '.$user);
			return false;
		}
		if ($n !== 0) {
			$files = array_slice($files, 0, $n);
		}

		if ($this->config->getAppValue('recognize', 'movinet.enabled', 'false') !== 'false') {
			$this->logger->debug('Classifying video files of user '.$user. ' using movinet');
			$this->movinet->classify($files);
		}
		return true;
	}
}
