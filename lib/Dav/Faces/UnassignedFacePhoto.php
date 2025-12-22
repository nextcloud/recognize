<?php

/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Dav\Faces;

use OCA\Recognize\Db\FaceDetection;
use OCA\Recognize\Db\FaceDetectionMapper;
use OCP\Files\Folder;
use OCP\IPreview;
use OCP\ITagManager;
use Override;
use Sabre\DAV\Exception\Forbidden;

final class UnassignedFacePhoto extends FacePhoto {

	public function __construct(FaceDetectionMapper $detectionMapper, FaceDetection $faceDetection, Folder $userFolder, ITagManager $tagManager, IPreview $preview) {
		parent::__construct($detectionMapper, $faceDetection, $userFolder, $tagManager, $preview);
	}

	#[Override]
	public function delete(): never {
		throw new Forbidden('Cannot delete unassigned photos');
	}
}
