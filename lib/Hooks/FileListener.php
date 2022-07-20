<?php

namespace OCA\Recognize\Hooks;

use OCA\Recognize\Db\AudioMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\ImageMapper;
use OCA\Recognize\Db\VideoMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class FileListener implements IEventListener {
	private FaceDetectionMapper $faceDetectionMapper;
	private LoggerInterface $logger;
	private IConfig $config;
	private ImageMapper $imageMapper;
	private VideoMapper $videoMapper;
	private AudioMapper $audioMapper;

	public function __construct(FaceDetectionMapper $faceDetectionMapper, LoggerInterface $logger, IConfig $config, ImageMapper $imageMapper, VideoMapper $videoMapper, AudioMapper $audioMapper) {
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->logger = $logger;
		$this->config = $config;
		$this->imageMapper = $imageMapper;
		$this->videoMapper = $videoMapper;
		$this->audioMapper = $audioMapper;
	}

	public function handle(Event $event): void {
		if ($event instanceof NodeDeletedEvent) {
			$this->postDelete($event->getNode());
		}
		if ($event instanceof NodeCreatedEvent) {
			$this->postInsert($event->getNode());
		}
	}

	public function postDelete(Node $node) {
		try {
			/**
			 * @var \OCA\Recognize\Db\FaceDetection[] $faceDetections
			 */
			$faceDetections = $this->faceDetectionMapper->findByFileId($node->getId());
			foreach ($faceDetections as $detection) {
				$this->logger->debug('Delete face detection ' . $detection->getId());
				$this->faceDetectionMapper->delete($detection);
			}
		} catch (NotFoundException $e) {
			$this->logger->debug($e->getMessage(), ['exception' => $e]);
		} catch (Exception|InvalidPathException $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
		}


		try {
			$image = $this->imageMapper->findByFileId($node->getId());
			$this->imageMapper->delete($image);
        } catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
            $this->logger->debug($e->getMessage(), ['exception' => $e]);
        } catch (InvalidPathException|NotFoundException $e) {
            $this->logger->warning($e->getMessage(), ['exception' => $e]);
        }

        try {
			$video = $this->videoMapper->findByFileId($node->getId());
			$this->videoMapper->delete($video);
        } catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
            $this->logger->debug($e->getMessage(), ['exception' => $e]);
        } catch (InvalidPathException|NotFoundException $e) {
            $this->logger->warning($e->getMessage(), ['exception' => $e]);
        }
        try {
			$audio = $this->audioMapper->findByFileId($node->getId());
			$this->videoMapper->delete($audio);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			$this->logger->debug($e->getMessage(), ['exception' => $e]);
		} catch (InvalidPathException|NotFoundException $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
		}
	}

	public function postInsert(Node $node) {
		// Trigger recrawl
		$this->config->setUserValue($node->getOwner()->getUID(), 'recognize', 'crawl.done', 'false');
	}
}
