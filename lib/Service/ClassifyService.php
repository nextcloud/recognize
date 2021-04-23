<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCA\Recognize\Exception\FileNotFoundError;
use OCP\Files\File;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ClassifyService {
    public const IMAGE_TIMEOUT = 17; // 17s
    public const RECOGNIZED_TAG = 'Tagged by recognize';

	/**
	 * @var LoggerInterface
	 */
	private $logger;
    /**
     * @var IRootFolder
     */
    private $rootFolder;
    /**
     * @var ISystemTagManager
     */
    private $tagManager;
    /**
     * @var ISystemTagObjectMapper
     */
    private $objectMapper;

    public function __construct(LoggerInterface $logger, IRootFolder $rootFolder, ISystemTagManager $tagManager, ISystemTagObjectMapper $objectMapper) {
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
		$this->tagManager = $tagManager;
        $this->objectMapper = $objectMapper;
    }

    /**
     * @param File[] $files
     * @return void
     */
	public function classify(array $files): void {
	    $paths = array_map(static function($file) {
            return $file->getStorage()->getLocalFile($file->getInternalPath());
        }, $files);

	    $this->logger->warning('Classifying '.var_export($paths, true));

	    $command = array_merge([
	        __DIR__.'/../../vendor/bin/node',
            __DIR__.'/../../src/classifier.js'
        ], $paths);
        $this->logger->warning('Running '.var_export($command, true));
		$proc = new Process($command, __DIR__);
	    $proc->setTimeout(count($paths)* self::IMAGE_TIMEOUT);
	    try {
            $proc->mustRun();
            $out = $proc->getOutput();
            $this->logger->warning('Output: '.$out);
            $results = json_decode(utf8_encode($out), true, 512, JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE);
        }catch(ProcessTimedOutException $e) {
	        $this->logger->warning('Classifier process timeout');
	        $this->logger->warning($proc->getErrorOutput());
	        return;
        } catch (\JsonException $e) {
            $this->logger->warning('JSON exception');
            $this->logger->warning($e->getMessage());
            return;
        }

        $recognizedTag = $this->getProcessedTag();
        $this->logger->warning('Results: '.var_export($results, true));
        foreach($results as $i => $result) {
            $tags = [$recognizedTag->getId()];
            foreach($result as $r) {
                if ($r['probability'] > 0.50 && $r['className'] !== 'other') {
                    $tags[] = $this->getTag($r['className'])->getId();
                }
            }
            if (count($tags) === 1 && $result[0]['probability'] > 0.35 && $result[0]['className'] !== 'other') {
                $tags[] = $this->getTag($result[0]['className'])->getId();
            }
            try {
                $this->objectMapper->assignTags($files[$i]->getId(), 'files', $tags);
            } catch (InvalidPathException $e) {
                $this->logger->warning('File with invalid path encountered');
            } catch (NotFoundException $e) {
                $this->logger->warning('File to tag was not found');
            }
        }
	}

	public function getTag($name) : ISystemTag {
        try {
            $tag = $this->tagManager->getTag($name, true, true);
        }catch(TagNotFoundException $e) {
            $tag = $this->tagManager->createTag($name, true, true);
        }
        return $tag;
    }

    public function getProcessedTag() {
        try {
            $tag = $this->tagManager->getTag(self::RECOGNIZED_TAG, false, false);
        }catch(TagNotFoundException $e) {
            $tag = $this->tagManager->createTag(self::RECOGNIZED_TAG, false, false);
        }
        return $tag;
    }
}
