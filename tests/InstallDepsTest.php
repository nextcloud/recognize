<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Recognize\Tests;

use OCA\Recognize\Migration\InstallDeps;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Http\Client\IClientService;
use OCP\IBinaryFinder;
use OCP\IConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Exercises InstallDeps::run() with a fake node binary that records every
 * install script it is asked to execute instead of downloading anything.
 */
final class InstallDepsTest extends TestCase {
	private string $appRoot;
	private string $logFile;
	private string $fakeNode;

	private IAppConfig&MockObject $appConfig;
	private IOutput&MockObject $output;

	protected function setUp(): void {
		parent::setUp();
		$this->appRoot = \dirname(__DIR__);
		$this->logFile = \tempnam(\sys_get_temp_dir(), 'recognize-installdeps-');
		$this->fakeNode = \tempnam(\sys_get_temp_dir(), 'recognize-fakenode-');
		$script = <<<'SCRIPT'
#!/bin/sh
if [ "$1" = "--version" ]; then
	echo "fake-node 1.0.0"
	exit 0
fi
echo "$PWD :: $*" >> "LOGFILE_PATH"
case "$*" in
	*tfjs-node-gpu*)
		if [ "$RECOGNIZE_TEST_GPU_FAIL" = "1" ]; then
			exit 1
		fi
		;;
	*tfjs-node/scripts/install.js*)
		if [ "$RECOGNIZE_TEST_CPU_FAIL" = "1" ]; then
			exit 1
		fi
		;;
esac
exit 0
SCRIPT;
		\file_put_contents($this->fakeNode, \str_replace('LOGFILE_PATH', $this->logFile, $script));
		\chmod($this->fakeNode, 0755);

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig->method('getAppValueString')->willReturnCallback(
			fn (string $key): string => $key === 'node_binary' ? $this->fakeNode : '',
		);
		$this->output = $this->createMock(IOutput::class);
	}

	protected function tearDown(): void {
		\unlink($this->logFile);
		\unlink($this->fakeNode);
		parent::tearDown();
	}

	private function createRepairStep(): InstallDeps {
		$systemConfig = $this->createMock(IConfig::class);
		$systemConfig->method('getSystemValueString')->willReturn('');
		$binaryFinder = $this->createMock(IBinaryFinder::class);
		$binaryFinder->method('findBinaryPath')->willReturn(false);

		return new InstallDeps(
			$this->appConfig,
			$systemConfig,
			$this->createMock(IClientService::class),
			$this->createMock(LoggerInterface::class),
			$binaryFinder,
		);
	}

	/**
	 * @return list<string> the install scripts that were executed, one per line
	 */
	private function executedInstallScripts(): array {
		if (!\is_file($this->logFile)) {
			return [];
		}
		$lines = \file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		return \array_values(\array_filter($lines, static fn (string $line): bool => \str_contains($line, 'install.js')));
	}

	private function createBinding(string $moduleDir): void {
		$dir = $this->appRoot . '/node_modules/' . $moduleDir . '/lib/9.9.9';
		\mkdir($dir, 0755, true);
		\file_put_contents($dir . '/tfjs_binding.node', '');
	}

	private function createFfmpegBinary(): void {
		$ffmpeg = $this->appRoot . '/node_modules/ffmpeg-static/ffmpeg';
		\file_put_contents($ffmpeg, "#!/bin/sh\nexit 0\n");
		\chmod($ffmpeg, 0755);
	}

	/**
	 * Remove a fixture file and any directories the test created for it.
	 */
	private function removeFixture(string $path): void {
		if (\is_file($path)) {
			\unlink($path);
		}
		$dir = \dirname($path);
		while ($dir !== $this->appRoot . '/node_modules' && \is_dir($dir) && \count(\scandir($dir)) === 2) {
			if (!\rmdir($dir)) {
				break;
			}
			$dir = \dirname($dir);
		}
	}

	private function assertAllArtifactsMissing(): void {
		if ($this->nativeBindingExists('@tensorflow/tfjs-node')
			|| $this->nativeBindingExists('@tensorflow/tfjs-node-gpu')
			|| $this->ffmpegExists()) {
			$this->markTestSkipped('Binaries are already installed in this environment');
		}
	}

	public function testAllInstallersRunWhenArtifactsAreMissing(): void {
		$this->assertAllArtifactsMissing();

		$this->createRepairStep()->run($this->output);

		$scripts = $this->executedInstallScripts();
		$this->assertCount(3, $scripts);
		$this->assertStringContainsString('tfjs-node/scripts/install.js', $scripts[0]);
		$this->assertStringContainsString('tfjs-node-gpu/scripts/install.js', $scripts[1]);
		$this->assertStringContainsString('ffmpeg-static/install.js', $scripts[2]);
	}

	public function testGpuFailureDoesNotPreventFfmpegInstall(): void {
		$this->assertAllArtifactsMissing();
		\putenv('RECOGNIZE_TEST_GPU_FAIL=1');

		try {
			$this->createRepairStep()->run($this->output);
		} finally {
			\putenv('RECOGNIZE_TEST_GPU_FAIL');
		}

		$scripts = $this->executedInstallScripts();
		$this->assertCount(3, $scripts, 'A failing GPU install must not prevent the ffmpeg install');
		$this->assertStringContainsString('tfjs-node-gpu/scripts/install.js', $scripts[1]);
		$this->assertStringContainsString('ffmpeg-static/install.js', $scripts[2]);
	}

	public function testCpuFailureDoesNotPreventOtherInstalls(): void {
		$this->assertAllArtifactsMissing();
		\putenv('RECOGNIZE_TEST_CPU_FAIL=1');

		try {
			$this->createRepairStep()->run($this->output);
		} finally {
			\putenv('RECOGNIZE_TEST_CPU_FAIL');
		}

		$scripts = $this->executedInstallScripts();
		$this->assertCount(3, $scripts, 'A failing CPU install must not prevent the GPU and ffmpeg installs');
		$this->assertStringContainsString('tfjs-node-gpu/scripts/install.js', $scripts[1]);
		$this->assertStringContainsString('ffmpeg-static/install.js', $scripts[2]);
	}

	public function testInstalledArtifactsAreNotDownloadedAgain(): void {
		$fixtures = [
			$this->appRoot . '/node_modules/@tensorflow/tfjs-node/lib/9.9.9/tfjs_binding.node',
			$this->appRoot . '/node_modules/@tensorflow/tfjs-node-gpu/lib/9.9.9/tfjs_binding.node',
			$this->appRoot . '/node_modules/ffmpeg-static/ffmpeg',
		];
		try {
			$this->createBinding('@tensorflow/tfjs-node');
			$this->createBinding('@tensorflow/tfjs-node-gpu');
			$this->createFfmpegBinary();

			$this->createRepairStep()->run($this->output);

			$this->assertSame([], $this->executedInstallScripts(), 'Installed artifacts must not be downloaded again');
		} finally {
			foreach ($fixtures as $fixture) {
				$this->removeFixture($fixture);
			}
		}
	}

	public function testOnlyMissingGpuBindingIsDownloaded(): void {
		if ($this->nativeBindingExists('@tensorflow/tfjs-node-gpu')) {
			$this->markTestSkipped('GPU binaries are already installed in this environment');
		}
		$fixtures = [
			$this->appRoot . '/node_modules/@tensorflow/tfjs-node/lib/9.9.9/tfjs_binding.node',
			$this->appRoot . '/node_modules/ffmpeg-static/ffmpeg',
		];
		try {
			$this->createBinding('@tensorflow/tfjs-node');
			$this->createFfmpegBinary();

			$this->createRepairStep()->run($this->output);

			$scripts = $this->executedInstallScripts();
			$this->assertCount(1, $scripts);
			$this->assertStringContainsString('tfjs-node-gpu/scripts/install.js', $scripts[0]);
		} finally {
			foreach ($fixtures as $fixture) {
				$this->removeFixture($fixture);
			}
		}
	}

	private function nativeBindingExists(string $moduleDir): bool {
		$bindings = \glob($this->appRoot . '/node_modules/' . $moduleDir . '/lib/*/tfjs_binding.node');
		return $bindings !== false && \count($bindings) > 0;
	}

	private function ffmpegExists(): bool {
		$ffmpeg = $this->appRoot . '/node_modules/ffmpeg-static/ffmpeg';
		return \is_file($ffmpeg) && \is_executable($ffmpeg);
	}
}
