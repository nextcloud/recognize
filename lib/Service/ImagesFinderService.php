<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\Recognize\Service\TagManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagObjectMapper;
use Psr\Log\LoggerInterface;

class ImagesFinderService
{
    public const FORMATS = ['image/jpeg', 'image/png', 'image/bmp', 'image/tiff'];

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ISystemTagObjectMapper
     */
    private $objectMapper;
    /**
     * @var ISystemTag
     */
    private $recognizedTag;

    public function __construct(LoggerInterface $logger, ISystemTagObjectMapper $objectMapper, TagManager $tagManager) {
        $this->logger = $logger;
        $this->objectMapper = $objectMapper;
        $this->recognizedTag = $tagManager->getProcessedTag();
    }

    /**
     * @throws NotFoundException|InvalidPathException
     */
    public function findImagesInFolder(Folder $folder, &$results = []):array {
        $this->logger->debug('Searching '.$folder->getInternalPath());
        $nodes = $folder->getDirectoryListing();
        foreach ($nodes as $node) {
            if ($node instanceof Folder) {
                $this->findImagesInFolder($node, $results);
            }
            else if ($node instanceof File) {
                if ($this->objectMapper->haveTag([$node->getId()], 'files', $this->recognizedTag->getId())) {
                    continue;
                }
                $mimeType = $node->getMimetype();
                if (!in_array($mimeType, self::FORMATS)) {
                    continue;
                }
                $this->logger->debug('Found '.$node->getPath());
                $results[] = $node;
            }
        }
        return $results;
    }
}
