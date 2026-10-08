<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix;

use OCA\Talk\Matrix\MatrixConfig;
use OCP\AppFramework\Services\IAppConfig;
use Test\TestCase;

class MatrixConfigTest extends TestCase {
	public function testGetAllowedGroupIdsDropsInvalidEntries(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueArray')->with(MatrixConfig::ALLOWED_GROUPS)->willReturn([3 => 'admin', 4 => 42, 5 => 'staff']);

		self::assertSame(['admin', 'staff'], (new MatrixConfig($appConfig))->getAllowedGroupIds());
	}
}
