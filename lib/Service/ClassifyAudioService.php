<?php

namespace OCA\Recognize\Service;

use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Db\AudioMapper;
use OCP\IConfig;

class ClassifyAudioService {
	private AudioMapper $audioMapper;

	private $logger;

	private IConfig $config;

	private MusicnnClassifier $musicnn;

	public function __construct(AudioMapper $audioMapper, Logger $logger, IConfig $config, MusicnnClassifier $musicnn) {
		$this->audioMapper = $audioMapper;
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
	 * @throws \OCP\DB\Exception
	 */
	public function run(string $user, int $n = 0): bool {
		if ($this->config->getAppValue('recognize', 'musicnn.enabled', 'false') !== 'false') {
			$audios = $this->audioMapper->findUnprocessedByUserId($user, 'musicnn');
			if (count($audios) === 0) {
				$this->logger->debug('No audio files found of user '.$user);
				return false;
			}
			if ($n !== 0) {
				$audios = array_slice($audios, 0, $n);
			}

			$this->logger->debug('Classifying '.count($audios).' audio files of user '.$user. ' using musicnn');
			$this->musicnn->classify($audios);
			return count($audios) > 0;
		}

		return false;
	}
}
