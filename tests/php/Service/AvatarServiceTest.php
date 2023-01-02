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

use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\RoomService;
use OCP\Files\IAppData;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AvatarServiceTest extends TestCase {
	private AvatarService $service;
	/** @var IAppData|MockObject */
	private $appData;
	/** @var IL10N|MockObject */
	private $l;
	/** @var IConfig|MockObject */
	private $config;
	/** @var IURLGenerator|MockObject */
	private $url;
	/** @var ISecureRandom|MockObject */
	private $random;
	/** @var RoomService|MockObject */
	private $roomService;
	/** @var IAvatarManager|MockObject */
	private $avatarManager;

	public function setUp(): void {
		parent::setUp();

		$this->appData = $this->createMock(IAppData::class);
		$this->l = $this->createMock(IL10N::class);
		$this->config = $this->createMock(IConfig::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->avatarManager = $this->createMock(IAvatarManager::class);
		$this->service = new AvatarService(
			$this->appData,
			$this->l,
			$this->config,
			$this->url,
			$this->random,
			$this->roomService,
			$this->avatarManager
		);
	}

	/**
	 * @dataProvider dataGetAvatarVersion
	 */
	public function testGetAvatarVersion(string $avatar, string $expected): void {
		/** @var Room|MockObject $room */
		$room = $this->createMock(Room::class);
		$room->expects($this->once())
			->method('getAvatar')
			->willReturn($avatar);
		$actual = $this->service->getAvatarVersion($room);
		$this->assertEquals($expected, $actual);
	}

	public function dataGetAvatarVersion(): array {
		return [
			['', ''],
			['1', '1'],
			['1.png', '1'],
		];
	}
}
