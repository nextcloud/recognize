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

use OCA\Recognize\Service\SettingsService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use function Safe\rename;
use function Safe\scandir;

final class MoveDefaultModelFolder implements IRepairStep {

	public function __construct(
		private SettingsService $settingsService,
		private LoggerInterface $logger,
	) {
	}

	public function getName(): string {
		return 'Try to move the default Model Folder';
	}

	public function run(IOutput $output): void {
		$oldModelTargetPath = __DIR__ . '/../../models';
		$oldModelArchivePath = __DIR__ . '/../../models.tar.gz';
		$newPath = $this->settingsService->getSetting('models_target_path');
		$newModelTargetPath = $newPath . '/models';
		$newModelArchivePath = $newPath . '/models.tar.gz';

		if (is_dir($oldModelTargetPath)) {
			$filesToMove = scandir($oldModelTargetPath);
			$filesToMove = array_filter($filesToMove, fn ($value) => $value !== '.' && $value === '..');
			$filesToMove = array_map(fn ($value) => $oldModelTargetPath.'/'.$value, $filesToMove);
			mkdir($newModelTargetPath);
			foreach ($filesToMove as $file) {
				rename($file, $newModelTargetPath.'/'.basename($file));
			}
		}

		if (is_file($oldModelArchivePath)) {
			rename($oldModelArchivePath, $newModelArchivePath);
		}
	}
}
