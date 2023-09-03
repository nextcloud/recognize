<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Dav;

use \Sabre\DAVACL\AbstractPrincipalCollection;
use \Sabre\DAVACL\PrincipalBackend\BackendInterface;
use OC\Metadata\IMetadataManager;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\Files\IRootFolder;
use OCP\IPreview;
use OCP\ITagManager;
use OCP\IUserSession;
use Sabre\DAV\Exception\Forbidden;

class RootCollection extends AbstractPrincipalCollection {
	private IUserSession $userSession;
	private FaceClusterMapper $faceClusterMapper;
	private FaceDetectionMapper $faceDetectionMapper;
	private IRootFolder $rootFolder;
	private ITagManager $tagManager;
	private IMetadataManager $metadataManager;
	private IPreview $previewManager;

	public function __construct(BackendInterface $principalBackend, IUserSession $userSession, FaceClusterMapper $faceClusterMapper, FaceDetectionMapper $faceDetectionMapper, IRootFolder $rootFolder, ITagManager $tagManager, IMetadataManager $metadataManager, IPreview $previewManager) {
		parent::__construct($principalBackend, 'principals/users');
		$this->userSession = $userSession;
		$this->faceClusterMapper = $faceClusterMapper;
		$this->faceDetectionMapper = $faceDetectionMapper;
		$this->rootFolder = $rootFolder;
		$this->tagManager = $tagManager;
		$this->metadataManager = $metadataManager;
		$this->previewManager = $previewManager;
	}

	public function getChildForPrincipal(array $principalInfo): RecognizeHome {
		[, $name] = \Sabre\Uri\split($principalInfo['uri']);
		$user = $this->userSession->getUser();
		if (is_null($user) || $name !== $user->getUID()) {
			throw new Forbidden();
		}
		return new RecognizeHome($principalInfo, $this->faceClusterMapper, $user, $this->faceDetectionMapper, $this->rootFolder, $this->tagManager, $this->metadataManager, $this->previewManager);
	}

	public function getName() {
		return 'recognize';
	}
}
