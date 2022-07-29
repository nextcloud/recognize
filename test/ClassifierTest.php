<?php


use OCA\Recognize\Classifiers\Classifier;
use OCA\Recognize\Db\QueueFile;
use Test\TestCase;

/**
 * @group DB
 */
class ClassifierTest extends TestCase {

	private Classifier $classifier;

	/**
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function setUp(): void
	{
		parent::setUp();
		$this->classifier = \OC::$server->get(Classifier::class);
		$userManager = \OC::$server->getUserManager();
		if (!$userManager->userExists('test')) {
			$userManager->createUser('test', 'test');
		}
		$this->userFolder = \OC::$server->getUserFolder('test');
		$this->testImagenetFile = $this->userFolder->newFile('/alpine.jpg', file_get_contents(__DIR__.'/res/alpine.JPG'));
	}

	public function testClassifier() : void{
		$queueFile = new QueueFile();
		$queueFile->setFileId($this->testImagenetFile->getId());
		$queueFile->setId(1);
		$generator = $this->classifier->classifyFiles('imagenet', [$queueFile], 100);
		$classifications  = iterator_to_array($generator, false);
		$this->assertCount(1, $classifications);
		$this->assertEquals('Alpine', $classifications[0][0]);

	}

}
