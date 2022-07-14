<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Classifiers\Images;

use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\Image;
use OCA\Recognize\Db\ImageMapper;
use OCA\Recognize\Service\FaceClusterAnalyzer;
use OCA\Recognize\Service\Logger;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ClusteringFaceClassifier  extends Classifier {
	public const IMAGE_TIMEOUT = 120; // seconds
	public const IMAGE_PUREJS_TIMEOUT = 360; // seconds
	public const MIN_FACE_RECOGNITION_SCORE = 0.5;
    public const MODEL_NAME = 'facevectors';

	private LoggerInterface $logger;

	private IConfig $config;

	private FaceDetectionMapper $faceDetections;

    private IRootFolder $rootFolder;

    private ImageMapper $imageMapper;

    public function __construct(Logger $logger, IConfig $config, FaceDetectionMapper $faceDetections, IRootFolder $rootFolder, ImageMapper $imageMapper) {
        parent::__construct($logger, $config);
		$this->logger = $logger;
		$this->config = $config;
		$this->faceDetections = $faceDetections;
        $this->rootFolder = $rootFolder;
        $this->imageMapper = $imageMapper;
    }

    /**
     * @param string $user
     * @param \OCA\Recognize\Db\Image[] $images
     * @return void
     * @throws \OCP\DB\Exception
     * @throws \OCP\Files\NotFoundException
     */
    public function classify(string $user, array $images): void {
        $paths = array_map(static function (Image $image) {
            $file = $this->rootFolder->getById($image->getFileId())[0];
            return $file->getStorage()->getLocalFile($file->getInternalPath());
        }, $images);
        if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true') {
            $timeout = count($paths) * self::IMAGE_PUREJS_TIMEOUT;
        } else {
            $timeout = count($paths) * self::IMAGE_TIMEOUT;
        }
        $classifierProcess = $this->classifyFiles(self::MODEL_NAME, $paths, $timeout);

        foreach ($classifierProcess as $i => $faces) {
            // remove exisiting detections
            foreach ($this->faceDetections->findByFileId($images[$i]->getFileId()) as $existingFaceDetection) {
                try {
                    $this->faceDetections->delete($existingFaceDetection);
                } catch (Exception $e) {
                    $this->logger->debug('Could not delete existing face detection');
                }
            }

            foreach ($faces as $face) {
                if ($face['score'] < self::MIN_FACE_RECOGNITION_SCORE) {
                    continue;
                }
                $faceDetection = new FaceDetection();
                $faceDetection->setX($face['x']);
                $faceDetection->setY($face['y']);
                $faceDetection->setWidth($face['width']);
                $faceDetection->setHeight($face['height']);
                $faceDetection->setVector($face['vector']);
                $faceDetection->setFileId($images[$i]->getFileId());
                $faceDetection->setUserId($user);
                $this->faceDetections->insert($faceDetection);
            }

            // Update processed status
            $images[$i]->setProcessedFaces(true);
            try {
                $this->imageMapper->update($images[$i]);
            } catch (Exception $e) {
                $this->logger->warning($e->getMessage());
            }
        }
    }
}
