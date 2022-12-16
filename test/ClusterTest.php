<?php

use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\FaceClusterAnalyzer;
use Rubix\ML\Kernels\Distance\Euclidean;
use Test\TestCase;

/**
 * @group DB
 */
class ClusterTest extends TestCase {
	public const TEST_USER1 = 'test-user1';
	public const INITIAL_DETECTIONS_PER_CLUSTER = 50;

	private FaceDetectionMapper $faceDetectionMapper;
	private FaceClusterAnalyzer $faceClusterAnalyzer;
	private FaceClusterMapper $faceClusterMapper;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		$backend = new \Test\Util\User\Dummy();
		$backend->createUser(self::TEST_USER1, self::TEST_USER1);
		\OC::$server->get(\OCP\IUserManager::class)->registerBackend($backend);
	}

	public function setUp(): void {
		parent::setUp();
		$this->faceDetectionMapper = \OC::$server->get(FaceDetectionMapper::class);
		$this->faceClusterAnalyzer = \OC::$server->get(FaceClusterAnalyzer::class);
		$this->faceClusterMapper = \OC::$server->get(FaceClusterMapper::class);

		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		foreach ($clusters as $cluster) {
			$this->faceClusterMapper->delete($cluster);
		}
		$detections = $this->faceDetectionMapper->findByUserId(self::TEST_USER1);
		foreach ($detections as $detection) {
			$this->faceDetectionMapper->delete($detection);
		}

		// Generate artificial clusters along just one dimension

		$numOfDetections = self::INITIAL_DETECTIONS_PER_CLUSTER;
		$cluster1Value = 1;
		for ($i = 0; $i < $numOfDetections; $i++) {
			$detection = new FaceDetection();
			$detection->setUserId(self::TEST_USER1);
			$vector = self::getNullVector();
			$vector[0] = $cluster1Value + 0.001 * $i;
			$detection->setVector($vector);
			$detection->setFileId($i);
			$this->faceDetectionMapper->insert($detection);
		}

		$cluster2Value = $cluster1Value + self::INITIAL_DETECTIONS_PER_CLUSTER;
		for ($i = 0; $i < $numOfDetections; $i++) {
			$detection = new FaceDetection();
			$detection->setUserId(self::TEST_USER1);
			$vector = self::getNullVector();
			$vector[0] = $cluster2Value + 0.001 * $i;
			$detection->setVector($vector);
			$detection->setFileId($numOfDetections + $i);
			$this->faceDetectionMapper->insert($detection);
		}
	}

	/**
	 * We check the basic case of 40 detections being correctly assigned to two clusters
	 * @return void
	 * @throws \JsonException
	 * @throws \OCP\DB\Exception
	 */
	public function testClusterAnalyzer() {
		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(2, $clusters);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[1]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);
	}

	/**
	 * We check whether removing a detection from a cluster survives a renewed cluster calculation
	 * @return void
	 * @throws \JsonException
	 * @throws \OCP\DB\Exception
	 */
	public function testClusterThresholds() {
		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(2, $clusters);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[1]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		$detectionToRemove = $detections[0];

		$centroid = FaceClusterAnalyzer::calculateCentroidOfDetections($detections);
		$distance = new Euclidean();
		$distanceValue = $distance->compute($centroid, $detectionToRemove->getVector());
		$detectionToRemove->setThreshold($distanceValue);
		$detectionToRemove->setClusterId(null);
		$this->faceDetectionMapper->update($detectionToRemove);

		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(2, $clusters);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[1]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER - 1, $detections);
	}

	/**
	 * We check whether cluster merging survives a renewed cluster calculation
	 * @return void
	 * @throws \JsonException
	 * @throws \OCP\DB\Exception
	 */
	public function testClusterMerging() {
		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(2, $clusters);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[1]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		// Merge the two clusters
		foreach ($detections as $detection) {
			$detection->setClusterId($clusters[0]->getId());
			$this->faceDetectionMapper->update($detection);
		}

		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(1, $clusters);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER * 2, $detections);
	}

	/**
	 * We merge two clusters and check if a new detection that falls within that new cluster
	 * is correctly added to the overarching cluster
	 *
	 * @return void
	 * @throws \JsonException
	 * @throws \OCP\DB\Exception
	 */
	public function testClusterMerging2() {
		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(2, $clusters);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[1]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		// Merge the two clusters
		foreach ($detections as $detection) {
			$detection->setClusterId($clusters[0]->getId());
			$this->faceDetectionMapper->update($detection);
		}

		$newDetection = new FaceDetection();
		$newDetection->setFileId(500000);
		$nullVector = self::getNullVector();
		$nullVector[0] = 0.8;
		$newDetection->setVector($nullVector);
		$newDetection->setUserId(self::TEST_USER1);
		$this->faceDetectionMapper->insert($newDetection);

		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(1, $clusters);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER * 2 + 1, $detections);
	}

	/**
	 * We merge two originally disparate clusters and check if the newly added detections
	 * will be assigned to their own cluster
	 * @return void
	 * @throws \JsonException
	 * @throws \OCP\DB\Exception
	 */
	public function testClusterMergingWithThirdAdditionalCluster() {
		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(2, $clusters);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[1]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		// Merge the two clusters
		foreach ($detections as $detection) {
			$detection->setClusterId($clusters[0]->getId());
			$this->faceDetectionMapper->update($detection);
		}

		$numOfDetections = self::INITIAL_DETECTIONS_PER_CLUSTER;
		$clusterValue = 3 * self::INITIAL_DETECTIONS_PER_CLUSTER;
		for ($i = 0; $i < $numOfDetections; $i++) {
			$detection = new FaceDetection();
			$detection->setUserId(self::TEST_USER1);
			$vector = self::getNullVector();
			$vector[0] = $clusterValue + 0.001 * $i;
			$detection->setVector($vector);
			$detection->setFileId($i);
			$this->faceDetectionMapper->insert($detection);
		}

		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(2, $clusters);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER * 2, $detections);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[1]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);
	}

	/**
	 * Initially we create three clusters A,B,C and in between two of them (A,B )we add new detections
	 * such that the algorithm now would want to put all A and B into the same cluster.
	 * This shouldn't happen, to keep the clustering stable. If people want to merge the clusters manually that's possible of course.
	 *
	 * @return void
	 * @throws \JsonException
	 * @throws \OCP\DB\Exception
	 */
	public function testClusterTemptClusterMerging() {
		$numOfDetections = self::INITIAL_DETECTIONS_PER_CLUSTER;
		$clusterValue = 1.5; // Above threshold for merging
		for ($i = 0; $i < $numOfDetections; $i++) {
			$detection = new FaceDetection();
			$detection->setUserId(self::TEST_USER1);
			$vector = self::getNullVector();
			$vector[0] = $clusterValue + 0.001 * $i;
			$detection->setVector($vector);
			$detection->setFileId($i);
			$this->faceDetectionMapper->insert($detection);
		}

		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(3, $clusters);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[1]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[2]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);

		$numOfDetections = self::INITIAL_DETECTIONS_PER_CLUSTER;
		$clusterValue = 1.2; // Within threshold for merging with both left and right
		for ($i = 0; $i < $numOfDetections; $i++) {
			$detection = new FaceDetection();
			$detection->setUserId(self::TEST_USER1);
			$vector = self::getNullVector();
			$vector[0] = $clusterValue + 0.001 * $i;
			$detection->setVector($vector);
			$detection->setFileId($i);
			$this->faceDetectionMapper->insert($detection);
		}

		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertGreaterThan(2, $clusters);

		foreach ($clusters as $cluster) {
			$detections = $this->faceDetectionMapper->findByClusterId($cluster->getId());
			self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER, $detections);
		}
	}

	private static function getNullVector() {
		$vector = [];
		for ($i = 0; $i < 128; $i++) {
			$vector[] = 0;
		}
		return $vector;
	}
}
