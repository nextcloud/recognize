<?php

namespace OCA\Recognize\Service;

use OCA\Recognize\Db\FaceCluster;
use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use Rubix\ML\Clusterers\DBSCAN;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Graph\Trees\BallTree;
use Rubix\ML\Kernels\Distance\Euclidean;

class FaceClusterAnalyzer {

    private FaceDetectionMapper $faceDetections;

    private FaceClusterMapper $faceClusters;

    private TagManager $tagManager;

    public function __construct(FaceDetectionMapper $faceDetections, FaceClusterMapper $faceClusters, TagManager $tagManager) {
        $this->faceDetections = $faceDetections;
        $this->faceClusters = $faceClusters;
        $this->tagManager = $tagManager;
    }

    /**
     * @throws \OCP\DB\Exception
     * @throws \JsonException
     */
    public function findClusters(string $userId) {
        /**
         * @var $detections FaceDetection[]
         */
        $detections = $this->faceDetections->findByUserId($userId);
        $dataset = new Unlabeled(array_map(function (FaceDetection $detection) {
            return $detection->getVector();
        }, $detections));
        $clusterer = new DBSCAN(0.4, 4, new BallTree(20, new Euclidean()));
        $results = $clusterer->predict($dataset);
        $numClusters = max($results);

        for($i = 0; $i <= $numClusters; $i++) {
            $keys = array_keys($results, $i);
            $cluster = new FaceCluster();
            $cluster->setTitle('');
            $cluster->setUserId($userId);
            $cluster = $this->faceClusters->insert($cluster);
            foreach ($keys as $key) {
                $detection = $detections[$key];
                $this->faceClusters->assocFaceWithCluster($detection, $cluster);
                $this->tagManager->assignTags($detection->getFileId(), ['face'.$i]);
            }
        }
    }
}
