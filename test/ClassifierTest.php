<?php


use OCA\Recognize\BackgroundJobs\ClassifyImagenetJob;
use OCA\Recognize\BackgroundJobs\SchedulerJob;
use OCA\Recognize\BackgroundJobs\StorageCrawlJob;
use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Db\QueueFile;
use OCP\BackgroundJob\IJobList;
use OCP\Files\IRootFolder;
use Test\TestCase;

/**
 * @group DB
 */
class ClassifierTest extends TestCase {
	public const TEST_USER1 = 'test-user1';

	private Classifier $classifier;
	private OCP\Files\File $testFile;
	private IRootFolder $rootFolder;
	private \OCP\Files\Folder $userFolder;

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
		$this->testFile = $this->userFolder->newFile('/alpine.jpg', file_get_contents(__DIR__.'/res/alpine.JPG'));
	}

	public function testClassifier() : void {
		$queueFile = new QueueFile();
		$queueFile->setFileId($this->testFile->getId());
		$queueFile->setId(1);
		$generator = $this->classifier->classifyFiles('imagenet', [$queueFile], 100);
		$classifications = iterator_to_array($generator, false);
		$this->assertCount(1, $classifications);
		$this->assertEquals('Alpine', $classifications[0][0]);
	}

	public function testSchedulerJob() : void {
		/** @var SchedulerJob $scheduler */
		$scheduler = \OC::$server->get(SchedulerJob::class);
		/** @var IJobList $jobList */
		$jobList = \OC::$server->get(IJobList::class);

		$scheduler->setId(1);
		$scheduler->setLastRun(0);
		$scheduler->execute($jobList);

		$storageId = $this->testFile->getMountPoint()->getNumericStorageId();
		$rootId = $this->testFile->getMountPoint()->getStorageRootId();
		$this->assertTrue($jobList->has(StorageCrawlJob::class, [
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'last_file_id' => 0
		]));
	}

	public function testStorageCrawlJob() : void {
		\OC::$server->getConfig()->setAppValue('recognize', 'imagenet.enabled', 'true');
		/** @var StorageCrawlJob $scheduler */
		$crawler = \OC::$server->get(StorageCrawlJob::class);
		/** @var IJobList $jobList */
		$jobList = \OC::$server->get(IJobList::class);

		$storageId = $this->testFile->getMountPoint()->getNumericStorageId();
		$rootId = $this->testFile->getMountPoint()->getStorageRootId();
		$crawler->setArgument([
			'storage_id' => $storageId,
			'root_id' => $rootId,
			'last_file_id' => 0
		]);
		$crawler->setId(1);
		$crawler->setLastRun(0);
		$crawler->execute($jobList);

		$storageId = $this->testFile->getMountPoint()->getNumericStorageId();
		$rootId = $this->testFile->getMountPoint()->getStorageRootId();
		$this->assertTrue($jobList->has(ClassifyImagenetJob::class, [
			'storageId' => $storageId,
			'rootId' => $rootId
		]));
	}

	private function loginAndGetUserFolder(string $userId) {
		$this->loginAsUser($userId);
		return $this->rootFolder->getUserFolder($userId);
	}
}
