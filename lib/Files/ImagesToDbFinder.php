<?php

namespace OCA\Recognize\Files;

use OCA\Recognize\Db\Image;
use OCA\Recognize\Db\ImageMapper;
use OCA\Recognize\Service\Logger;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;

class ImagesToDbFinder extends ImagesFinder {
	private Logger $logger;
	private ImageMapper $imageMapper;

	public function __construct(Logger $logger, ImageMapper $imageMapper) {
		parent::__construct($logger);
		$this->logger = $logger;
		$this->imageMapper = $imageMapper;
	}


	/**
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function foundFile(string $user, File $node) :void {
		try {
			$this->imageMapper->findByFileId($node->getId());
		} catch (DoesNotExistException $e) {
			$image = new Image();
			$image->setUserId($user);
			$image->setFileId($node->getId());
			$image->setProcessedFaces(false);
			$image->setProcessedImagenet(false);
			$image->setProcessedLandmarks(false);
			$image->setProcessedGeo(false);
			$this->imageMapper->insert($image);
		}
	}
}
