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

use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RegisterBinary implements IRepairStep
{

    public const VERSION = 'v14.9.0';

    /** @var IConfig */
    protected $config;

    public function __construct(IConfig $config)
    {
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'Register the node binary';
    }

    public function run(IOutput $output): void
    {
        $binaryDir = dirname(__DIR__, 2) . '/bin/';

        if (PHP_INT_SIZE === 8) {
            $binaryPath = $binaryDir . 'node-' . self::VERSION . '-linux-arm64';
            $version = $this->testBinary($binaryPath);
            if ($version === null) {
                $binaryPath = $binaryDir . 'node-' . self::VERSION . '-linux-x64';
                $version = $this->testBinary($binaryPath);
                if ($version === null) {
                    $output->warning('Failed to read version from node binary');
                }
            }
        } else {
            $output->warning('Can only run node.js on linux arm64 and linux x64');
            return;
        }

        // Write the app config
        $this->config->setAppValue('recognize', 'node_binary', $binaryPath);
    }

    protected function testBinary(string $binaryPath): ?string
    {
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
}
