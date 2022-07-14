<?php

namespace OCA\Recognize\Service;

use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\GeoClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Db\Image;
use OCA\Recognize\Db\ImageMapper;
use OCA\Recognize\Files\ImagesFinder;
use OCP\DB\Exception;
use OCP\IConfig;
use OCP\Files\IRootFolder;

class ClassifyImagesService {
	private ImagenetClassifier $imagenet;

	private ClusteringFaceClassifier $facenet;

	private $logger;

	private IConfig $config;

	private LandmarksClassifier $landmarks;

	private GeoClassifier $geo;

	private FaceClusterAnalyzer $faceClusterAnalyzer;
    /**
     * @var \OCA\Recognize\Db\ImageMapper
     */
    private ImageMapper $imageMapper;

    public function __construct(ClusteringFaceClassifier $facenet, ImagenetClassifier $imagenet, Logger $logger, IConfig $config, LandmarksClassifier $landmarks, GeoClassifier $geo, FaceClusterAnalyzer $faceClusterAnalyzer, ImageMapper $imageMapper) {
		$this->facenet = $facenet;
		$this->imagenet = $imagenet;
		$this->logger = $logger;
		$this->config = $config;
		$this->landmarks = $landmarks;
		$this->geo = $geo;
		$this->faceClusterAnalyzer = $faceClusterAnalyzer;
        $this->imageMapper = $imageMapper;
    }

	/**
	 * Run image classifiers
	 *
	 * @param string $user
	 * @param int $n The number of images to process at max, 0 for no limit (default)
	 * @return bool whether any photos were processed
     * @throws \OCP\DB\Exception
     * @throws \OCP\Files\NotFoundException
     */
	public function run(string $user, int $n = 0): bool {
		if ($this->config->getAppValue('recognize', 'faces.enabled', 'false') !== 'true' &&
			$this->config->getAppValue('recognize', 'imagenet.enabled', 'false') !== 'true' &&
			$this->config->getAppValue('recognize', 'geo.enabled', 'false') !== 'true') {
			return false;
		}

        if ($this->config->getAppValue('recognize', 'imagenet.enabled', 'false') !== 'false') {
            $this->logger->debug('Classifying photos of user '.$user. ' using imagenet');
            $unprocessedImagenetImages = $this->imageMapper->findUnprocessedByUserId($user, 'imagenet');

            if ($n !== 0) {
                $unprocessedImagenetImages = array_slice($unprocessedImagenetImages, 0, $n);
            }
            if (count($unprocessedImagenetImages) > 0) {
                $this->imagenet->classify($unprocessedImagenetImages);
            }

            if ($this->config->getAppValue('recognize', 'landmarks.enabled', 'false') !== 'false') {
                $this->logger->debug('Classifying photos of user '.$user. ' using landmarks');
                $unprocessedLandmarksImages = $this->imageMapper->findUnprocessedByUserId($user, 'landmarks');

                if ($n !== 0) {
                    $unprocessedLandmarksImages = array_slice($unprocessedLandmarksImages, 0, $n);
                }
                if (count($unprocessedLandmarksImages) > 0) {
                    $this->landmarks->classify($unprocessedLandmarksImages);
                }
            }
        }

        if ($this->config->getAppValue('recognize', 'geo.enabled', 'false') !== 'false') {
            $this->logger->debug('Classifying photos of user '.$user. ' using geo tagger');
            $unprocessedGeoImages = $this->imageMapper->findUnprocessedByUserId($user, 'geo');

            if ($n !== 0) {
                $unprocessedGeoImages = array_slice($unprocessedGeoImages, 0, $n);
            }

            if (count($unprocessedGeoImages) > 0) {
                $this->geo->classify($unprocessedGeoImages);
            }
        }

        if ($this->config->getAppValue('recognize', 'faces.enabled', 'false') !== 'false') {
            $this->logger->debug('Classifying photos of user '.$user. ' using facenet');
            $unprocessedFaceImages = $this->imageMapper->findUnprocessedByUserId($user, 'faces');

            if ($n !== 0) {
                $unprocessedFaceImages = array_slice($unprocessedFaceImages, 0, $n);
            }

            if (count($unprocessedFaceImages) > 0) {
                $this->facenet->classify($user, $unprocessedFaceImages);

                try {
                    $this->faceClusterAnalyzer->calculateClusters($user);
                } catch (\JsonException|Exception $e) {
                    $this->logger->warning('Error during face clustering for user '.$user);
                    $this->logger->warning($e->getMessage());
                }
            }
        }

		return (isset($unprocessedImagenetImages) && count($unprocessedImagenetImages) > 0) ||
            (isset($unprocessedLandmarksImages) && count($unprocessedLandmarksImages) > 0)  ||
            (isset($unprocessedGeoImages) && count($unprocessedGeoImages) > 0) ||
            (isset($unprocessedFaceImages) && count($unprocessedFaceImages) > 0)
            ;
	}
}
