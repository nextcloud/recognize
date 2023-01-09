<?php
/*
 * Copyright (c) 2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2021, Marcel Klehr <mklehr@gmx.net>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Recognize\Migration;

use OCA\Recognize\Helper\TAR;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

class InstallDeps implements IRepairStep {
	public const NODE_VERSION = 'v14.18.2';
	public const NODE_SERVER_OFFICIAL = 'https://nodejs.org/dist/';
	public const NODE_SERVER_UNOFFICIAL = 'https://unofficial-builds.nodejs.org/download/release/';

	protected IConfig $config;
	private string $binaryDir;
	private string $preGypBinaryDir;
	private string $ffmpegDir;
	private string $tfjsInstallScript;
	private string $tfjsPath;
	private IClientService $clientService;
	private LoggerInterface $logger;
	private string $tfjsGpuInstallScript;
	private string $ffmpegInstallScript;
	private string $tfjsGPUPath;

	public function __construct(IConfig $config, IClientService $clientService, LoggerInterface $logger) {
		$this->config = $config;
		$this->binaryDir = dirname(__DIR__, 2) . '/bin/';
		$this->preGypBinaryDir = dirname(__DIR__, 2) . '/node_modules/@mapbox/node-pre-gyp/bin/';
		$this->ffmpegDir = dirname(__DIR__, 2) . '/node_modules/ffmpeg-static/';
		$this->ffmpegInstallScript = dirname(__DIR__, 2) . '/node_modules/ffmpeg-static/install.js';
		$this->tfjsInstallScript = dirname(__DIR__, 2) . '/node_modules/@tensorflow/tfjs-node/scripts/install.js';
		$this->tfjsGpuInstallScript = dirname(__DIR__, 2) . '/node_modules/@tensorflow/tfjs-node-gpu/scripts/install.js';
		$this->tfjsPath = dirname(__DIR__, 2) . '/node_modules/@tensorflow/tfjs-node/';
		$this->tfjsGPUPath = dirname(__DIR__, 2) . '/node_modules/@tensorflow/tfjs-node-gpu/';
		$this->clientService = $clientService;
		$this->logger = $logger;
	}

	public function getName(): string {
		return 'Install dependencies';
	}

	public function run(IOutput $output): void {
		$existingBinary = $this->config->getAppValue('recognize', 'node_binary', '');
		if ($existingBinary !== '') {
			$version = $this->testBinary($existingBinary);
			if ($version !== null) {
				$this->installNodeBinary($output);
			}
		} else {
			$this->installNodeBinary($output);
		}

		$this->setBinariesPermissions();

		$binaryPath = $this->config->getAppValue('recognize', 'node_binary', '');

		$this->runTfjsInstall($binaryPath);
		$this->runTfjsGpuInstall($binaryPath);
		$this->runFfmpegInstall($binaryPath);
	}

	protected function installNodeBinary(IOutput $output) : void {
		$isARM = false;
		$isMusl = false;
		$uname = php_uname('m');

		if ($uname === 'x86_64') {
			$binaryPath = $this->downloadNodeBinary(self::NODE_SERVER_OFFICIAL, self::NODE_VERSION, 'x64');
			$version = $this->testBinary($binaryPath);

			if ($version === null) {
				$binaryPath = $this->downloadNodeBinary(self::NODE_SERVER_UNOFFICIAL, self::NODE_VERSION, 'x64', 'musl');
				$version = $this->testBinary($binaryPath);
				if ($version !== null) {
					$isMusl = true;
				}
			}
		} elseif ($uname === 'aarch64') {
			$binaryPath = $this->downloadNodeBinary(self::NODE_SERVER_OFFICIAL, self::NODE_VERSION, 'arm64');
			$version = $this->testBinary($binaryPath);
			if ($version !== null) {
				$isARM = true;
			}
		} elseif ($uname === 'armv7l') {
			$binaryPath = $this->downloadNodeBinary(self::NODE_SERVER_OFFICIAL, self::NODE_VERSION, 'armv7l');
			$version = $this->testBinary($binaryPath);
			if ($version !== null) {
				$isARM = true;
			}
		} else {
			$output->warning('CPU archtecture $uname is not supported.');
			return;
		}

		if ($version === null) {
			$output->warning('Failed to install node binary');
			return;
		}

		// Write the app config
		$this->config->setAppValue('recognize', 'node_binary', $binaryPath);

		$supportsAVX = $this->isAVXSupported();
		if ($isARM || $isMusl || !$supportsAVX) {
			$output->info('Enabling purejs mode (isMusl='.$isMusl.', isARM='.$isARM.', supportsAVX='.$supportsAVX.')');
			$this->config->setAppValue('recognize', 'tensorflow.purejs', 'true');
		}
	}

	protected function testBinary(string $binaryPath): ?string {
		// Make binary executable
		chmod($binaryPath, 0755);

		$cmd = escapeshellcmd($binaryPath) . ' ' . escapeshellarg('--version');
		try {
			exec($cmd . ' 2>&1', $output, $returnCode);
		} catch (\Throwable $e) {
		}

		if ($returnCode !== 0) {
			return null;
		}

		return trim(implode("\n", $output));
	}

	protected function runTfjsInstall(string $nodeBinary) : void {
		$oriCwd = getcwd();
		chdir($this->tfjsPath);
		$cmd = 'PATH='.escapeshellcmd($this->preGypBinaryDir).':'.escapeshellcmd($this->binaryDir).':$PATH ' . escapeshellcmd($nodeBinary) . ' ' . escapeshellarg($this->tfjsInstallScript) . ' cpu ' . escapeshellarg('download');
		try {
			exec($cmd . ' 2>&1', $output, $returnCode); // Appending  2>&1 to avoid leaking sterr
		} catch (\Throwable $e) {
			$this->logger->error('Failed to install Tensorflow.js: '.$e->getMessage(), ['exception' => $e]);
			throw new \Exception('Failed to install Tensorflow.js: '.$e->getMessage());
		}
		chdir($oriCwd);
		if ($returnCode !== 0) {
			$this->logger->error('Failed to install Tensorflow.js: '.trim(implode("\n", $output)));
			throw new \Exception('Failed to install Tensorflow.js: '.trim(implode("\n", $output)));
		}
	}

	protected function runTfjsGpuInstall(string $nodeBinary) : void {
		$oriCwd = getcwd();
		chdir($this->tfjsGPUPath);
		$cmd = 'PATH='.escapeshellcmd($this->preGypBinaryDir).':'.escapeshellcmd($this->binaryDir).':$PATH ' . escapeshellcmd($nodeBinary) . ' ' . escapeshellarg($this->tfjsGpuInstallScript) . ' gpu ' . escapeshellarg('download');
		try {
			exec($cmd . ' 2>&1', $output, $returnCode); // Appending  2>&1 to avoid leaking sterr
		} catch (\Throwable $e) {
			$this->logger->error('Failed to install Tensorflow.js for GPU: '.$e->getMessage(), ['exception' => $e]);
			throw new \Exception('Failed to install Tensorflow.js for GPU: '.$e->getMessage());
		}
		chdir($oriCwd);
		if ($returnCode !== 0) {
			$this->logger->error('Failed to install Tensorflow.js for GPU: '.trim(implode("\n", $output)));
			throw new \Exception('Failed to install Tensorflow.js for GPU: '.trim(implode("\n", $output)));
		}
	}

	protected function runFfmpegInstall(string $nodeBinary): void {
		$oriCwd = getcwd();
		chdir($this->tfjsGPUPath);
		$cmd = escapeshellcmd($nodeBinary) . ' ' . escapeshellarg($this->ffmpegInstallScript);
		try {
			exec($cmd . ' 2>&1', $output, $returnCode); // Appending  2>&1 to avoid leaking sterr
		} catch (\Throwable $e) {
			$this->logger->error('Failed to install ffmpeg: '.$e->getMessage(), ['exception' => $e]);
			throw new \Exception('Failed to install ffmpeg: '.$e->getMessage());
		}
		chdir($oriCwd);
		if ($returnCode !== 0) {
			$this->logger->error('Failed to install ffmpeg: '.trim(implode("\n", $output)));
			throw new \Exception('Failed to install ffmpeg: '.trim(implode("\n", $output)));
		}
	}

	protected function downloadNodeBinary(string $server, string $version, string $arch, string $flavor = '') : string {
		$name = 'node-'.$version.'-linux-'.$arch;
		if ($flavor !== '') {
			$name = $name . '-'.$flavor;
		}
		$url = $server.$version.'/'.$name.'.tar.gz';
		$file = $this->binaryDir.$arch.'.tar.gz';
		try {
			$this->clientService->newClient()->get($url, ['timeout' => 300, 'sink' => $file]);
		} catch (\Exception $e) {
			$this->logger->error('Downloading of node binary failed', ['exception' => $e]);
			throw new \Exception('Downloading of node binary failed');
		}
		$tar = new TAR($file);
		$tar->extractFile($name.'/bin/node', $this->binaryDir.'node');
		return $this->binaryDir.'node';
	}

	/**
	 * try to fix binaries permissions issues
	 */
	protected function setBinariesPermissions(): void {
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->preGypBinaryDir));
		foreach ($iterator as $item) {
			if (chmod(realpath($item->getPathname()), 0755) === false) {
				throw new \Exception('Error when setting '.$this->preGypBinaryDir.'* permissions');
			}
		}
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->ffmpegDir));
		foreach ($iterator as $item) {
			if (chmod(realpath($item->getPathname()), 0755) === false) {
				throw new \Exception('Error when setting '.$this->ffmpegDir.'* permissions');
			}
		}
	}

	protected function isAVXSupported(): bool {
		try {
			$cpuinfo = file_get_contents('/proc/cpuinfo');
		} catch (\Throwable $e) {
			return false;
		}

		return $cpuinfo !== false && strpos($cpuinfo, 'avx') !== false;
	}
}
