<?php

namespace OCA\Recognize\Hooks;

use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\QueueService;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

class FileListener implements IEventListener {
	private FaceDetectionMapper $faceDetectionMapper;
	private LoggerInterface $logger;
	private QueueService $queue;

	public function __construct(FaceDetectionMapper $faceDetectionMapper, LoggerInterface $logger, QueueService $queue) {
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->logger = $logger;
		$this->queue = $queue;
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
		// Try Deleting possibly existing face detections
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

		// Try removing file from possibly existing queue entries
		try {
			$this->queue->removeFileFromAllQueues($node->getId());
		} catch (NotFoundException $e) {
			$this->logger->debug($e->getMessage(), ['exception' => $e]);
		} catch (Exception|InvalidPathException $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
		}
	}

	public function postInsert(Node $node) {
		$queueFile = new QueueFile();
		$queueFile->setStorageId($node->getMountPoint()->getNumericStorageId());
		$queueFile->setRootId($node->getMountPoint()->getStorageRootId());

		try {
			$queueFile->setFileId($node->getId());
		} catch (InvalidPathException|NotFoundException $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			return;
		}

		$queueFile->setUpdate(false);
		try {
			switch ($node->getMimePart()) {
				case 'image':
					$this->queue->insertIntoQueue(ImagenetClassifier::MODEL_NAME, $queueFile);
					$this->queue->insertIntoQueue(ClusteringFaceClassifier::MODEL_NAME, $queueFile);
					break;
				case 'video':
					$this->queue->insertIntoQueue(MovinetClassifier::MODEL_NAME, $queueFile);
					break;
				case 'audio':
					$this->queue->insertIntoQueue(MusicnnClassifier::MODEL_NAME, $queueFile);
			}
		} catch (Exception $e) {
			$this->logger->error('Failed to add file to queue', ['exception' => $e]);
			return;
		}
	}
}
