<?php

namespace OCA\Recognize\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class FaceDetectionMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'recognize_face_detections', FaceDetection::class);
        $this->db = $db;
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     * @throws \OCP\DB\Exception
     */
    public function find(int $id): FaceDetection {
        $qb = $this->db->getQueryBuilder();
        $qb
            ->select(FaceDetection::$columns)
            ->from('recognize_face_detections')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

        return $this->findEntity($qb);
    }

    public function findByClusterId(int $clusterId) : array {
        $qb = $this->db->getQueryBuilder();
        $qb->select(FaceDetection::$columns)
            ->from('recognize_face_detections', 'd')
            ->join('d', 'recognize_faces2clusters', 'c', 'd.id = c.face_detection_id')
            ->where($qb->expr()->eq('c.cluster_id', $qb->createPositionalParameter($clusterId)));
        return $this->findEntities($qb);
    }

    /**
     * @throws \OCP\DB\Exception
     */
    function findByUserId(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select(FaceDetection::$columns)
            ->from('recognize_face_detections')
            ->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));
        return $this->findEntities($qb);
    }

    /**
     * @throws \OCP\DB\Exception
     */
    function findByFileId(int $fileId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select(FaceDetection::$columns)
            ->from('recognize_face_detections')
            ->where($qb->expr()->eq('file_id', $qb->createPositionalParameter($fileId, IQueryBuilder::PARAM_INT)));
        return $this->findEntities($qb);
    }
}
