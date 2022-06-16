<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Settings;

use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class UserSettings implements ISettings {

    private IInitialState $initialState;

    private IConfig $config;

    private FaceDetectionMapper $faceDetections;

    private FaceClusterMapper $faceClusters;
    /**
     * @var \OCP\IUserSession
     */
    private IUserSession $userSession;

    public function __construct(IInitialState $initialState, IConfig $config, FaceDetectionMapper $faceDetections, FaceClusterMapper $faceClusters, IUserSession $userSession)
    {
        $this->initialState = $initialState;
        $this->config = $config;
        $this->faceDetections = $faceDetections;
        $this->faceClusters = $faceClusters;
        $this->userSession = $userSession;
    }


    /**
     * @return TemplateResponse
     */
    public function getForm(): TemplateResponse {
        $faceClusters = $this->faceClusters->findByUserId($this->userSession->getUser()->getUID());
        $faceClusters = array_map(function(FaceCluster $cluster) {
            return [
                'title' => $cluster->getTitle(),
                'id' => $cluster->getId(),
                'detections' => array_map(function(FaceDetection $detection) {
                    return $detection->toArray();
                }, $this->faceDetections->findByClusterId($cluster->getId())),
            ];
        }, $faceClusters);
        $this->initialState->provideInitialState('faceClusters', $faceClusters);
        return new TemplateResponse('recognize', 'user');
    }

    /**
     * @return string the section ID, e.g. 'sharing'
     */
    public function getSection(): string {
        return 'recognize';
    }

    /**
     * @return int whether the form should be rather on the top or bottom of the admin section. The forms are arranged in ascending order of the priority values. It is required to return a value between 0 and 100.
     */
    public function getPriority(): int {
        return 50;
    }
}
