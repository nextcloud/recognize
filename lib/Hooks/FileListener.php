<?php

namespace OCA\Recognize\Hooks;

use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Node;
use Psr\Log\LoggerInterface;

class FileListener implements IEventListener {
	private FaceDetectionMapper $faceDetectionMapper;

	private LoggerInterface $logger;

	public function __construct(FaceDetectionMapper $faceDetectionMapper, LoggerInterface $logger) {
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->logger = $logger;
	}

	public function handle(Event $event) : void {
		$this->postDelete($event->getSubject());
	}

	public function postDelete(Node $node) {
		/**
		 * @var \OCA\Recognize\Db\FaceDetection[] $faceDetections
		 */
		$faceDetections = $this->faceDetectionMapper->findByFileId($node->getId());
		foreach ($faceDetections as $detection) {
			$this->logger->debug('Delete face detection '.$detection->getId());
			$this->faceDetectionMapper->delete($detection);
		}
	}
}
