<?php

use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\FaceClusterAnalyzer;
use OCA\Recognize\Service\Logger;
use \Rubix\ML\Kernels\Distance\Euclidean;
use Symfony\Component\Console\Output\OutputInterface;
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

		$this->faceClusterAnalyzer->setMinDatasetSize(30);

		$logger = \OC::$server->get(Logger::class);
		$cliOutput = $this->createMock(OutputInterface::class);
		$cliOutput->method('writeln')
			->willReturnCallback(fn ($msg) => print($msg."\n"));
		$logger->setCliOutput($cliOutput);

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
			$detection->setHeight(0.5);
			$detection->setWidth(0.5);
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
			$detection->setHeight(0.5);
			$detection->setWidth(0.5);
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

		for ($i = 0; $i < self::INITIAL_DETECTIONS_PER_CLUSTER; $i++) {
			$newDetection = new FaceDetection();
			$newDetection->setHeight(0.5);
			$newDetection->setWidth(0.5);
			$newDetection->setFileId(500000 + $i);
			$nullVector = self::getNullVector();
			$nullVector[0] = 1 + 0.001 * $i;
			$newDetection->setVector($nullVector);
			$newDetection->setUserId(self::TEST_USER1);
			$this->faceDetectionMapper->insert($newDetection);
		}

		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(1, $clusters);

		$detections = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		self::assertCount(self::INITIAL_DETECTIONS_PER_CLUSTER * 3, $detections);
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
		$this->faceClusterMapper->delete($clusters[1]);

		$numOfDetections = self::INITIAL_DETECTIONS_PER_CLUSTER;
		$clusterValue = 3 * self::INITIAL_DETECTIONS_PER_CLUSTER;
		for ($i = 0; $i < $numOfDetections; $i++) {
			$detection = new FaceDetection();
			$detection->setUserId(self::TEST_USER1);
			$vector = self::getNullVector();
			$vector[0] = $clusterValue + 0.001 * $i;
			$detection->setVector($vector);
			$detection->setFileId($clusterValue + $i);
			$detection->setHeight(0.5);
			$detection->setWidth(0.5);
			$this->faceDetectionMapper->insert($detection);
		}

		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(2, $clusters);

		$detections1 = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		$detections2 = $this->faceDetectionMapper->findByClusterId($clusters[1]->getId());
		$counts = [count($detections1), count($detections2)];
		self::assertCount(1, array_filter($counts, fn ($count) => $count === self::INITIAL_DETECTIONS_PER_CLUSTER), var_export($counts, true));
		self::assertCount(1, array_filter($counts, fn ($count) => $count === self::INITIAL_DETECTIONS_PER_CLUSTER * 2), var_export($counts, true));
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
			$detection->setHeight(0.5);
			$detection->setWidth(0.5);
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
			$detection->setHeight(0.5);
			$detection->setWidth(0.5);
			$this->faceDetectionMapper->insert($detection);
		}

		$this->faceClusterAnalyzer->calculateClusters(self::TEST_USER1);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $this->faceClusterMapper->findByUserId(self::TEST_USER1);
		self::assertCount(4, $clusters);

		$detections1 = $this->faceDetectionMapper->findByClusterId($clusters[0]->getId());
		$detections2 = $this->faceDetectionMapper->findByClusterId($clusters[1]->getId());
		$detections3 = $this->faceDetectionMapper->findByClusterId($clusters[2]->getId());
		$detections4 = $this->faceDetectionMapper->findByClusterId($clusters[3]->getId());
		$counts = [count($detections1), count($detections2), count($detections3), count($detections4)];

		self::assertCount(4, array_filter($counts, fn ($count) => $count === self::INITIAL_DETECTIONS_PER_CLUSTER), var_export($counts, true));
	}

	private static function getNullVector() {
		$vector = [];
		for ($i = 0; $i < 128; $i++) {
			$vector[] = 0;
		}
		return $vector;
	}
}
