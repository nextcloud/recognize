<?php

namespace OCA\Recognize\Service;

use OC\User\NoUserException;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IConfig;

class ClassifyAudioService {
	/**
	 * @var AudioFinderService
	 */
	private $audioFinder;
	/**
	 * @var IRootFolder
	 */
	private $rootFolder;
	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	private $logger;
	/**
	 * @var \OCP\IConfig
	 */
	private $config;
	/**
	 * @var MusicnnClassifier
	 */
	private $musicnn;

	public function __construct(IRootFolder $rootFolder, AudioFinderService $audioFinder, Logger $logger, IConfig $config, MusicnnClassifier $musicnn) {
		$this->rootFolder = $rootFolder;
		$this->audioFinder = $audioFinder;
		$this->logger = $logger;
		$this->config = $config;
		$this->musicnn = $musicnn;
	}

	/**
	 * Run image classifiers
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
		if ($this->config->getAppValue('recognize', 'musicnn.enabled', 'false') !== 'true') {
			return false;
		}
		$this->logger->debug('Collecting audio files of user '.$user);
		$files = $this->audioFinder->findAudioInFolder($user, $this->rootFolder->getUserFolder($user));
		if (count($files) === 0) {
			$this->logger->debug('No audio files found of user '.$user);
			return false;
		}
		if ($n !== 0) {
			$files = array_slice($files, 0, $n);
		}

		if ($this->config->getAppValue('recognize', 'musicnn.enabled', 'false') !== 'false') {
			$this->logger->debug('Classifying audio files of user '.$user. ' using musicnn');
			$this->musicnn->classify($files);
		}
		return true;
	}
}
