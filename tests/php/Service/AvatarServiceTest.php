<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\EmojiService;
use OCA\Talk\Service\RoomService;
use OCP\Config\IUserConfig;
use OCP\Files\IAppData;
use OCP\Files\IFilenameValidator;
use OCP\IAvatarManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

#[Group('DB')]
class AvatarServiceTest extends TestCase {
	protected IAppData&MockObject $appData;
	protected IUserConfig&MockObject $userConfig;
	protected IL10N&MockObject $l;
	protected IURLGenerator&MockObject $url;
	protected ISecureRandom&MockObject $random;
	protected RoomService&MockObject $roomService;
	protected IAvatarManager&MockObject $avatarManager;
	protected EmojiService $emojiService;
	protected IFilenameValidator $filenameValidator;
	protected ?AvatarService $service = null;

	public function setUp(): void {
		parent::setUp();

		$this->appData = $this->createMock(IAppData::class);
		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->l = $this->createMock(IL10N::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->avatarManager = $this->createMock(IAvatarManager::class);
		$this->emojiService = Server::get(EmojiService::class);
		$this->filenameValidator = Server::get(IFilenameValidator::class);
		$this->service = new AvatarService(
			$this->appData,
			$this->userConfig,
			$this->l,
			$this->url,
			$this->random,
			$this->roomService,
			$this->avatarManager,
			$this->emojiService,
			$this->filenameValidator,
		);
	}

	public function testGetUserAvatarVersionsFillsInUsersWithoutAVersion(): void {
		$this->userConfig->method('getValuesByUsers')
			->with('avatar', 'version', null, ['alice', 'bob'])
			->willReturn(['alice' => 5]);

		$this->assertSame(
			['alice' => '5', 'bob' => '0'],
			$this->service->getUserAvatarVersions(['alice', 'bob']),
		);
	}

	public function testGetUserAvatarVersionsSkipsAnEmptyList(): void {
		$this->userConfig->expects($this->never())->method('getValuesByUsers');

		$this->assertSame([], $this->service->getUserAvatarVersions([]));
	}

	public static function dataGetAvatarVersion(): array {
		return [
			['', 'STRING WITH 8 CHARS'],
			['1', '1'],
			['1.png', '1'],
		];
	}

	#[DataProvider('dataGetAvatarVersion')]
	public function testGetAvatarVersion(string $avatar, string $expected): void {
		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		$room->method('getAvatar')
			->willReturn($avatar);
		$actual = $this->service->getAvatarVersion($room);
		if ($expected === 'STRING WITH 8 CHARS') {
			$this->assertEquals(8, strlen($actual));
		} else {
			$this->assertEquals($expected, $actual);
		}
	}
}
