<?php

namespace OCA\Recognize\Hooks;

use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Constants;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\IgnoreService;
use OCA\Recognize\Service\QueueService;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\FileInfo;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

class FileListener implements IEventListener {
	private FaceDetectionMapper $faceDetectionMapper;
	private LoggerInterface $logger;
	private QueueService $queue;
	private IgnoreService $ignoreService;

	public function __construct(FaceDetectionMapper $faceDetectionMapper, LoggerInterface $logger, QueueService $queue, IgnoreService $ignoreService) {
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->logger = $logger;
		$this->queue = $queue;
		$this->ignoreService = $ignoreService;
	}

	public function handle(Event $event): void {
		if ($event instanceof BeforeNodeDeletedEvent) {
			$this->postDelete($event->getNode());
		}
		if ($event instanceof NodeCreatedEvent) {
			$this->postInsert($event->getNode());
		}
	}

	public function postDelete(Node $node): void {
		if ($node->getType() === FileInfo::TYPE_FOLDER) {
			try {
				/** @var \OCP\Files\Folder $node */
				foreach ($node->getDirectoryListing() as $child) {
					$this->postDelete($child);
				}
			} catch (NotFoundException $e) {
				$this->logger->debug($e->getMessage(), ['exception' => $e]);
			}
			return;
		}

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

	/**
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function postInsert(Node $node): void {
		$queueFile = new QueueFile();
		$queueFile->setStorageId($node->getMountPoint()->getStorageId());
		$queueFile->setRootId((string) $node->getMountPoint()->getStorageRootId());

		$ignoreMarkers = [];
		if (in_array($node->getMimetype(), Constants::IMAGE_FORMATS)) {
			$ignoreMarkers = array_merge($ignoreMarkers, Constants::IGNORE_MARKERS_IMAGE);
		}
		if (in_array($node->getMimetype(), Constants::VIDEO_FORMATS)) {
			$ignoreMarkers = array_merge($ignoreMarkers, Constants::IGNORE_MARKERS_VIDEO);
		}
		if (in_array($node->getMimetype(), Constants::AUDIO_FORMATS)) {
			$ignoreMarkers = array_merge($ignoreMarkers, Constants::IGNORE_MARKERS_AUDIO);
		}
		if (count($ignoreMarkers) === 0) {
			return;
		}
		$ignoreMarkers = array_merge($ignoreMarkers, Constants::IGNORE_MARKERS_ALL);
		$ignoredDirectories = $this->ignoreService->getIgnoredDirectories($node->getMountPoint()->getNumericStorageId(), $ignoreMarkers);

		try {
			if (in_array($node->getParent()->getId(), $ignoredDirectories)) {
				return;
			}
		} catch (InvalidPathException $e) {
			return;
		} catch (NotFoundException $e) {
			return;
		}

		try {
			$queueFile->setFileId($node->getId());
		} catch (InvalidPathException|NotFoundException $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			return;
		}

		$queueFile->setUpdate(false);
		try {
			if (in_array($node->getMimetype(), Constants::IMAGE_FORMATS)) {
				$this->queue->insertIntoQueue(ImagenetClassifier::MODEL_NAME, $queueFile);
				$this->queue->insertIntoQueue(ClusteringFaceClassifier::MODEL_NAME, $queueFile);
			}
			if (in_array($node->getMimetype(), Constants::VIDEO_FORMATS)) {
				$this->queue->insertIntoQueue(MovinetClassifier::MODEL_NAME, $queueFile);
			}
			if (in_array($node->getMimetype(), Constants::AUDIO_FORMATS)) {
				$this->queue->insertIntoQueue(MusicnnClassifier::MODEL_NAME, $queueFile);
			}
		} catch (Exception $e) {
			$this->logger->error('Failed to add file to queue', ['exception' => $e]);
			return;
		}
	}
}
