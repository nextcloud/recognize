<?php

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
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class InstallDeps implements IRepairStep {
	public const NODE_VERSION = 'v14.18.2';
    public const NODE_SERVER_OFFICIAL = 'https://nodejs.org/dist/';
    public const NODE_SERVER_UNOFFICIAL = 'https://unofficial-builds.nodejs.org/download/release/';

	/** @var IConfig */
	protected $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
		$this->binaryDir = dirname(__DIR__, 2) . '/bin/';
		$this->preGypBinaryDir = dirname(__DIR__, 2) . '/node_modules/@mapbox/node-pre-gyp/bin/';
		$this->tfjsInstallScript = dirname(__DIR__, 2) . '/node_modules/@tensorflow/tfjs-node/scripts/install.js';
		$this->tfjsPath = dirname(__DIR__, 2) . '/node_modules/@tensorflow/tfjs-node/';
	}

	public function getName(): string {
		return 'Install dependencies';
	}

	public function run(IOutput $output): void {
        $isARM = false;
        $isMusl = false;

        if (PHP_INT_SIZE === 8) {
            $binaryPath = $this->downloadNodeBinary(self::NODE_SERVER_OFFICIAL, self::NODE_VERSION, 'arm64');
            $version = $this->testBinary($binaryPath);
            $isARM = true;
            if ($version === null) {
                $binaryPath = $this->downloadNodeBinary(self::NODE_SERVER_OFFICIAL, self::NODE_VERSION, 'x64');
                $version = $this->testBinary($binaryPath);
                $isARM = false;
            }
        } else {
            $binaryPath = $this->downloadNodeBinary(self::NODE_SERVER_OFFICIAL, self::NODE_VERSION, 'armv7l');
            $version = $this->testBinary($binaryPath);
            $isARM = true;
        }

        if ($version === null && PHP_INT_SIZE === 8) {
            $binaryPath = $this->downloadNodeBinary(self::NODE_SERVER_UNOFFICIAL, self::NODE_VERSION, 'x64', 'musl');
            $version = $this->testBinary($binaryPath);
            $isARM = false;
            $isMusl = true;
        }

        if ($version === null) {
            $output->warning('Failed to install node binary');
            return;
        }

		// Write the app config
		$this->config->setAppValue('recognize', 'node_binary', $binaryPath);
        if ($isARM || $isMusl) {
            $this->config->setAppValue('recognize', 'tensorflow.purejs', 'true');
        }

		$this->setBinariesPermissions();

		$this->runTfjsInstall($binaryPath);
	}

	protected function testBinary(string $binaryPath): ?string {
		// Make binary executable
		chmod($binaryPath, 0755);

		$cmd = escapeshellcmd($binaryPath) . ' ' . escapeshellarg('--version');
		try {
			@exec($cmd, $output, $returnCode);
		} catch (\Throwable $e) {
		}

		if ($returnCode !== 0) {
			return null;
		}

		return trim(implode("\n", $output));
	}

	protected function runTfjsInstall($nodeBinary) : void {
		$oriCwd = getcwd();
		chdir($this->tfjsPath);
		$cmd = 'PATH='.escapeshellcmd($this->preGypBinaryDir).':'.escapeshellcmd($this->binaryDir).':$PATH ' . escapeshellcmd($nodeBinary) . ' ' . escapeshellarg($this->tfjsInstallScript) . ' ' . escapeshellarg('download');
		try {
			@exec($cmd, $output, $returnCode);
		} catch (\Throwable $e) {
		}
		chdir($oriCwd);
		if ($returnCode !== 0) {
			throw new \Exception('Failed to install Tensorflow.js: '.trim(implode("\n", $output)));
		}
	}

	protected function downloadNodeBinary(string $server, string $version, string $arch, string $flavor = '') : string {
        $name = 'node-'.$version.'-linux-'.$arch;
        if ($flavor !== '') {
            $name = $name . '-'.$flavor;
        }
		$url = $server.$version.'/'.$name.'.tar.gz';
		$file = $this->binaryDir.'/'.$arch.'.tar.gz';
		$archive = file_get_contents($url);
		if ($archive === false) {
			throw new \Exception('Downloading of node binary failed');
		}
		$saved = file_put_contents($file, $archive);
		if ($saved === false) {
			throw new \Exception('Saving of node binary failed');
		}
		$tar = new TAR($file);
		$tar->extractFile($name.'/bin/node', $this->binaryDir.'/node');
		return $this->binaryDir.'/node';
	}

	/**
	 * try to fix binaries permissions issues
	 */
	protected function setBinariesPermissions() {
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->preGypBinaryDir));
		foreach ($iterator as $item) {
			if (chmod(realpath($item->getPathname()), 0755) === false) {
				throw new \Exception('Error when setting node_modules/.bin/* permissions');
			}
		}
	}
}
