<?php

namespace OCA\Recognize\Service;

use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\VideoMapper;
use OCP\IConfig;

class ClassifyVideoService {
	private Logger $logger;
	private IConfig $config;
	private MovinetClassifier $movinet;
	private VideoMapper $videoMapper;

	public function __construct(Logger $logger, IConfig $config, MovinetClassifier $movinet, VideoMapper $videoMapper) {
		$this->logger = $logger;
		$this->config = $config;
		$this->movinet = $movinet;
		$this->videoMapper = $videoMapper;
	}

	/**
	 * Run image classifiersMusicnnClassifier
	 *
	 * @param string $user
	 * @param int $n The number of images to process at max, 0 for no limit (default)
	 * @return bool whether any photos were processed
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\DB\Exception
	 */
	public function run(string $user, int $n = 0): bool {
		if ($this->config->getAppValue('recognize', 'movinet.enabled', 'false') !== 'false') {
			$this->logger->debug('Classifying video files of user '.$user. ' using movinet');

			$videos = $this->videoMapper->findUnprocessedByUserId($user, 'movinet');

			if ($n !== 0) {
				$videos = array_slice($videos, 0, $n);
			}

			if (count($videos) > 0) {
				$this->movinet->classify($videos);
				return true;
			}
		}
		return false;
	}
}
