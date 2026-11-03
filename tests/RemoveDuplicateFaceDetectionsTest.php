<?php

use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Migration\RemoveDuplicateFaceDetections;
use OCP\IDBConnection;
use OCP\Server;
use Psr\Log\LoggerInterface;
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

	public function testRepairStepPrefersClusteredOverRejectedOverUnclustered() : void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')->executeStatement();

		// rejected (older) vs. clustered (newer): clustered wins
		$rejected = $this->insertDetection('user1', 200, 0.295590, 0.269856, 0.037232, 0.070845, -1);
		$clustered = $this->insertDetection('user1', 200, 0.295480, 0.269174, 0.037122, 0.071395, 1254);
		// unclustered (older) vs. rejected (newer): rejected wins
		$unclustered2 = $this->insertDetection('user1', 201, 0.295590, 0.269856, 0.037232, 0.070845, null);
		$rejected2 = $this->insertDetection('user1', 201, 0.295480, 0.269174, 0.037122, 0.071395, -1);

		Server::get(RemoveDuplicateFaceDetections::class)->run($this->createMock(\OCP\Migration\IOutput::class));

		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from('recognize_face_detections')->executeQuery();
		$remainingIds = [];
		while (($id = $result->fetchOne()) !== false) {
			$remainingIds[] = (int)$id;
		}
		$result->closeCursor();
		sort($remainingIds);
		$expected = [$clustered, $rejected2];
		sort($expected);
		$this->assertEquals($expected, $remainingIds);
	}

	public function testRepairStepAcrossBatches() : void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')->executeStatement();

		$expected = [];
		for ($fileId = 300; $fileId < 310; $fileId++) {
			// Two different faces per file, each detected twice with slightly shifted boxes
			$expected[] = $this->insertDetection('user1', $fileId, 0.295590, 0.269856, 0.037232, 0.070845, null);
			$expected[] = $this->insertDetection('user1', $fileId, 0.5, 0.3, 0.04, 0.07, null);
			$this->insertDetection('user1', $fileId, 0.295480, 0.269174, 0.037122, 0.071395, null);
			$this->insertDetection('user1', $fileId, 0.5001, 0.3001, 0.04, 0.07, null);
		}

		$repairStep = new RemoveDuplicateFaceDetections($this->db, Server::get(LoggerInterface::class));
		// A batch size that doesn't line up with file boundaries, so batch limits fall in the middle of a file
		$repairStep->setBatchSize(3);
		$repairStep->run($this->createMock(\OCP\Migration\IOutput::class));

		$this->assertEquals($this->sorted($expected), $this->getRemainingIds());
	}

	public function testRepairStepWithFileLargerThanBatch() : void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recognize_face_detections')->executeStatement();

		$expected = [];
		$expected[] = $this->insertDetection('user1', 400, 0.1, 0.1, 0.05, 0.05, null);
		for ($i = 0; $i < 5; $i++) {
			$this->insertDetection('user1', 400, 0.1 + $i * 0.0001, 0.1, 0.05, 0.05, null);
		}
		$expected[] = $this->insertDetection('user1', 401, 0.1, 0.1, 0.05, 0.05, null);
		$this->insertDetection('user1', 401, 0.1001, 0.1, 0.05, 0.05, null);

		$repairStep = new RemoveDuplicateFaceDetections($this->db, Server::get(LoggerInterface::class));
		$repairStep->setBatchSize(2);
		$repairStep->run($this->createMock(\OCP\Migration\IOutput::class));

		$this->assertEquals($this->sorted($expected), $this->getRemainingIds());
	}

	/**
	 * @return list<int>
	 */
	private function getRemainingIds(): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from('recognize_face_detections')->executeQuery();
		$remainingIds = [];
		while (($id = $result->fetchOne()) !== false) {
			$remainingIds[] = (int)$id;
		}
		$result->closeCursor();
		return $this->sorted($remainingIds);
	}

	/**
	 * @param list<int> $ids
	 * @return list<int>
	 */
	private function sorted(array $ids): array {
		sort($ids);
		return $ids;
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
