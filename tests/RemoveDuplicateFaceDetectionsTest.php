<?php

use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Migration\RemoveDuplicateFaceDetections;
use OCP\IDBConnection;
use OCP\Server;
use Test\TestCase;

/**
 * @group DB
 */
class RemoveDuplicateFaceDetectionsTest extends TestCase {
	private IDBConnection $db;
	private FaceDetectionMapper $faceDetectionMapper;

	public function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);
		$this->faceDetectionMapper = Server::get(FaceDetectionMapper::class);

		// Clear
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')->executeStatement();

		// Generate 11 face detections per file (1000 files per user; 100 users)
		// = 1.100.000 face detections out of which 900.000 are superfluous duplicates to be removed
		// After the repair step there should be 200.000 left
		for ($k = 0; $k < 100; $k++) {
			for ($j = 0; $j < 1000; $j++) {
				$user = 'user' . $k;
				$x = rand(0, 100) / 100;
				$y = rand(0, 100) / 100;
				$height = rand(0, 100) / 100;
				$width = rand(0, 100) / 100;
				for ($i = 0; $i < 10; $i++) {
					$face = new \OCA\Recognize\Db\FaceDetection();
					$face->setUserId($user);
					$face->setX($x);
					$face->setY($y);
					$face->setHeight($height);
					$face->setWidth($width);
					$face->setFileId($j);
					$face->setThreshold(0.5);
					$face->setVector([1, 2, 3, 4, 5, 6, 7, 8, 9, 0]);
					$this->faceDetectionMapper->insertWithoutDeduplication($face);
				}
				$face2 = new \OCA\Recognize\Db\FaceDetection();
				$face2->setUserId($user);
				$face2->setX(rand(0, 100) / 100);
				$face2->setY(rand(0, 100) / 100);
				$face2->setHeight(rand(0, 100) / 100);
				$face2->setWidth(rand(0, 100) / 100);
				$face2->setFileId($k * $j);
				$face2->setThreshold(0.5);
				$face2->setVector([1, 2, 3, 4, 5, 6, 7, 8, 9, 0]);
				$this->faceDetectionMapper->insertWithoutDeduplication($face2);
			}
		}
	}

	public function testRepairStep() : void {
		// Prepare
		$repairStep = Server::get(RemoveDuplicateFaceDetections::class);
		$output = $this->createMock(\OCP\Migration\IOutput::class);

		// Check
		$qb = $this->db->getQueryBuilder();
		$count = $qb->select($qb->func()->count('*'))->from('recognize_face_detections')->executeQuery()->fetchOne();
		$this->assertEquals(1100000, (int)$count);

		// Run
		$repairStep->run($output);

		// Assert
		$qb = $this->db->getQueryBuilder();
		$count = $qb->select($qb->func()->count('*'))->from('recognize_face_detections')->executeQuery()->fetchOne();
		$this->assertEquals(200000, (int)$count);
	}
}
