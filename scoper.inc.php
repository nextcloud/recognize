<?php

declare(strict_types=1);

// scoper.inc.php

use Symfony\Component\Finder\Finder;

return [
	'finders' => [
		Finder::create()
			->files()
			->exclude([
				'bin',
				'bamarni',
				'nextcloud',
				'symfony',
				'psr'
			])
			->in('.'),
		],
	'patchers' => [
		static function (string $filePath, string $prefix, string $content): string {
			//
			// PHP-Parser patch conditions for file targets
			//
			if (str_contains($filePath, '/rubix/')) {
				return preg_replace(
					'%([ |<{:,])\\\\Rubix\\\\ML%',
					'$1\\\\OCA\\\\Recognize\\\\Vendor\\\\Rubix\\\\ML',
					$content
				);
			}

			return $content;
		},
	],
];
