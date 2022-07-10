<?php

namespace OCA\Recognize\Service;

use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\GeoClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCP\IConfig;
use OCP\Files\IRootFolder;

class ClassifyImagesService {
	private ImagenetClassifier $imagenet;

	private ClusteringFaceClassifier $facenet;

	private ImagesFinderService $imagesFinder;

	private IRootFolder $rootFolder;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	private $logger;

	private IConfig $config;

	private LandmarksClassifier $landmarks;

	private GeoClassifier $geo;

	private FaceClusterAnalyzer $faceClusterAnalyzer;

	public function __construct(ClusteringFaceClassifier $facenet, ImagenetClassifier $imagenet, IRootFolder $rootFolder, ImagesFinderService $imagesFinder, Logger $logger, IConfig $config, LandmarksClassifier $landmarks, GeoClassifier $geo, FaceClusterAnalyzer $faceClusterAnalyzer) {
		$this->facenet = $facenet;
		$this->imagenet = $imagenet;
		$this->rootFolder = $rootFolder;
		$this->imagesFinder = $imagesFinder;
		$this->logger = $logger;
		$this->config = $config;
		$this->landmarks = $landmarks;
		$this->geo = $geo;
		$this->faceClusterAnalyzer = $faceClusterAnalyzer;
	}

	/**
	 * Run image classifiers
	 *
	 * @param string $user
	 * @param int $n The number of images to process at max, 0 for no limit (default)
	 * @return bool whether any photos were processed
	 * @throws \JsonException
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function run(string $user, int $n = 0): bool {
		if ($this->config->getAppValue('recognize', 'faces.enabled', 'false') !== 'true' &&
			$this->config->getAppValue('recognize', 'imagenet.enabled', 'false') !== 'true' &&
			$this->config->getAppValue('recognize', 'geo.enabled', 'false') !== 'true') {
			return false;
		}
		$this->logger->debug('Collecting photos of user '.$user);
		$images = $this->imagesFinder->findImagesInFolder($user, $this->rootFolder->getUserFolder($user));
		if (count($images) === 0) {
			$this->logger->debug('No unclassified photos found by user '.$user);

			if ($this->config->getAppValue('recognize', 'faces.enabled', 'false') !== 'false') {
				$this->logger->debug('Clustering faces in photos by user '.$user);
				$this->faceClusterAnalyzer->findClusters($user);
			}

			return false;
		}
		if ($n !== 0) {
			$images = array_slice($images, 0, $n);
		}

		if ($this->config->getAppValue('recognize', 'imagenet.enabled', 'false') !== 'false') {
			$this->logger->debug('Classifying photos of user '.$user. ' using imagenet');
			$this->imagenet->classify($images);

			if ($this->config->getAppValue('recognize', 'landmarks.enabled', 'false') !== 'false') {
				$this->logger->debug('Classifying photos of user '.$user. ' using landmarks');
				$this->landmarks->classify($images);
			}
		}

		if ($this->config->getAppValue('recognize', 'geo.enabled', 'false') !== 'false') {
			$this->logger->debug('Classifying photos of user '.$user. ' using geo tagger');
			$this->geo->classify($images);
		}

		if ($this->config->getAppValue('recognize', 'faces.enabled', 'false') !== 'false') {
			$this->logger->debug('Classifying photos of user '.$user. ' using facenet');
			$this->facenet->classify($user, $images);
		}
		return true;
	}
}
