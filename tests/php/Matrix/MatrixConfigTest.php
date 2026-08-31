<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix;

use OCA\Talk\Matrix\MatrixConfig;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class MatrixConfigTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private IGroupManager&MockObject $groupManager;
	private MatrixConfig $config;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->config = new MatrixConfig($this->appConfig, $this->groupManager);
	}

	public function testCanUserLinkRespectsFeatureAndGroups(): void {
		$user = $this->createMock(IUser::class);
		$this->groupManager->method('getUserGroupIds')->willReturn(['staff']);

		$this->appConfig->method('getAppValueBool')->with(MatrixConfig::ENABLED, false)->willReturn(false);
		self::assertFalse($this->config->canUserLink($user));
		self::assertFalse($this->config->canUserLink(null));
	}

	public function testCanUserLinkWithGroupRestriction(): void {
		$user = $this->createMock(IUser::class);
		$this->groupManager->method('getUserGroupIds')->willReturn(['staff']);
		$this->appConfig->method('getAppValueBool')->willReturn(true);
		$this->appConfig->method('getAppValueArray')->with(MatrixConfig::ALLOWED_GROUPS)->willReturnOnConsecutiveCalls([], ['admin'], ['admin', 'staff']);

		self::assertTrue($this->config->canUserLink($user), 'no restriction');
		self::assertFalse($this->config->canUserLink($user), 'not in group');
		self::assertTrue($this->config->canUserLink($user), 'in group');
	}

	public function testIntervalsAreClamped(): void {
		$this->appConfig->method('getAppValueInt')->willReturnCallback(static fn (string $key, int $default = 0) => match ($key) {
			MatrixConfig::SYNC_INTERVAL => 2,
			MatrixConfig::IDLE_SYNC_INTERVAL => 5,
			default => $default,
		});
		self::assertSame(10, $this->config->getSyncInterval());
		self::assertSame(10, $this->config->getIdleSyncInterval(), 'idle interval is never shorter than the active one');
	}
}
