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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
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
    /**
     * @var \OCP\IConfig
     */
    private $config;

    public function __construct(LoggerInterface $logger, IRootFolder $rootFolder, ISystemTagManager $tagManager, ISystemTagObjectMapper $objectMapper, \OCP\IConfig $config) {
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
		$this->tagManager = $tagManager;
        $this->objectMapper = $objectMapper;
        $this->config = $config;
    }

    /**
     * @param File[] $files
     * @return void
     */
	public function classify(array $files): void {
	    $paths = array_map(static function($file) {
            return $file->getStorage()->getLocalFile($file->getInternalPath());
        }, $files);

	    $this->logger->debug('Classifying '.var_export($paths, true));

        $recognizedTag = $this->getProcessedTag();

	    $command = array_merge([
	        $this->config->getAppValue('recognize', 'node_binary'),
            dirname(__DIR__, 2) . '/src/classifier.js'
        ], $paths);

        $this->logger->debug('Running '.var_export($command, true));
		$proc = new Process($command, __DIR__);
	    $proc->setTimeout(count($paths) * self::IMAGE_TIMEOUT);
	    try {
            $proc->start();

            $i = 0;
            $errOut = '';
            foreach ($proc as $type => $data) {
                if ($type !== $proc::OUT) {
                    $errOut .= $data;
                    $this->logger->debug('Classifier process output: '.$data);
                    continue;
                }
                $lines = explode("\n", $data);
                foreach($lines as $result) {
                    if (trim($result) === '') {
                        continue;
                    }
                    $this->logger->debug('Result for ' . $files[$i]->getName() . ' = ' . $result);
                    try {
                        // decode json
                        $tags = json_decode(utf8_encode($result), true, 512, JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE);


                        // assign tags
                        $tags = array_map(function ($tag) {
                            return $this->getTag($tag)->getId();
                        }, $tags);
                        $tags[] = $recognizedTag->getId();
                        $this->objectMapper->assignTags($files[$i]->getId(), 'files', $tags);

                    } catch (InvalidPathException $e) {
                        $this->logger->warning('File with invalid path encountered');
                    } catch (NotFoundException $e) {
                        $this->logger->warning('File to tag was not found');
                    } catch (\JsonException $e) {
                        $this->logger->warning('JSON exception');
                        $this->logger->warning($e->getMessage());
                        $this->logger->warning($result);
                    }
                    $i++;
                }
            }
            if ($i !== count($files)-1) {
                $this->logger->warning('Classifier process output: '.$errOut);
            }
        }catch(ProcessTimedOutException $e) {
	        $this->logger->warning('Classifier process timeout');
	        $this->logger->warning($proc->getErrorOutput());
	        return;
        }catch(RuntimeException $e) {
            $this->logger->warning('Classifier process could not be started');
            $this->logger->warning($proc->getErrorOutput());
            return;
        }
	}

    /**
     * @param File[] $files
     * @param int $threads
     * @return void
     */
    public function classifyParallel(array $files, int $threads, OutputInterface $output): int {
        $chunks = array_chunk($files, count($files)/$threads);

        $recognizedTag = $this->getProcessedTag();


        $i = [];
        $errOut = [];
        $proc = [];
        foreach($chunks as $j => $chunk) {
            try {
                $paths = array_map(static function($file) {
                    return $file->getStorage()->getLocalFile($file->getInternalPath());
                }, $chunk);

                $output->writeln('Classifying '.var_export($paths, true));

                $command = array_merge([
                    $this->config->getAppValue('recognize', 'node_binary'),
                    dirname(__DIR__, 2) . '/src/classifier.js'
                ], $paths);

                $output->writeln('Running ' . var_export($command, true));
                $proc[$j] = new Process($command, __DIR__);
                $proc[$j]->setTimeout(count($paths) * self::IMAGE_TIMEOUT);

                $i[$j] = 0;
                $errOut[$j] = '';
                $proc[$j]->start(function ($type, $data) use ($i, $proc, $errOut, $chunk, $recognizedTag, $j, $output){
                    if ($type !== $proc[$j]::OUT) {
                        $errOut[$j] .= $data;
                        $output->writeln('Classifier process output: ' . $data);
                        return;
                    }
                    $lines = explode("\n", $data);
                    foreach($lines as $result) {
                        if (trim($result) === '') {
                            continue;
                        }
                        $output->writeln('Result for ' . $chunk[$i[$j]]->getName() . ' = ' . $result);
                        try {
                            // decode json
                            $tags = json_decode(utf8_encode($result), true, 512, JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE);

                            // assign tags
                            $tags = array_map(function ($tag) {
                                return $this->getTag($tag)->getId();
                            }, $tags);
                            $tags[] = $recognizedTag->getId();
                            $this->objectMapper->assignTags($chunk[$i[$j]]->getId(), 'files', $tags);

                        } catch (InvalidPathException $e) {
                            $output->writeln('File with invalid path encountered');
                        } catch (NotFoundException $e) {
                            $output->writeln('File to tag was not found');
                        } catch (\JsonException $e) {
                            $output->writeln('JSON exception');
                            $output->writeln($e->getMessage());
                            $output->writeln($result);
                        }
                        $i[$j]++;
                    }
                });
            } catch (RuntimeException $e) {
                $output->writeln('Classifier process could not be started');
                $output->writeln($proc[$j]->getErrorOutput());
                continue;
            }
        }
        $return = 0;
        foreach ($proc as $j => $process) {
            try {
                $process->wait();
            } catch (ProcessTimedOutException $e) {
                $output->writeln('Classifier process timeout');
                $output->writeln($process->getErrorOutput());
                continue;
            }
            if ($i[$j] !== count($chunks[$j])-1) {
                $output->writeln('Classifier process output: ' . $errOut[$j]);
                $return = 1;
            }
        }
        return $return;
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
