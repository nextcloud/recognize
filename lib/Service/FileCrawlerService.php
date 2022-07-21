<?php

namespace OCA\Recognize\Service;

use OCA\Recognize\Files\AudioToDbFinder;
use OCA\Recognize\Files\FileCrawler;
use OCA\Recognize\Files\ImagesToDbFinder;
use OCA\Recognize\Files\VideoToDbFinder;
use OCP\Files\IRootFolder;

class FileCrawlerService {
	private FileCrawler $fileCrawler;
	private ImagesToDbFinder $imagesFinder;
	private AudioToDbFinder $audioFinder;
	private VideoToDbFinder $videoFinder;
	private IRootFolder $rootFolder;

	public function __construct(FileCrawler $fileCrawler, ImagesToDbFinder $imagesFinder, AudioToDbFinder $audioFinder, VideoToDbFinder $videoFinder, IRootFolder $rootFolder) {
		$this->fileCrawler = $fileCrawler;
		$this->imagesFinder = $imagesFinder;
		$this->audioFinder = $audioFinder;
		$this->videoFinder = $videoFinder;
		$this->rootFolder = $rootFolder;
	}

	/**
	 * @param string $user
	 * @return void
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function crawlForUser(string $user) {
		$folder = $this->rootFolder->getUserFolder($user);
		$this->fileCrawler->crawlFolder($user, $folder, [$this->imagesFinder, $this->audioFinder, $this->videoFinder]);
	}
}
