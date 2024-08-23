<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OC\EmojiHelper;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\RoomService;
use OCA\Theming\Service\ThemesService;
use OCP\Files\IAppData;
use OCP\IAvatarManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use OCP\Server;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * @group DB
 */
class AvatarServiceTest extends TestCase {
	protected IAppData&MockObject $appData;
	protected IL10N&MockObject $l;
	protected IURLGenerator&MockObject $url;
	protected ISecureRandom&MockObject $random;
	protected RoomService&MockObject $roomService;
	protected IAvatarManager&MockObject $avatarManager;
	protected EmojiHelper $emojiHelper;
	protected ThemesService $themesService;
	protected ?AvatarService $service = null;

	public function setUp(): void {
		parent::setUp();

		$this->appData = $this->createMock(IAppData::class);
		$this->l = $this->createMock(IL10N::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->avatarManager = $this->createMock(IAvatarManager::class);
		$this->emojiHelper = Server::get(EmojiHelper::class);
		$this->themesService = $this->createMock(ThemesService::class);
		$this->service = new AvatarService(
			$this->appData,
			$this->l,
			$this->url,
			$this->random,
			$this->roomService,
			$this->avatarManager,
			$this->emojiHelper,
			$this->themesService,
		);
	}

	public static function dataGetAvatarVersion(): array {
		return [
			['', 'STRING WITH 8 CHARS'],
			['1', '1'],
			['1.png', '1'],
		];
	}

	/**
	 * @dataProvider dataGetAvatarVersion
	 */
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

	public static function dataGetFirstCombinedEmoji(): array {
		return [
			['👋 Hello', '👋'],
			['Only leading emojis 🚀', ''],
			['👩🏽‍💻👩🏻‍💻👨🏿‍💻 Only one, but with all attributes', '👩🏽‍💻'],
		];
	}

	/**
	 * @dataProvider dataGetFirstCombinedEmoji
	 */
	public function testGetFirstCombinedEmoji(string $roomName, string $avatarEmoji): void {
		$this->assertSame($avatarEmoji, self::invokePrivate($this->service, 'getFirstCombinedEmoji', [$roomName]));
	}

	public static function dataGetAvatarUrl(): array {
		return [
			[['default', 'light', 'dark'], true],
			[['default', 'light', 'dark-contrast'], true],
			[['default', 'light'], false],
		];
	}

	/**
	 * @dataProvider dataGetAvatarUrl
	 */
	public function testGetAvatarUrl(array $enabledThemes, bool $darkTheme) {
		$room = $this->createMock(Room::class);

		$this->themesService
			->method('getEnabledThemes')
			->willReturn($enabledThemes);

		$this->url
			->expects($this->once())
			->method('linkToOCSRouteAbsolute')
			->with('spreed.Avatar.getAvatar', [
				'token' => '',
				'apiVersion' => 'v1',
				'darkTheme' => $darkTheme,
				'v' => $this->service->getAvatarVersion($room),
			])->willReturn('url');

		$this->assertEquals('url', $this->service->getAvatarUrl($room));
	}
}
