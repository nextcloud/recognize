<?php

namespace OCA\Recognize\Controller;

use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\DB\Exception;
use OCP\IRequest;
use OCP\IUserSession;

class UserController extends Controller {

    /**
     * @var \OCA\Recognize\Db\FaceClusterMapper
     */
    private FaceClusterMapper $clusters;
    /**
     * @var \OCP\IUserSession
     */
    private IUserSession $userSession;
    /**
     * @var \OCA\Recognize\Db\FaceDetectionMapper
     */
    private FaceDetectionMapper $faceDetections;
    /**
     * @var \OCA\Recognize\Service\TagManager
     */
    private TagManager $tagManager;

    public function __construct($appName, IRequest $request, FaceClusterMapper $clusters, IUserSession $userSession, FaceDetectionMapper $faceDetections, TagManager $tagManager) {
		parent::__construct($appName, $request);
        $this->clusters = $clusters;
        $this->userSession = $userSession;
        $this->faceDetections = $faceDetections;
        $this->tagManager = $tagManager;
    }

    public function updateCluster(int $id, string $title) {
        if (!$this->userSession->isLoggedIn()) {
            return new JSONResponse([], Http::STATUS_FORBIDDEN);
        }
        /**
         * @var $cluster \OCA\Recognize\Db\FaceCluster
         */
        try {
            $cluster = $this->clusters->find($id);
        }catch(Exception $e) {
            return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
        if ($cluster->getUserId() !== $this->userSession->getUser()->getUID()) {
            return new JSONResponse([], Http::STATUS_FORBIDDEN);
        }
        $oldTitle = $cluster->getTitle();
        $cluster->setTitle($title);
        try {
            $this->clusters->update($cluster);
        } catch (Exception $e) {
            return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
        /**
         * @var $detections \OCA\Recognize\Db\FaceDetection[]
         */
        $detections = $this->faceDetections->findByClusterId($cluster->getId());
        foreach ($detections as $detection) {
            $this->tagManager->assignFace($detection->getFileId(), $cluster->getTitle(), $oldTitle);
        }
        return new JSONResponse($cluster->toArray(), Http::STATUS_OK);
    }
}
