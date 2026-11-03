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

		for ($k = 0; $k < 10; $k++) {
			for ($j = 0; $j < 10; $j++) {
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
		$this->assertEquals(10 * 10 * 11, (int)$count); // 1100

		// Run
		$repairStep->run($output);

		// Assert
		$qb = $this->db->getQueryBuilder();
		$count = $qb->select($qb->func()->count('*'))->from('recognize_face_detections')->executeQuery()->fetchOne();
		$this->assertEquals(10 * 10 * 11 - 10 * 10 * 9, (int)$count); // 200
	}

	public function testRepairStepRemovesNearDuplicates() : void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')->executeStatement();

		// Values taken from https://github.com/nextcloud/recognize/issues/1603
		$original = $this->insertDetection('user1', 100, 0.295590, 0.269856, 0.037232, 0.070845, null);
		$reDetected = $this->insertDetection('user1', 100, 0.295480, 0.269174, 0.037122, 0.071395, 1254);
		$reDetectedAgain = $this->insertDetection('user1', 100, 0.295500, 0.269500, 0.037200, 0.071000, null);
		$otherFace = $this->insertDetection('user1', 100, 0.5, 0.3, 0.04, 0.07, null);
		$overlappingOtherFace = $this->insertDetection('user1', 100, 0.52, 0.3, 0.04, 0.07, null);
		$otherUser = $this->insertDetection('user2', 100, 0.295590, 0.269856, 0.037232, 0.070845, null);
		$otherFile = $this->insertDetection('user1', 101, 0.295590, 0.269856, 0.037232, 0.070845, null);

		Server::get(RemoveDuplicateFaceDetections::class)->run($this->createMock(\OCP\Migration\IOutput::class));

		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from('recognize_face_detections')->executeQuery();
		$remainingIds = [];
		while (($id = $result->fetchOne()) !== false) {
			$remainingIds[] = (int)$id;
		}
		$result->closeCursor();
		sort($remainingIds);
		$expected = [$reDetected, $otherFace, $overlappingOtherFace, $otherUser, $otherFile];
		sort($expected);
		$this->assertEquals($expected, $remainingIds);
		$this->assertNotContains($original, $remainingIds, 'The unclustered duplicate should be removed in favour of the clustered one');
		$this->assertNotContains($reDetectedAgain, $remainingIds);
	}

	public function testIsSameFace() : void {
		$box = ['x' => 0.3, 'y' => 0.3, 'width' => 0.1, 'height' => 0.1];
		$this->assertTrue(RemoveDuplicateFaceDetections::isSameFace($box, $box));
		$this->assertTrue(RemoveDuplicateFaceDetections::isSameFace($box, ['x' => 0.301, 'y' => 0.299, 'width' => 0.1, 'height' => 0.101]));
		$this->assertFalse(RemoveDuplicateFaceDetections::isSameFace($box, ['x' => 0.32, 'y' => 0.3, 'width' => 0.1, 'height' => 0.1]));
		$this->assertFalse(RemoveDuplicateFaceDetections::isSameFace($box, ['x' => 0.6, 'y' => 0.6, 'width' => 0.1, 'height' => 0.1]));
		$empty = ['x' => 0.3, 'y' => 0.3, 'width' => 0.0, 'height' => 0.1];
		$this->assertTrue(RemoveDuplicateFaceDetections::isSameFace($empty, $empty));
		$this->assertFalse(RemoveDuplicateFaceDetections::isSameFace($empty, ['x' => 0.31, 'y' => 0.3, 'width' => 0.0, 'height' => 0.1]));
	}

	private function insertDetection(string $userId, int $fileId, float $x, float $y, float $width, float $height, ?int $clusterId): int {
		$face = new \OCA\Recognize\Db\FaceDetection();
		$face->setUserId($userId);
		$face->setFileId($fileId);
		$face->setX($x);
		$face->setY($y);
		$face->setWidth($width);
		$face->setHeight($height);
		$face->setClusterId($clusterId);
		$face->setThreshold(0.5);
		$face->setVector([1, 2, 3, 4, 5, 6, 7, 8, 9, 0]);
		return $this->faceDetectionMapper->insertWithoutDeduplication($face)->getId();
	}
}
