<?php

/*
 * Copyright (c) 2021-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Classifiers;

use OCA\Recognize\Classifiers\Images\ClusteringFaceClassifier;
use OCA\Recognize\Classifiers\Images\ImagenetClassifier;
use OCA\Recognize\Classifiers\Images\LandmarksClassifier;
use OCA\Recognize\Constants;
use OCA\Recognize\Db\QueueFile;
use OCA\Recognize\Service\QueueService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\DB\Exception;
use OCP\Files\File;
use OCP\Files\InvalidPathException;
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

abstract class Classifier {
	public const TEMP_FILE_DIMENSION = 1024;
	public const MAX_EXECUTION_TIME = 0;

	protected LoggerInterface $logger;
	protected IAppConfig $config;
	protected IRootFolder $rootFolder;
	protected QueueService $queue;
	private ITempManager $tempManager;
	private IPreview $previewProvider;
	private int $maxExecutionTime = self::MAX_EXECUTION_TIME;

	public function __construct(LoggerInterface $logger, IAppConfig $config, IRootFolder $rootFolder, QueueService $queue, ITempManager $tempManager, IPreview  $previewProvider) {
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
	 * @param QueueFile[] $queueFiles
	 * @return void
	 * @throws \ErrorException|\RuntimeException
	 */
	abstract public function classify(array $queueFiles): void;

	/**
	 * @param string $model
	 * @param QueueFile[] $queueFiles
	 * @param int $timeout
	 * @return \Generator
	 * @psalm-return \Generator<QueueFile, mixed, mixed, null>
	 * @throws \ErrorException|\RuntimeException
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
				if ($files[0]->getSize() == 0) {
					$this->logger->debug('File is empty: ' . $files[0]->getPath());
					try {
						$this->logger->debug('removing ' . $queueFile->getFileId() . ' from ' . $model . ' queue');
						$this->queue->removeFromQueue($model, $queueFile);
					} catch (Exception $e) {
						$this->logger->warning($e->getMessage(), ['exception' => $e]);
					}
					continue;
				}
				$path = $this->getConvertedFilePath($files[0]);
				if (in_array($model, [ImagenetClassifier::MODEL_NAME, LandmarksClassifier::MODEL_NAME, ClusteringFaceClassifier::MODEL_NAME], true)) {
					// Check file data size
					$filesize = filesize($path);
					if ($filesize !== false) {
						$filesizeMb = $filesize / (1024 * 1024);
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
			} catch (NotFoundException|InvalidPathException $e) {
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
			$this->config->getAppValueString('node_binary'),
			dirname(__DIR__, 2) . '/src/classifier_'.$model.'.js',
			'-'
		];

		if (trim($this->config->getAppValueString('nice_binary', '')) !== '') {
			$command = [
				$this->config->getAppValueString('nice_binary'),
				"-" . $this->config->getAppValueString('nice_value', '0'),
				...$command,
			];
		}

		$this->logger->debug('Running '.var_export($command, true));

		$proc = new Process($command, __DIR__);
		$env = [];
		if ($this->config->getAppValueString('tensorflow.gpu', 'false') === 'true') {
			$env['RECOGNIZE_GPU'] = 'true';
		}
		if ($this->config->getAppValueString('tensorflow.purejs', 'false') === 'true') {
			$env['RECOGNIZE_PUREJS'] = 'true';
		}
		// Set cores
		$cores = $this->config->getAppValueString('tensorflow.cores', '0');
		if ($cores !== '0') {
			$env['RECOGNIZE_CORES'] = $cores;
		}
		$proc->setEnv($env);
		$proc->setTimeout(count($paths) * $timeout);
		$proc->setInput(implode("\n", $paths));
		try {
			$proc->start();

			if ($cores !== '0') {
				@exec('taskset -cp ' . implode(',', range(0, (int)$cores, 1)) . ' ' . ((string)$proc->getPid()));
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
			$proc->stop();
			$this->cleanUpTmpFiles();
			if ($i !== count($paths)) {
				$this->logger->warning('Classifier process output: '.$errOut);
				throw new \ErrorException('Classifier process error');
			}
		} catch (ProcessTimedOutException $e) {
			$this->cleanUpTmpFiles();
			$this->logger->warning($proc->getErrorOutput());
			throw new \RuntimeException('Classifier process timeout');
		} catch (RuntimeException $e) {
			$this->cleanUpTmpFiles();
			$this->logger->warning($proc->getErrorOutput());
			throw new \ErrorException('Classifier process could not be started');
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
		if (!$file instanceof File) {
			throw new NotFoundException();
		}
		$path = $file->getStorage()->getLocalFile($file->getInternalPath());

		if (!is_string($path)) {
			throw new NotFoundException();
		}

		// check if this is an image to convert / downscale
		$mime = $file->getMimeType();
		if (!in_array($mime, Constants::IMAGE_FORMATS)) {
			return $path;
		}

		if ($this->previewProvider->isAvailable($file)) {
			try {
				$this->logger->debug('generating preview of ' . $file->getId() . ' with dimension ' . self::TEMP_FILE_DIMENSION . ' using nextcloud preview manager');
				return $this->generatePreviewWithProvider($file);
			} catch (\Throwable $e) {
				$this->logger->warning('Failed to generate preview of ' . $file->getId() . ' with dimension ' . self::TEMP_FILE_DIMENSION . ' with nextcloud preview manager: ' . $e->getMessage());
			}
		}

		try {
			$imageType = exif_imagetype($path);
			if ($imageType > 0) {
				$this->logger->debug('generating preview of ' . $file->getId() . ' with dimension ' . self::TEMP_FILE_DIMENSION . ' using gdlib');
				return $this->generatePreviewWithGD($path);
			} else {
				return $path;
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to generate preview of ' . $file->getId() . ' with dimension ' . self::TEMP_FILE_DIMENSION . ' with gdlib: ' . $e->getMessage());
			return $path;
		}
	}

	public function cleanUpTmpFiles():void {
		$this->tempManager->clean();
	}

	/**
	 * @param File $file
	 * @return string
	 * @throws \OCA\Recognize\Exception\Exception|NotFoundException
	 */
	public function generatePreviewWithProvider(File $file): string {
		$image = $this->previewProvider->getPreview($file, self::TEMP_FILE_DIMENSION, self::TEMP_FILE_DIMENSION);

		try {
			$preview = $image->read();
		} catch (NotPermittedException $e) {
			throw new \OCA\Recognize\Exception\Exception('Could not read preview file', 0, $e);
		}

		if ($preview === false) {
			throw new \OCA\Recognize\Exception\Exception('Could not open preview file');
		}

		// Create a temporary file *with the correct extension*
		$tmpname = $this->tempManager->getTemporaryFile('.jpg');

		$tmpfile = fopen($tmpname, 'wb');

		if ($tmpfile === false) {
			throw new \OCA\Recognize\Exception\Exception('Could not open tmpfile');
		}

		$copyResult = stream_copy_to_stream($preview, $tmpfile);
		fclose($preview);
		fclose($tmpfile);

		if ($copyResult === false) {
			throw new \OCA\Recognize\Exception\Exception('Could not copy preview file to temp folder');
		}

		$imagetype = exif_imagetype($tmpname);

		if (in_array($imagetype, [IMAGETYPE_WEBP, IMAGETYPE_AVIF, false])) { // To troubleshoot if it is a webp or avif.
			$imageString = file_get_contents($tmpname);
			if ($imageString === false) {
				throw new \OCA\Recognize\Exception\Exception('Could not load preview file from temp folder');
			}
			$previewImage = imagecreatefromstring($imageString);
			if ($previewImage === false) {
				throw new \OCA\Recognize\Exception\Exception('Could not load preview file from temp folder');
			}
			$use_gd_quality = (int)\OCP\Server::get(IConfig::class)->getSystemValue('recognize.preview.quality', '100');
			if (imagejpeg($previewImage, $tmpname, $use_gd_quality) === false) {
				imagedestroy($previewImage);
				throw new \OCA\Recognize\Exception\Exception('Could not copy preview file to temp folder');
			}
			imagedestroy($previewImage);
		}

		return $tmpname;
	}

	/**
	 * @param string $path
	 * @return string
	 * @throws \OCA\Recognize\Exception\Exception
	 */
	public function generatePreviewWithGD(string $path): string {
		$imageContents = file_get_contents($path);
		if (!$imageContents) {
			throw new \OCA\Recognize\Exception\Exception('Could not load image for preview with gdlib');
		}
		$image = imagecreatefromstring($imageContents);
		if (!$image) {
			throw new \OCA\Recognize\Exception\Exception('Could not load image for preview with gdlib');
		}
		$width = imagesx($image);
		$height = imagesy($image);

		if ($width === false || $height === false) {
			throw new \OCA\Recognize\Exception\Exception('Could not get image dimensions for preview with gdlib');
		}

		$maxWidth = (float) self::TEMP_FILE_DIMENSION;
		$maxHeight = (float) self::TEMP_FILE_DIMENSION;

		if ($width > $maxWidth || $height > $maxHeight) {
			$aspectRatio = (float) ($width / $height);
			if ($width > $height) {
				$newWidth = $maxWidth;
				$newHeight = $maxWidth / $aspectRatio;
			} else {
				$newHeight = $maxHeight;
				$newWidth = $maxHeight * $aspectRatio;
			}
			$previewImage = imagescale($image, (int)$newWidth, (int)$newHeight);
		} else {
			return $path;
		}

		// Create a temporary file *with the correct extension*
		$tmpname = $this->tempManager->getTemporaryFile('.jpg');

		$use_gd_quality = (int)\OCP\Server::get(IConfig::class)->getSystemValue('recognize.preview.quality', '100');
		if (imagejpeg($previewImage, $tmpname, $use_gd_quality) === false) {
			imagedestroy($image);
			imagedestroy($previewImage);
			throw new \OCA\Recognize\Exception\Exception('Could not copy preview file to temp folder');
		}
		imagedestroy($image);
		imagedestroy($previewImage);

		return $tmpname;
	}
}
