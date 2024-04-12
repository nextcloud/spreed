<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

namespace OCA\Talk\Tests\php\Service;

use OC\EmojiHelper;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\RoomService;
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
		$this->service = new AvatarService(
			$this->appData,
			$this->l,
			$this->url,
			$this->random,
			$this->roomService,
			$this->avatarManager,
			$this->emojiHelper,
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
}
