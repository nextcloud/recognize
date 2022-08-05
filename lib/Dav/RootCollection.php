<?php

namespace OCA\Recognize\Dav;

use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\Files\IRootFolder;
use OCP\IUserSession;
use Sabre\DAV\Exception\Forbidden;
use \Sabre\DAVACL\AbstractPrincipalCollection;
use \Sabre\DAVACL\PrincipalBackend\BackendInterface;

class RootCollection extends AbstractPrincipalCollection {
	private IUserSession $userSession;
	private FaceClusterMapper $faceClusterMapper;
	private FaceDetectionMapper $faceDetectionMapper;
	private IRootFolder $rootFolder;

	public function __construct(BackendInterface $principalBackend, IUserSession $userSession, FaceClusterMapper $faceClusterMapper, FaceDetectionMapper $faceDetectionMapper, IRootFolder $rootFolder) {
		parent::__construct($principalBackend, 'principals/users');
		$this->userSession = $userSession;
		$this->faceClusterMapper = $faceClusterMapper;
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->rootFolder = $rootFolder;
	}

	public function getChildForPrincipal(array $principalInfo): RecognizeHome {
		[, $name] = \Sabre\Uri\split($principalInfo['uri']);
		$user = $this->userSession->getUser();
		if (is_null($user) || $name !== $user->getUID()) {
			throw new Forbidden();
		}
		return new RecognizeHome($principalInfo, $this->faceClusterMapper, $user, $this->faceDetectionMapper, $this->rootFolder);
	}

	public function getName() {
		return 'recognize';
	}
}
