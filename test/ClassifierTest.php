<?php

use OCA\Recognize\BackgroundJobs\ClassifyFacesJob;
use OCA\Recognize\BackgroundJobs\ClassifyImagenetJob;
use OCA\Recognize\BackgroundJobs\ClassifyLandmarksJob;
use OCA\Recognize\BackgroundJobs\ClusterFacesJob;
use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\BackgroundJobs\StorageCrawlJob;
use OCA\Recognize\Classifiers\Audio\MusicnnClassifier;
use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Classifiers\Video\MovinetClassifier;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\QueueService;
use OCP\BackgroundJob\IJobList;
use OCP\Files\IRootFolder;
use OCP\SystemTag\ISystemTagObjectMapper;
use Test\TestCase;

/**
 * @group DB
 */
class ClassifierTest extends TestCase {
	public const TEST_USER1 = 'test-user1';

	public const TEST_FILES = ['alpine.jpg' ,'eiffeltower.jpg', 'Rock_Rejam.mp3', 'jumpingjack.gif'];

	private Classifier $classifier;
	private OCP\Files\File $testFile;
	private IRootFolder $rootFolder;
	private \OCP\Files\Folder $userFolder;
	private QueueService $queue;
	private FaceDetectionMapper $faceDetectionMapper;
	private IJobList $jobList;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		$backend = new \Test\Util\User\Dummy();
		$backend->createUser(self::TEST_USER1, self::TEST_USER1);
		\OC::$server->get(\OCP\IUserManager::class)->registerBackend($backend);
	}

	public function setUp(): void {
		parent::setUp();
		$this->classifier = \OC::$server->get(Classifier::class);
		$this->rootFolder = \OC::$server->getRootFolder();
		$this->userFolder = $this->loginAndGetUserFolder(self::TEST_USER1);
		$this->faceDetectionMapper = \OC::$server->get(FaceDetectionMapper::class);
		$this->jobList = \OC::$server->get(IJobList::class);
		$this->queue = \OC::$server->get(QueueService::class);
		foreach (self::TEST_FILES as $filename) {
			try {
				$this->userFolder->get($filename)->delete();
			} catch (\OCP\Files\NotFoundException $e) {
				// noop
			}
		}
		$this->queue->clearQueue(ImagenetClassifier::MODEL_NAME);
		$this->queue->clearQueue(LandmarksClassifier::MODEL_NAME);
		$this->queue->clearQueue(MovinetClassifier::MODEL_NAME);
		$this->queue->clearQueue(MusicnnClassifier::MODEL_NAME);
	}

	public function testSchedulerJob() : void {
		$this->testFile = $this->userFolder->newFile('/alpine.jpg', file_get_contents(__DIR__.'/res/alpine.JPG'));

		/** @var SchedulerJob $scheduler */
		$scheduler = \OC::$server->get(SchedulerJob::class);

		$scheduler->setId(1);
		$scheduler->setLastRun(0);
		$scheduler->execute($this->jobList);

		$storageId = $this->testFile->getMountPoint()->getNumericStorageId();
		$rootId = $this->testFile->getMountPoint()->getStorageRootId();
		self::assertTrue($this->jobList->has(StorageCrawlJob::class, [
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'last_file_id' => 0
		]));

		// cleanup
		$this->jobList->remove(StorageCrawlJob::class, [
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'last_file_id' => 0
		]);
	}

	public function testImagenetPipeline() : void {
		$this->testFile = $this->userFolder->newFile('/alpine.jpg', file_get_contents(__DIR__.'/res/alpine.JPG'));
		\OC::$server->getConfig()->setAppValue('recognize', 'imagenet.enabled', 'true');
		/** @var StorageCrawlJob $scheduler */
		$crawler = \OC::$server->get(StorageCrawlJob::class);
		/** @var IJobList $this->jobList */
		$this->jobList = \OC::$server->get(IJobList::class);
		/** @var QueueService $queue */

		$storageId = $this->testFile->getMountPoint()->getNumericStorageId();
		$rootId = $this->testFile->getMountPoint()->getStorageRootId();

		self::assertCount(0, $this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100), 'imagenet queue should be empty initially');

		$crawler->setArgument([
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'last_file_id' => 0
		]);
		$crawler->setId(1);
		$crawler->setLastRun(0);
		$crawler->execute($this->jobList);

		// clean up
		$this->jobList->remove(StorageCrawlJob::class);

		self::assertTrue($this->jobList->has(ClassifyImagenetJob::class, [
			'storageId' => $storageId,
			'rootId' => $rootId
		]), 'ClassifyImagenetJob should have been scheduled');

		self::assertCount(1,
			$this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100),
			'imagenet queue should contain image');

		list($classifier) = $this->jobList->getJobs(ClassifyImagenetJob::class, 1, 0);
		$classifier->execute($this->jobList);

		/** @var \OCP\SystemTag\ISystemTagObjectMapper $objectMapper */
		$objectMapper = \OC::$server->get(ISystemTagObjectMapper::class);
		/** @var \OCP\SystemTag\ISystemTagManager $tagManager */
		$tagManager = \OC::$server->get(OCP\SystemTag\ISystemTagManager::class);

		self::assertTrue(
			$objectMapper->haveTag(
				(string)$this->testFile->getId(),
				'files',
				$tagManager->getTag('Alpine', true, true)->getId()
			),
			'Correct tag should have been set on image file'
		);
	}

	public function testLandmarksPipeline() : void {
		$this->testFile = $this->userFolder->newFile('/eiffeltower.jpg', file_get_contents(__DIR__.'/res/eiffeltower.jpg'));
		\OC::$server->getConfig()->setAppValue('recognize', 'imagenet.enabled', 'true');
		\OC::$server->getConfig()->setAppValue('recognize', 'landmarks.enabled', 'true');
		/** @var StorageCrawlJob $scheduler */
		$crawler = \OC::$server->get(StorageCrawlJob::class);
		/** @var IJobList $this->jobList */
		$this->jobList = \OC::$server->get(IJobList::class);

		$storageId = $this->testFile->getMountPoint()->getNumericStorageId();
		$rootId = $this->testFile->getMountPoint()->getStorageRootId();

		self::assertCount(0,
			$this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100),
			'imagenet Queue should be empty initially');
		self::assertCount(0,
			$this->queue->getFromQueue(LandmarksClassifier::MODEL_NAME, $storageId, $rootId, 100),
			'landmarks Queue should be empty initially');

		$crawler->setArgument([
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'last_file_id' => 0
		]);
		$crawler->setId(1);
		$crawler->setLastRun(0);
		$crawler->execute($this->jobList);

		// clean up
		$this->jobList->remove(StorageCrawlJob::class);

		self::assertTrue($this->jobList->has(ClassifyImagenetJob::class, [
			'storageId' => $storageId,
			'rootId' => $rootId
		]), 'ClassifyImagenetJob should have been created');

		self::assertCount(0,
			$this->queue->getFromQueue(LandmarksClassifier::MODEL_NAME, $storageId, $rootId, 100),
			'landmarks queue should still be empty after StorageCrawlJob');
		self::assertCount(1,
			$this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100),
			'imagenet queue should contain image');

		list($classifier, ) = $this->jobList->getJobs(ClassifyImagenetJob::class, 1, 0);
		$classifier->execute($this->jobList);

		self::assertTrue($this->jobList->has(ClassifyLandmarksJob::class, [
			'storageId' => $storageId,
			'rootId' => $rootId
		]), 'ClassifyLandmarksJob should have been scheduled');

		self::assertCount(1,
			$this->queue->getFromQueue(LandmarksClassifier::MODEL_NAME, $storageId, $rootId, 100),
			'landmarks queue should contain image');

		list($classifier, ) = $this->jobList->getJobs(ClassifyLandmarksJob::class, 1, 0);
		$classifier->execute($this->jobList);

		/** @var \OCP\SystemTag\ISystemTagObjectMapper $objectMapper */
		$objectMapper = \OC::$server->get(ISystemTagObjectMapper::class);
		/** @var \OCP\SystemTag\ISystemTagManager $tagManager */
		$tagManager = \OC::$server->get(OCP\SystemTag\ISystemTagManager::class);

		self::assertTrue(
			$objectMapper->haveTag(
				(string)$this->testFile->getId(),
				'files',
				$tagManager->getTag('Eiffel Tower', true, true)->getId()
			),
			'Correct Tag should have been set on image file'
		);
	}

	public function testFacesPipeline() : void {
		// Upload FaceID files
		$testFiles = [];
		$personToFiles = [];
		foreach (['Nguyen_Ngoc_Nghia', 'Sao_Mai'] as $person) {
			$personToFiles[$person] = [];
			$it = new RecursiveDirectoryIterator(__DIR__ . '/res/FaceID-550/'.$person, RecursiveDirectoryIterator::SKIP_DOTS);
			$files = new RecursiveIteratorIterator($it,
				RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($files as $file) {
				if (!$file->isDir()) {
					$personToFiles[$person][] = $testFiles[] = $this->userFolder->newFile('/' . $file->getBasename(), file_get_contents($file->getRealPath()));
				}
			}
		}

		\OC::$server->getConfig()->setAppValue('recognize', 'faces.enabled', 'true');

		/** @var StorageCrawlJob $scheduler */
		$crawler = \OC::$server->get(StorageCrawlJob::class);
		/** @var IJobList $this->jobList */
		$this->jobList = \OC::$server->get(IJobList::class);

		$storageId = $testFiles[0]->getMountPoint()->getNumericStorageId();
		$rootId = $testFiles[0]->getMountPoint()->getStorageRootId();

		self::assertCount(0,
			$this->queue->getFromQueue(ClusteringFaceClassifier::MODEL_NAME, $storageId, $rootId, 100),
			'faces Queue should be empty initially');

		$crawler->setArgument([
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'last_file_id' => 0
		]);
		$crawler->setId(1);
		$crawler->setLastRun(0);
		$crawler->execute($this->jobList);

		while (count($jobs = $this->jobList->getJobs(StorageCrawlJob::class, 1, 0)) > 0) {
			list($crawler) = $jobs;
			$crawler->execute($this->jobList);
		}

		self::assertTrue($this->jobList->has(ClassifyFacesJob::class, [
			'storageId' => $storageId,
			'rootId' => $rootId
		]), 'ClassifyFacesJob should have been created');

		self::assertCount(count($testFiles),
			$this->queue->getFromQueue(ClusteringFaceClassifier::MODEL_NAME, $storageId, $rootId, 500),
			'faces queue should contain images');

		list($classifier, ) = $this->jobList->getJobs(ClassifyFacesJob::class, 1, 0);
		$classifier->execute($this->jobList);

		self::assertGreaterThan(10,
			count($this->faceDetectionMapper->findByUserId(self::TEST_USER1)),
			'at least 10 face detections should have been created');

		self::assertTrue($this->jobList->has(ClusterFacesJob::class, [
			'userId' => self::TEST_USER1,
		]), 'ClusterFacesJob should have been scheduled');

		list($clusterer, ) = $this->jobList->getJobs(ClusterFacesJob::class, 1, 0);
		$clusterer->execute($this->jobList);

		/** @var FaceClusterMapper $clusterMapper */
		$clusterMapper = \OC::$server->get(FaceClusterMapper::class);

		/** @var \OCA\Recognize\Db\FaceCluster[] $clusters */
		$clusters = $clusterMapper->findByUserId(self::TEST_USER1);
		self::assertGreaterThan(2, $clusters, 'at least 2 clusters should have been created');

		$personToFileIds = [];
		foreach ($personToFiles as $person => $files) {
			$personToFileIds[$person] = array_map(fn ($file) => $file->getId(), $files);
		}

		foreach ($clusters as $cluster) {
			/** @var \OCA\Recognize\Db\FaceDetection[] $faces */
			$faces = $this->faceDetectionMapper->findByClusterId($cluster->getId());
			$currentClusterPerson = null;
			foreach ($faces as $face) {
				$persons = array_keys(array_filter($personToFileIds, fn ($fileIds) => in_array($face->getFileId(), $fileIds)));
				if (count($persons) > 0) {
					if ($currentClusterPerson === null) {
						$currentClusterPerson = $persons[0];
					} else {
						self::assertEquals($currentClusterPerson, $persons[0], 'All fotos of the same person should be in the same cluster');
					}
				}
			}
		}
	}

	/**
	 * @dataProvider classifierFilesProvider
	 * @return void
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function testClassifier($file, $model, $tag) : void {
		$this->testFile = $this->userFolder->newFile('/'.$file, file_get_contents(__DIR__.'/res/'.$file));
		$queueFile = new QueueFile();
		$queueFile->setFileId($this->testFile->getId());
		$queueFile->setId(1);
		$generator = $this->classifier->classifyFiles($model, [$queueFile], 200);
		$classifications = iterator_to_array($generator, false);
		self::assertCount(1, $classifications);
		self::assertContains($tag, $classifications[0]);
	}

	/**
	 * @return array
	 */
	public function classifierFilesProvider() {
		return [
			['alpine.JPG', ImagenetClassifier::MODEL_NAME, 'Alpine'],
			['eiffeltower.jpg', LandmarksClassifier::MODEL_NAME, 'Eiffel Tower'],
			['Rock_Rejam.mp3', MusicnnClassifier::MODEL_NAME, 'electronic'],
			['jumpingjack.gif', MovinetClassifier::MODEL_NAME, 'jumping jacks']
		];
	}

	private function loginAndGetUserFolder(string $userId) {
		$this->loginAsUser($userId);
		return $this->rootFolder->getUserFolder($userId);
	}
}
