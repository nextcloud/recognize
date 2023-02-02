<?php
/*
 * Copyright (c) 2021-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\Classifiers;

use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Constants;
use OCA\Recognize\Service\QueueService;
use OCP\DB\Exception;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IPreview;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class Classifier {
	public const TEMP_FILE_DIMENSION = 1024;
	public const MAX_EXECUTION_TIME = 60 * 5;

	protected LoggerInterface $logger;
	protected IConfig $config;
	private IRootFolder $rootFolder;
	protected QueueService $queue;
	private ITempManager $tempManager;
	private IPreview $previewProvider;
	private int $maxExecutionTime = self::MAX_EXECUTION_TIME;

	/**
	 * @var list<string>
	 */
	private array $tmpFiles = [];

	public function __construct(LoggerInterface $logger, IConfig $config, IRootFolder $rootFolder, QueueService $queue, ITempManager $tempManager, IPreview  $previewProvider) {
		$this->logger = $logger;
		$this->config = $config;
		$this->rootFolder = $rootFolder;
		$this->queue = $queue;
		$this->tempManager = $tempManager;
		$this->previewProvider = $previewProvider;
	}

	public function setMaxExecutionTime(int $time): void {
		$this->maxExecutionTime = $time;
	}

	/**
	 * @param string $model
	 * @param \OCA\Recognize\Db\QueueFile[] $queueFiles
	 * @param int $timeout
	 * @return \Generator
	 */
	public function classifyFiles(string $model, array $queueFiles, int $timeout): \Generator {
		$paths = [];
		$processedFiles = [];
		$fileNames = [];
		$startTime = time();
		foreach ($queueFiles as $queueFile) {
			if ($this->maxExecutionTime > 0 && time() - $startTime > $this->maxExecutionTime) {
				return;
			}
			$files = $this->rootFolder->getById($queueFile->getFileId());
			if (count($files) === 0) {
				try {
					$this->logger->debug('removing '.$queueFile->getFileId().' from '.$model.' queue because it couldn\'t be found');
					$this->queue->removeFromQueue($model, $queueFile);
				} catch (Exception $e) {
					$this->logger->warning($e->getMessage(), ['exception' => $e]);
				}
				continue;
			}
			try {
				$path = $this->getConvertedFilePath($files[0]);
				if (in_array($model, [ImagenetClassifier::MODEL_NAME, LandmarksClassifier::MODEL_NAME, ClusteringFaceClassifier::MODEL_NAME], true)) {
					// Check file data size
					$filesizeMb = filesize($path) / (1024 * 1024);
					if ($filesizeMb > 8) {
						$this->logger->debug('File is too large for classifier: ' . $files[0]->getPath());
						try {
							$this->logger->debug('removing ' . $queueFile->getFileId() . ' from ' . $model . ' queue');
							$this->queue->removeFromQueue($model, $queueFile);
						} catch (Exception $e) {
							$this->logger->warning($e->getMessage(), ['exception' => $e]);
						}
						continue;
					}
					// Check file dimensions
					$dimensions = @getimagesize($path);
					if (isset($dimensions) && $dimensions !== false && ($dimensions[0] > 1024 || $dimensions[1] > 1024)) {
						$this->logger->debug('File dimensions are too large for classifier: ' . $files[0]->getPath());
						try {
							$this->logger->debug('removing ' . $queueFile->getFileId() . ' from ' . $model . ' queue');
							$this->queue->removeFromQueue($model, $queueFile);
						} catch (Exception $e) {
							$this->logger->warning($e->getMessage(), ['exception' => $e]);
						}
						continue;
					}
				}
				$paths[] = $path;
				$processedFiles[] = $queueFile;
				$fileNames[] = $files[0]->getPath();
			} catch (NotFoundException $e) {
				$this->logger->warning('Could not find file', ['exception' => $e]);
				try {
					$this->queue->removeFromQueue($model, $queueFile);
				} catch (Exception $e) {
					$this->logger->warning($e->getMessage(), ['exception' => $e]);
				}
				continue;
			}
		}

		if (count($paths) === 0) {
			$this->logger->debug('No files left to classify');
			return;
		}

		$this->logger->debug('Classifying '.var_export($paths, true));

		$command = [
			$this->config->getAppValue('recognize', 'node_binary'),
			dirname(__DIR__, 2) . '/src/classifier_'.$model.'.js',
			'-'
		];

		$this->logger->debug('Running '.var_export($command, true));

		$proc = new Process($command, __DIR__);
		if ($this->config->getAppValue('recognize', 'tensorflow.gpu', 'false') === 'true') {
			$proc->setEnv(['RECOGNIZE_GPU' => 'true']);
		}
		if ($this->config->getAppValue('recognize', 'tensorflow.purejs', 'false') === 'true') {
			$proc->setEnv(['RECOGNIZE_PUREJS' => 'true']);
		}
		$proc->setTimeout(count($paths) * $timeout);
		$proc->setInput(implode("\n", $paths));
		try {
			$proc->start();

			// Set cores
			$cores = $this->config->getAppValue('recognize', 'tensorflow.cores', '0');
			if ($cores !== '0') {
				@exec('taskset -cp ' . implode(',', range(0, (int)$cores, 1)) . ' ' . $proc->getPid());
			}

			$i = 0;
			$errOut = '';
			$buffer = '';
			foreach ($proc as $type => $data) {
				if ($type !== $proc::OUT) {
					$errOut .= $data;
					$this->logger->debug('Classifier process output: '.$data);
					continue;
				}
				if ($this->maxExecutionTime > 0 && time() - $startTime > $this->maxExecutionTime) {
					$proc->stop(10, 9);
					$this->cleanUpTmpFiles();
					return;
				}
				$buffer .= $data;
				$lines = explode("\n", $buffer);
				$buffer = '';
				foreach ($lines as $result) {
					if (trim($result) === '') {
						continue;
					}
					try {
						json_decode($result, true, 512, JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE);
						$invalid = false;
					} catch (\JsonException $e) {
						$invalid = true;
					}
					if ($invalid) {
						$buffer .= "\n".$result;
						continue;
					}
					$this->logger->debug('Result for ' . $fileNames[$i] .'(' . basename($paths[$i]) . ') = ' . $result);
					try {
						// decode json
						$results = json_decode($result, true, 512, JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE);
						yield $processedFiles[$i] => $results;
						$this->queue->removeFromQueue($model, $processedFiles[$i]);
					} catch (\JsonException $e) {
						$this->logger->warning('JSON exception');
						$this->logger->warning($e->getMessage(), ['exception' => $e]);
						$this->logger->warning($result);
					} catch (Exception $e) {
						$this->logger->warning($e->getMessage(), ['exception' => $e]);
					}
					$i++;
				}
			}
			$this->cleanUpTmpFiles();
			if ($i !== count($paths)) {
				$this->logger->warning('Classifier process output: '.$errOut);
				throw new \RuntimeException('Classifier process error');
			}
		} catch (ProcessTimedOutException $e) {
			$this->cleanUpTmpFiles();
			$this->logger->warning($proc->getErrorOutput());
			throw new \RuntimeException('Classifier process timeout');
		} catch (RuntimeException $e) {
			$this->cleanUpTmpFiles();
			$this->logger->warning($proc->getErrorOutput());
			throw new \RuntimeException('Classifier process could not be started');
		}
	}

	/**
	 * Get path of file to process.
	 * If the file is an image and not JPEG, it will be converted using ImageMagick.
	 * Images will also be downscaled to a max dimension of 4096px.
	 *
	 * @param \OCP\Files\Node $file
	 * @return string Path to file to process
	 * @throws \OCP\Files\NotFoundException
	 */
	private function getConvertedFilePath(Node $file): string {
		$path = $file->getStorage()->getLocalFile($file->getInternalPath());

		if ($path === false || !is_string($path) || !$file instanceof File) {
			throw new NotFoundException();
		}

		// check if this is an image to convert / downscale
		$mime = $file->getMimeType();
		if (!in_array($mime, Constants::IMAGE_FORMATS)) {
			return $path;
		}

		// Create a temporary file *with the correct extension*
		$tmpname = $this->tempManager->getTemporaryFile('.jpg');

		if (!$this->previewProvider->isAvailable($file) && !($file instanceof File)) {
			return $path;
		}

		try {
			$this->logger->debug('generating preview of ' . $file->getId() . ' with dimension '.self::TEMP_FILE_DIMENSION);
			$image = $this->previewProvider->getPreview($file, self::TEMP_FILE_DIMENSION, self::TEMP_FILE_DIMENSION);
		} catch(NotFoundException|\InvalidArgumentException $e) {
			return $path;
		}

		$tmpfile = fopen($tmpname, 'wb');

		if ($tmpfile === false) {
			$this->logger->warning('Could not open tmpfile');
			return $path;
		}

		try {
			$preview = $image->read();
		} catch (NotPermittedException $e) {
			$this->logger->warning('Could not read preview file', ['exception' => $e]);
			return $path;
		}

		if ($preview === false) {
			$this->logger->warning('Could not open preview file');
			return $path;
		}

		$this->logger->debug('Copying ' . $file->getId() . ' preview to tempfolder');
		if (stream_copy_to_stream($preview, $tmpfile) === false) {
			$this->logger->warning('Could not copy preview file to temp folder');
			fclose($preview);
			fclose($tmpfile);
			return $path;
		}
		fclose($preview);
		fclose($tmpfile);

		$this->tmpFiles[] = $tmpname;

		return $tmpname;
	}

	public function cleanUpTmpFiles():void {
		foreach ($this->tmpFiles as $tmpFile) {
			unlink($tmpFile);
		}
		$this->tmpFiles = [];
	}
}
