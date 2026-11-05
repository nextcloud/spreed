<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\SetupCheck;

use OCA\Talk\Config;
use OCA\Talk\SetupCheck\ExpensiveRepairSteps;
use OCP\AppFramework\Services\IAppConfig;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\SetupCheck\SetupResult;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class ExpensiveRepairStepsTest extends TestCase {
	public static function dataRun(): array {
		return [
			'never performed' => [0, 42, SetupResult::WARNING],
			'outdated' => [ExpensiveRepairSteps::VERSION - 1, 42, SetupResult::WARNING],
			'performed' => [ExpensiveRepairSteps::VERSION, 42, SetupResult::SUCCESS],
			'never performed without attachments' => [0, false, SetupResult::SUCCESS],
		];
	}

	#[DataProvider('dataRun')]
	public function testRun(int $performed, int|false $attachmentId, string $severity): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueInt')
			->with(Config::EXPENSIVE_REPAIR_PERFORMED)
			->willReturn($performed);

		$result = $this->createMock(IResult::class);
		$result->method('fetchOne')
			->willReturn($attachmentId);
		$query = $this->createMock(IQueryBuilder::class);
		$query->method('select')->willReturnSelf();
		$query->method('from')->willReturnSelf();
		$query->method('setMaxResults')->willReturnSelf();
		$query->method('executeQuery')->willReturn($result);
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('getQueryBuilder')
			->willReturn($query);

		$check = new ExpensiveRepairSteps($appConfig, $connection, $this->createMock(IL10N::class));
		$this->assertSame($severity, $check->run()->getSeverity());
	}
}
