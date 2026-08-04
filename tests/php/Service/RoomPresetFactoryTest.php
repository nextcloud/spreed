<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Room;
use OCA\Talk\RoomPresets\Announcement;
use OCA\Talk\RoomPresets\Channel;
use OCA\Talk\Service\RoomPresetFactory;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class RoomPresetFactoryTest extends TestCase {
	protected IAppConfig&MockObject $appConfig;
	protected IGroupManager&MockObject $groupManager;
	protected LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig->method('getAppValueInt')
			->with('start_calls', Room::START_CALL_EVERYONE)
			->willReturn(Room::START_CALL_EVERYONE);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	protected function getFactory(?string $userId): RoomPresetFactory {
		return new RoomPresetFactory(
			$this->appConfig,
			$this->groupManager,
			$this->logger,
			$userId,
		);
	}

	public function testAnnouncementIsAvailableToAdmins(): void {
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$presets = $this->getFactory('admin')->getPresets();

		$this->assertArrayHasKey(Announcement::getIdentifier(), $presets);
	}

	public function testAnnouncementIsNotAvailableToRegularUsers(): void {
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with('user')
			->willReturn(false);

		$presets = $this->getFactory('user')->getPresets();

		$this->assertArrayNotHasKey(Announcement::getIdentifier(), $presets);
		$this->assertArrayHasKey(Channel::getIdentifier(), $presets, 'Channels are still available to regular users');
	}

	public function testAnnouncementIsNotAvailableToGuests(): void {
		$this->groupManager->expects($this->never())
			->method('isAdmin');

		$presets = $this->getFactory(null)->getPresets();

		$this->assertArrayNotHasKey(Announcement::getIdentifier(), $presets);
	}
}
