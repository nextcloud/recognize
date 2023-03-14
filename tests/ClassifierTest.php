<?php

use OCA\Recognize\BackgroundJobs\ClassifyFacesJob;
use OCA\Recognize\BackgroundJobs\ClassifyImagenetJob;
use OCA\Recognize\BackgroundJobs\ClassifyLandmarksJob;
use OCA\Recognize\BackgroundJobs\ClassifyMovinetJob;
use OCA\Recognize\BackgroundJobs\ClassifyMusicnnJob;
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
use OCA\Recognize\Service\Logger;
use OCA\Recognize\Service\QueueService;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\SystemTag\ISystemTagObjectMapper;
use Test\TestCase;

/**
 * @group DB
 */
class ClassifierTest extends TestCase {
	public const TEST_USER1 = 'test-user1';

	public const TEST_FILES = ['alpine.jpg' ,'eiffeltower.jpg', 'Rock_Rejam.mp3', 'jumpingjack.gif', 'test'];
	public const ALL_MODELS = [
		ClusteringFaceClassifier::MODEL_NAME,
		ImagenetClassifier::MODEL_NAME,
		LandmarksClassifier::MODEL_NAME,
		MovinetClassifier::MODEL_NAME,
		MusicnnClassifier::MODEL_NAME,
	];

	private Classifier $classifier;
	private OCP\Files\File $testFile;
	private IRootFolder $rootFolder;
	private Folder $userFolder;
	private QueueService $queue;
	private FaceDetectionMapper $faceDetectionMapper;
	private IJobList $jobList;
	private IConfig $config;

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
		$logger = \OC::$server->get(Logger::class);
		$cliOutput = $this->createMock(Symfony\Component\Console\Output\OutputInterface::class);
		$cliOutput->method('writeln')
			->willReturnCallback(fn ($msg) => print($msg."\n"));
		$logger->setCliOutput($cliOutput);
		$this->jobList = \OC::$server->get(IJobList::class);
		$this->config = \OC::$server->getConfig();
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
		$this->config->setAppValue('recognize', 'imagenet.enabled', 'false');
		$this->config->setAppValue('recognize', 'faces.enabled', 'false');
		$this->config->setAppValue('recognize', 'movinet.enabled', 'false');
		$this->config->setAppValue('recognize', 'musicnn.enabled', 'false');

		$faceClusterAnalyzer = \OC::$server->get(FaceClusterAnalyzer::class);
		$faceClusterAnalyzer->setMinDatasetSize(30);
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
			'override_root' => $this->userFolder->getId(),
			'last_file_id' => 0,
			'models' => self::ALL_MODELS,
		]));

		// cleanup
		$this->jobList->remove(StorageCrawlJob::class, [
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'override_root' => $this->userFolder->getId(),
			'last_file_id' => 0,
			'models' => self::ALL_MODELS,
		]);
	}

	/**
	 * @dataProvider ignoreImageFilesProvider
	 * @return void
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function testFileListener(string $ignoreFileName) : void {
		$this->config->setAppValue('recognize', 'imagenet.enabled', 'true');
		$this->queue->clearQueue(ImagenetClassifier::MODEL_NAME);

		$this->testFile = $this->userFolder->newFile('/alpine.jpg', file_get_contents(__DIR__.'/res/alpine.JPG'));
		$this->userFolder->newFolder('/test/ignore/');
		$ignoreFile = $this->userFolder->newFile('/test/' . $ignoreFileName, '');
		$this->ignoredFile = $this->userFolder->newFile('/test/ignore/alpine-2.jpg', file_get_contents(__DIR__.'/res/alpine.JPG'));

		$storageId = $this->testFile->getMountPoint()->getNumericStorageId();
		$rootId = $this->testFile->getMountPoint()->getStorageRootId();

		self::assertCount(1, $this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100), 'one element should have been added to imagenet queue');

		$this->testFile->delete();

		self::assertCount(0, $this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100), 'entry should have been removed from imagenet queue');

		$ignoreFile->delete();

		self::assertCount(1, $this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100), 'one element should have been added to imagenet queue after deleting ignore file');

		$ignoreFile = $this->userFolder->newFile('/test/' . $ignoreFileName, '');

		self::assertCount(0, $this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100), 'entry should have been removed from imagenet queue after creating ignore file');

		$this->ignoredFile->move($this->userFolder->getPath() . '/alpine-2.jpg');

		self::assertCount(1, $this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100), 'one element should have been added to imagenet queue after moving it out of ignored territory');

		$ignoreFile->move($this->userFolder->getPath() . '/' . $ignoreFileName);

		self::assertCount(0, $this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100), 'entry should have been removed from imagenet queue after moving ignore file');

		$ignoreFile->move($this->userFolder->getPath() . '/test/' . $ignoreFileName);

		self::assertCount(1, $this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100), 'one element should have been added to imagenet queue after moving ignore file');

		$this->ignoredFile->move($this->userFolder->getPath() . '/test/ignore/alpine-2.jpg');

		self::assertCount(0, $this->queue->getFromQueue(ImagenetClassifier::MODEL_NAME, $storageId, $rootId, 100), 'entry should have been removed from imagenet queue after moving it into ignored territory');
	}

	/**
	 * @dataProvider ignoreImageFilesProvider
	 * @return void
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function testClassifyCommand(string $ignoreFileName) : void {
		$this->queue->clearQueue(ImagenetClassifier::MODEL_NAME);

		$this->testFile = $this->userFolder->newFile('/alpine.jpg', file_get_contents(__DIR__.'/res/alpine.JPG'));
		$this->userFolder->newFolder('/test/ignore/');
		$this->userFolder->newFile('/test/' . $ignoreFileName, '');
		$this->ignoredFile = $this->userFolder->newFile('/test/ignore/alpine.jpg', file_get_contents(__DIR__.'/res/alpine.JPG'));

		/** @var \OCA\Recognize\Command\Classify $classifyCommand */
		$classifyCommand = \OC::$server->get(\OCA\Recognize\Command\Classify::class);

		$cliOutput = $this->createMock(Symfony\Component\Console\Output\OutputInterface::class);
		$cliOutput->method('writeln')
			->willReturnCallback(fn ($msg) => print($msg."\n"));
		$cliInput = $this->createMock(Symfony\Component\Console\Input\InputInterface::class);

		$this->config->setAppValue('recognize', 'imagenet.enabled', 'true');
		$classifyCommand->run($cliInput, $cliOutput);

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

		self::assertFalse(
			$objectMapper->haveTag(
				(string)$this->ignoredFile->getId(),
				'files',
				$tagManager->getTag('Alpine', true, true)->getId()
			),
			'Correct tag should not have been set on ignored image file'
		);
	}

	/**
	 * @dataProvider ignoreImageFilesProvider
	 * @return void
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function testImagenetPipeline(string $ignoreFileName) : void {
		$this->testFile = $this->userFolder->newFile('/alpine.jpg', file_get_contents(__DIR__.'/res/alpine.JPG'));
		$this->userFolder->newFolder('/test/ignore/');
		$this->ignoredFile = $this->userFolder->newFile('/test/ignore/alpine.jpg', file_get_contents(__DIR__.'/res/alpine.JPG'));
		$this->userFolder->newFile('/test/' . $ignoreFileName, '');

		$this->config->setAppValue('recognize', 'imagenet.enabled', 'true');

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
			'override_root' => $this->userFolder->getId(),
			'last_file_id' => 0,
			'models' => self::ALL_MODELS,
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
			'imagenet queue should contain exactly one image');

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
		if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true') {
			// landmarks will fail with purejs/WASM mode, sadly, because we use a worse imagenet model in WASM mode
			self::markTestSkipped();
		}
		$this->testFile = $this->userFolder->newFile('/eiffeltower.jpg', file_get_contents(__DIR__.'/res/eiffeltower.jpg'));
		$this->config->setAppValue('recognize', 'imagenet.enabled', 'true');
		$this->config->setAppValue('recognize', 'landmarks.enabled', 'true');
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
			'override_root' => $this->userFolder->getId(),
			'last_file_id' => 0,
			'models' => self::ALL_MODELS,
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

		$this->config->setAppValue('recognize', 'faces.enabled', 'true');

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
			'override_root' => $this->userFolder->getId(),
			'last_file_id' => 0,
			'models' => self::ALL_MODELS,
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
	 * @dataProvider ignoreVideoFilesProvider
	 * @return void
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function testMovinetPipeline(string $ignoreFileName) : void {
		if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true') {
			// Cannot run musicnn with purejs/WASM mode
			self::markTestSkipped();
		}
		$this->testFile = $this->userFolder->newFile('/jumpingjack.gif', file_get_contents(__DIR__.'/res/jumpingjack.gif'));
		$this->userFolder->newFolder('/test/ignore/');
		$this->ignoredFile = $this->userFolder->newFile('/test/ignore/jumpingjack.gif', file_get_contents(__DIR__.'/res/jumpingjack.gif'));
		$this->userFolder->newFile('/test/' . $ignoreFileName, '');
		$this->config->setAppValue('recognize', 'movinet.enabled', 'true');
		/** @var StorageCrawlJob $scheduler */
		$crawler = \OC::$server->get(StorageCrawlJob::class);

		$storageId = $this->testFile->getMountPoint()->getNumericStorageId();
		$rootId = $this->testFile->getMountPoint()->getStorageRootId();

		self::assertCount(0,
			$this->queue->getFromQueue(MovinetClassifier::MODEL_NAME, $storageId, $rootId, 100),
			'movinet queue should be empty initially');

		$crawler->setArgument([
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'override_root' => $this->userFolder->getId(),
			'last_file_id' => 0,
			'models' => self::ALL_MODELS,
		]);
		$crawler->setId(1);
		$crawler->setLastRun(0);
		$crawler->execute($this->jobList);

		while (count($jobs = $this->jobList->getJobs(StorageCrawlJob::class, 1, 0)) > 0) {
			list($crawler) = $jobs;
			$crawler->execute($this->jobList);
		}

		self::assertTrue($this->jobList->has(ClassifyMovinetJob::class, [
			'storageId' => $storageId,
			'rootId' => $rootId
		]), 'ClassifyMovinetJob should have been scheduled');

		self::assertCount(1,
			$this->queue->getFromQueue(MovinetClassifier::MODEL_NAME, $storageId, $rootId, 100),
			'movinet queue should contain exactly one entry');

		list($classifier) = $this->jobList->getJobs(ClassifyMovinetJob::class, 1, 0);
		$classifier->execute($this->jobList);

		/** @var \OCP\SystemTag\ISystemTagObjectMapper $objectMapper */
		$objectMapper = \OC::$server->get(ISystemTagObjectMapper::class);
		/** @var \OCP\SystemTag\ISystemTagManager $tagManager */
		$tagManager = \OC::$server->get(OCP\SystemTag\ISystemTagManager::class);

		self::assertTrue(
			$objectMapper->haveTag(
				(string)$this->testFile->getId(),
				'files',
				$tagManager->getTag('jumping jacks', true, true)->getId()
			),
			'Correct tag should have been set on gif file'
		);
	}

	/**
	 * @dataProvider ignoreAudioFilesProvider
	 * @return void
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function testMusicnnPipeline(string $ignoreFileName) : void {
		if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true') {
			// Cannot run musicnn with purejs/WASM mode
			self::markTestSkipped();
		}
		$this->testFile = $this->userFolder->newFile('/Rock_Rejam.mp3', file_get_contents(__DIR__.'/res/Rock_Rejam.mp3'));
		$this->userFolder->newFolder('/test/ignore/');
		$this->ignoredFile = $this->userFolder->newFile('/test/ignore/Rock_Rejam.mp3', file_get_contents(__DIR__.'/res/Rock_Rejam.mp3'));
		$this->userFolder->newFile('/test/' . $ignoreFileName, '');
		$this->config->setAppValue('recognize', 'musicnn.enabled', 'true');
		/** @var StorageCrawlJob $scheduler */
		$crawler = \OC::$server->get(StorageCrawlJob::class);
		/** @var IJobList $this->jobList */
		$this->jobList = \OC::$server->get(IJobList::class);

		$storageId = $this->testFile->getMountPoint()->getNumericStorageId();
		$rootId = $this->testFile->getMountPoint()->getStorageRootId();

		self::assertCount(0,
			$this->queue->getFromQueue(MusicnnClassifier::MODEL_NAME, $storageId, $rootId, 100),
			'musicnn queue should be empty initially');

		$crawler->setArgument([
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'override_root' => $this->userFolder->getId(),
			'last_file_id' => 0,
			'models' => self::ALL_MODELS,
		]);
		$crawler->setId(1);
		$crawler->setLastRun(0);
		$crawler->execute($this->jobList);

		while (count($jobs = $this->jobList->getJobs(StorageCrawlJob::class, 1, 0)) > 0) {
			list($crawler) = $jobs;
			$crawler->execute($this->jobList);
		}

		self::assertTrue($this->jobList->has(ClassifyMusicnnJob::class, [
			'storageId' => $storageId,
			'rootId' => $rootId
		]), 'ClassifyMovinetJob should have been scheduled');

		self::assertCount(1,
			$this->queue->getFromQueue(MusicnnClassifier::MODEL_NAME, $storageId, $rootId, 100),
			'movinet queue should contain exactly one entry');

		list($classifier) = $this->jobList->getJobs(ClassifyMusicnnJob::class, 1, 0);
		$classifier->execute($this->jobList);

		/** @var \OCP\SystemTag\ISystemTagObjectMapper $objectMapper */
		$objectMapper = \OC::$server->get(ISystemTagObjectMapper::class);
		/** @var \OCP\SystemTag\ISystemTagManager $tagManager */
		$tagManager = \OC::$server->get(OCP\SystemTag\ISystemTagManager::class);

		self::assertTrue(
			$objectMapper->haveTag(
				(string)$this->testFile->getId(),
				'files',
				$tagManager->getTag('electronic', true, true)->getId()
			),
			'Correct tag should have been set on gif file'
		);
	}

	/**
	 * @dataProvider classifierFilesProvider
	 * @return void
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function testClassifier($file, $model, $tag) : void {
		if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true' && in_array($model, ['movinet', 'musicnn'])) {
			// Cannot run musicnn/movinet with purejs/WASM mode
			self::markTestSkipped();
		}
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

	/**
	 * @return array
	 */
	public function ignoreAudioFilesProvider() {
		return [
			['.nomusic'],
			['.nomedia']
		];
	}

	/**
	 * @return array
	 */
	public function ignoreVideoFilesProvider() {
		return [
			['.novideo'],
			['.nomedia']
		];
	}

	/**
	 * @return array
	 */
	public function ignoreImageFilesProvider() {
		return [
			['.noimage'],
			['.nomedia']
		];
	}

	private function loginAndGetUserFolder(string $userId) {
		$this->loginAsUser($userId);
		return $this->rootFolder->getUserFolder($userId);
	}
}
