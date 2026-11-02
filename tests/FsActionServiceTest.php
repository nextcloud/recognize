<?php

use OC\Files\SetupManager;
use OCA\Recognize\BackgroundJobs\ClusterFacesJob;
use OCA\Recognize\BackgroundJobs\ProcessFsActionsJob;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Db\FsAccessUpdate;
use OCA\Recognize\Db\FsActionMapper;
use OCA\Recognize\Db\FsCreation;
use OCA\Recognize\Db\FsDeletion;
use OCA\Recognize\Db\FsMove;
use OCA\Recognize\Service\FsActionService;
use OCP\BackgroundJob\IJobList;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\Server;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Test\TestCase;

/**
 * Checks that face detections follow file access when files are moved or folders are shared
 *
 * @group DB
 */
class FsActionServiceTest extends TestCase {
	public const OWNER = 'fsaction-owner';
	public const RECIPIENT = 'fsaction-recipient';

	private IRootFolder $rootFolder;
	private Folder $userFolder;
	private FaceDetectionMapper $faceDetectionMapper;
	private FsActionMapper $fsActionMapper;
	private IJobList $jobList;
	private IShareManager $shareManager;
	private IDBConnection $db;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		$backend = new \Test\Util\User\Dummy();
		$backend->createUser(self::OWNER, self::OWNER);
		$backend->createUser(self::RECIPIENT, self::RECIPIENT);
		Server::get(\OCP\IUserManager::class)->registerBackend($backend);

		// The file listener relies on node events, which are emitted by hooks that
		// TestCase::tearDownAfterClass() clears after every test class
		\OC_Hook::clear('OC_Filesystem');
		Server::get(\OC\Files\Node\HookConnector::class)->viewToNode();
	}

	public function setUp(): void {
		parent::setUp();
		$this->rootFolder = Server::get(IRootFolder::class);
		$this->faceDetectionMapper = Server::get(FaceDetectionMapper::class);
		$this->fsActionMapper = Server::get(FsActionMapper::class);
		$this->jobList = Server::get(IJobList::class);
		$this->shareManager = Server::get(IShareManager::class);
		$this->db = Server::get(IDBConnection::class);

		$this->loginAsUser(self::OWNER);
		$this->userFolder = $this->rootFolder->getUserFolder(self::OWNER);

		foreach ($this->shareManager->getSharesBy(self::OWNER, IShare::TYPE_USER, null, true, -1) as $share) {
			$this->shareManager->deleteShare($share);
		}
		foreach ($this->userFolder->getDirectoryListing() as $node) {
			$node->delete();
		}
		\OCP\Server::get(\OCA\Files_Trashbin\Trashbin::class)->deleteAll();
		$this->refreshMounts(self::RECIPIENT);

		foreach (['recognize_face_detections', FsMove::$tableName, FsAccessUpdate::$tableName, FsCreation::$tableName, FsDeletion::$tableName] as $table) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($table)->executeStatement();
		}
		$this->jobList->remove(ProcessFsActionsJob::class);
		$this->jobList->remove(ClusterFacesJob::class);
	}

	public function testMoveQueuesExactlyOneAction(): void {
		$file = $this->userFolder->newFile('photo.jpg', 'content');
		$this->runFsActionJobs();

		$file->move($this->userFolder->getPath() . '/photo-1.jpg');
		self::assertEquals(1, $this->fsActionMapper->count(FsMove::class), 'moving a file should queue a move action');
		self::assertTrue($this->jobList->has(ProcessFsActionsJob::class, ['type' => FsMove::class]), 'moving a file should schedule the job processing move actions');

		// A second move before the job ran replaces the pending action instead of failing or piling up
		$file->move($this->userFolder->getPath() . '/photo-2.jpg');
		self::assertEquals(1, $this->fsActionMapper->count(FsMove::class), 'moving a file again should replace the pending move action');

		$this->runFsActionJobs();
		self::assertEquals(0, $this->fsActionMapper->count(FsMove::class), 'the job should process all move actions');
		self::assertFalse($this->jobList->has(ProcessFsActionsJob::class, ['type' => FsMove::class]), 'the job should remove itself once all move actions are processed');
	}

	public function testMoveDuringProcessingIsNotLost(): void {
		$file = $this->userFolder->newFile('photo.jpg', 'content');
		$this->runFsActionJobs();

		$file->move($this->userFolder->getPath() . '/photo-1.jpg');
		// The job has fetched the pending action, but not processed it yet, when the file is moved again
		$actions = $this->fsActionMapper->find(FsMove::class);
		$file->move($this->userFolder->getPath() . '/photo-2.jpg');
		Server::get(FsActionService::class)->processActions($actions);
		self::assertEquals(1, $this->fsActionMapper->count(FsMove::class), 'a move made while the job was processing should stay queued');
	}

	public function testMoveFileIntoAndOutOfSharedFolder(): void {
		$shared = $this->userFolder->newFolder('shared');
		$this->shareWithRecipient($shared);
		$file = $this->userFolder->newFile('photo.jpg', 'content');
		$this->addDetection($file->getId(), self::OWNER);
		$this->runFsActionJobs();
		self::assertEquals([self::OWNER], $this->getUsersWithDetections($file->getId()));

		$file->move($shared->getPath() . '/photo.jpg');
		$this->runFsActionJobs();
		self::assertEquals([self::OWNER, self::RECIPIENT], $this->getUsersWithDetections($file->getId()), 'recipient should get detections for a file moved into a folder shared with them');
		self::assertTrue($this->jobList->has(ClusterFacesJob::class, ['userId' => self::RECIPIENT]), 'clustering should be scheduled for the recipient');

		$file->move($this->userFolder->getPath() . '/photo.jpg');
		$this->runFsActionJobs();
		self::assertEquals([self::OWNER], $this->getUsersWithDetections($file->getId()), 'recipient should lose detections for a file moved out of a folder shared with them');
	}

	public function testMoveFolderWithSubfoldersIntoSharedFolder(): void {
		$shared = $this->userFolder->newFolder('shared');
		$this->shareWithRecipient($shared);
		$album = $this->userFolder->newFolder('album');
		$files = [
			$album->newFile('a.jpg', 'content'),
			$album->newFolder('sub')->newFile('b.jpg', 'content'),
			$album->get('sub')->newFolder('deeper')->newFile('c.jpg', 'content'),
		];
		foreach ($files as $file) {
			$this->addDetection($file->getId(), self::OWNER);
		}
		$this->runFsActionJobs();

		$album->move($shared->getPath() . '/album');
		$this->runFsActionJobs();
		foreach ($files as $file) {
			self::assertEquals([self::OWNER, self::RECIPIENT], $this->getUsersWithDetections($file->getId()), 'recipient should get detections for ' . $file->getInternalPath());
		}
	}

	public function testMoveFolderKeepsDetectionsOfDirectlySharedSubfolder(): void {
		$album = $this->userFolder->newFolder('album');
		$sub = $album->newFolder('sub');
		$file = $sub->newFile('b.jpg', 'content');
		$this->shareWithRecipient($sub);
		$this->addDetection($file->getId(), self::OWNER);
		$this->addDetection($file->getId(), self::RECIPIENT);
		$this->runFsActionJobs();

		// The moved folder itself is not shared with the recipient, but the subfolder still is
		$album->move($this->userFolder->getPath() . '/album-renamed');
		$this->runFsActionJobs();
		self::assertEquals([self::OWNER, self::RECIPIENT], $this->getUsersWithDetections($file->getId()), 'recipient should keep detections for a file in a subfolder shared with them directly');
	}

	public function testSharingAndUnsharingFolderUpdatesDetectionsInSubfolders(): void {
		$folder = $this->userFolder->newFolder('toshare');
		$files = [
			$folder->newFile('a.jpg', 'content'),
			$folder->newFolder('sub')->newFile('b.jpg', 'content'),
		];
		foreach ($files as $file) {
			$this->addDetection($file->getId(), self::OWNER);
		}
		$this->runFsActionJobs();

		$share = $this->shareWithRecipient($folder);
		$this->runFsActionJobs();
		foreach ($files as $file) {
			self::assertEquals([self::OWNER, self::RECIPIENT], $this->getUsersWithDetections($file->getId()), 'recipient should get detections after sharing for ' . $file->getInternalPath());
		}

		$this->shareManager->deleteShare($share);
		$this->refreshMounts(self::RECIPIENT);
		$this->runFsActionJobs();
		foreach ($files as $file) {
			self::assertEquals([self::OWNER], $this->getUsersWithDetections($file->getId()), 'recipient should lose detections after unsharing for ' . $file->getInternalPath());
		}
	}

	private function shareWithRecipient(Folder $folder): IShare {
		$share = $this->shareManager->newShare();
		$share->setNode($folder)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith(self::RECIPIENT)
			->setSharedBy(self::OWNER)
			->setPermissions(Constants::PERMISSION_ALL);
		$share = $this->shareManager->createShare($share);
		$this->refreshMounts(self::RECIPIENT);
		return $share;
	}

	/**
	 * Sets up the user's file system from scratch, so that the mount cache (and the listeners for
	 * mounts being added or removed) sees shares created or deleted in the meantime
	 */
	private function refreshMounts(string $userId): void {
		Server::get(SetupManager::class)->tearDown();
		$this->rootFolder->getUserFolder($userId);
		$this->loginAsUser(self::OWNER);
		$this->userFolder = $this->rootFolder->getUserFolder(self::OWNER);
	}

	private function addDetection(int $fileId, string $userId): void {
		$detection = new FaceDetection();
		$detection->setFileId($fileId);
		$detection->setUserId($userId);
		$detection->setX(0.1);
		$detection->setY(0.2);
		$detection->setHeight(0.3);
		$detection->setWidth(0.4);
		$detection->setThreshold(0.5);
		$detection->setVector([1, 2, 3, 4, 5, 6, 7, 8, 9, 0]);
		$this->faceDetectionMapper->insertWithoutDeduplication($detection);
	}

	/**
	 * @return list<string>
	 */
	private function getUsersWithDetections(int $fileId): array {
		$userIds = array_values(array_unique(array_map(static fn (FaceDetection $detection) => $detection->getUserId(), $this->faceDetectionMapper->findByFileId($fileId))));
		sort($userIds);
		return $userIds;
	}

	private function runFsActionJobs(): void {
		$runs = 0;
		while ($job = $this->jobList->getNext(jobClasses: [ProcessFsActionsJob::class])) {
			// A job that fails to process its actions never removes itself
			self::assertLessThan(100, $runs++, 'fs action jobs should finish');
			$this->jobList->resetBackgroundJob($job);
			$job->start($this->jobList);
			$this->jobList->resetBackgroundJob($job);
		}
	}
}
