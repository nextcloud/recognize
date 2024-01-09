<?php

declare(strict_types=1);

// scoper.inc.php

return [
	'patchers' => [
		static function (string $filePath, string $prefix, string $content): string {
			//
			// PHP-Parser patch conditions for file targets
			//
			if (str_contains($filePath, '/rubix/')) {
				return preg_replace(
					'%([ |<{:,])\\\\Rubix\\\\ML%',
					'$1\\\\OCA\\\\Recognize\\\\Rubix\\\\ML',
					$content
				);
			}

			return $content;
		},
	],
];
