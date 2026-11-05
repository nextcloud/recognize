<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);
namespace OCA\Recognize\Tests\AuditLog;

use OCA\Recognize\AuditLog\Operation;
use PHPUnit\Framework\TestCase;

// Not \Test\TestCase: its tearDownAfterClass() clears the file hooks that ClassifierTest relies on
final class OperationTest extends TestCase {
	public static function operationProvider(): array {
		return array_map(static fn (Operation $operation): array => [$operation], Operation::cases());
	}

	/**
	 * @dataProvider operationProvider
	 */
	public function testOperationFormat(Operation $operation): void {
		$this->assertMatchesRegularExpression('/^recognize\.[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $operation->value);
	}

	/**
	 * @dataProvider operationProvider
	 */
	public function testOperationHasDescription(Operation $operation): void {
		$this->assertNotSame('', trim($operation->description()));
	}
}
