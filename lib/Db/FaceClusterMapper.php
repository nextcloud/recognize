<?php

namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class FaceClusterMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'recognize_face_clusters', FaceCluster::class);
        $this->db = $db;
    }

    /**
     * @throws \OCP\DB\Exception
     */
    function find(int $id): FaceCluster {
        $qb = $this->db->getQueryBuilder();
        $qb->select(FaceCluster::$columns)
            ->from('recognize_face_clusters')
            ->where($qb->expr()->eq('id', $qb->createPositionalParameter($id)));
        return $this->findEntity($qb);
    }

    /**
     * @throws \OCP\DB\Exception
     */
    function findByUserId(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select(FaceCluster::$columns)
            ->from('recognize_face_clusters')
            ->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));
        return $this->findEntities($qb);
    }

    public function assocFaceWithCluster(FaceDetection $faceDetection, FaceCluster $faceCluster) {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('recognize_faces2clusters')->values([
            'cluster_id' => $qb->createPositionalParameter($faceCluster->getId(), IQueryBuilder::PARAM_INT),
            'face_detection_id' => $qb->createPositionalParameter($faceDetection->getId(), IQueryBuilder::PARAM_INT),
        ])->executeStatement();
    }
}
