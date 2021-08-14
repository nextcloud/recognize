<?php
/*
 * Copyright (c) 2021. The Nextcloud Recognize contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Service;

use OCP\Files\File;
use OCP\IConfig;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class ClassifyFacesService {
    public const IMAGE_TIMEOUT = 17; // 17s

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var TagManager
     */
    private $tagManager;
    /**
     * @var IConfig
     */
    private $config;

    public function __construct(LoggerInterface $logger, IConfig $config, TagManager $tagManager) {
        $this->logger = $logger;
        $this->config = $config;
        $this->tagManager = $tagManager;
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

        $command = [
            $this->config->getAppValue('recognize', 'node_binary'),
            dirname(__DIR__, 2) . '/src/classifier_faces.js',
            '-'
        ];

        $this->logger->debug('Running '.var_export($command, true));
        $proc = new Process($command, __DIR__);
        $proc->setTimeout(count($paths) * self::IMAGE_TIMEOUT);
        $proc->setInput(implode("\n", $paths));
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
                        $this->tagManager->assignTags($files[$i]->getId(), $tags);
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
            if ($i !== count($files)) {
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
     * @return int
     */
    public function classifyParallel(array $faces, array $files, int $threads, OutputInterface $output): int {
        $chunks = array_chunk($files, ceil(count($files)/$threads));

        $output->writeln('Looking for faces: '.var_export($faces, true));

        $return = 0;
        $errOut = [];
        $proc = [];
        $i = [];
        array_walk($chunks, function($chunk, $j) use ($output, &$proc, &$errOut, &$i, &$return, $faces){
            try {
                $paths = array_map(static function($file) {
                    return $file->getStorage()->getLocalFile($file->getInternalPath());
                }, $chunk);

                $output->writeln('Classifying '.var_export($paths, true));

                $command =[
                    $this->config->getAppValue('recognize', 'node_binary'),
                    dirname(__DIR__, 2) . '/src/classifier_faces.js',
                    '-'
                ];

                $output->writeln('Running ' . var_export($command, true));
                $proc[$j] = new Process($command, __DIR__);
                $proc[$j]->setTimeout(count($paths) * self::IMAGE_TIMEOUT);
                $proc[$j]->setInput(json_encode($faces) . "\n\n" . implode("\n", $paths));

                $i[$j] = 0;
                $errOut[$j] = '';
                $proc[$j]->start(function ($type, $data) use (&$i, &$proc, &$errOut, $chunk, &$j, $output, &$return){
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
                            $this->tagManager->assignTags($chunk[$i[$j]]->getId(), $tags);
                        } catch (InvalidPathException $e) {
                            $output->writeln('File with invalid path encountered');
                            $return = 1;
                        } catch (NotFoundException $e) {
                            $output->writeln('File to tag was not found');
                            $return = 1;
                        } catch (\JsonException $e) {
                            $output->writeln('JSON exception');
                            $output->writeln($e->getMessage());
                            $output->writeln($result);
                            $return = 1;
                        }
                        $i[$j]++;
                    }
                });
            } catch (RuntimeException $e) {
                $output->writeln('Classifier process could not be started');
                $output->writeln($proc[$j]->getErrorOutput());
                $return = 1;
            }
        });

        foreach ($proc as $j => $process) {
            try {
                $process->wait();
            } catch (ProcessTimedOutException $e) {
                $output->writeln('Classifier process timeout');
                $output->writeln($process->getErrorOutput());
                $return = 1;
                continue;
            }
            if ($i[$j] !== count($chunks[$j])) {
                $output->writeln('Classifier process output: ' . $errOut[$j]);
                $return = 1;
            }
        }
        return $return;
    }
}
