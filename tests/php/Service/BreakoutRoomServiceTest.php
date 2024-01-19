<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config;
use OCA\Talk\Manager;
use OCA\Talk\Service\BreakoutRoomService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\Notification\IManager as INotificationManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class BreakoutRoomServiceTest extends TestCase {
	private BreakoutRoomService $service;

	/** @var Config|MockObject */
	private $config;
	/** @var Manager|MockObject */
	private $manager;
	/** @var RoomService|MockObject */
	private $roomService;
	/** @var ParticipantService|MockObject */
	private $participantService;
	/** @var ChatManager|MockObject */
	private $chatManager;
	/** @var INotificationManager|MockObject */
	private $notificationManager;
	/** @var ITimeFactory|MockObject */
	protected $timeFactory;
	/** @var IEventDispatcher|MockObject */
	private $dispatcher;
	/** @var IL10N|MockObject */
	private $l;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(Config::class);
		$this->manager = $this->createMock(Manager::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->l = $this->createMock(IL10N::class);
		$this->service = new BreakoutRoomService(
			$this->config,
			$this->manager,
			$this->roomService,
			$this->participantService,
			$this->chatManager,
			$this->notificationManager,
			$this->timeFactory,
			$this->dispatcher,
			$this->l
		);
	}
	public static function dataParseAttendeeMap(): array {
		return [
			'Empty string means no map' => ['', 3, [], false],
			'Empty array means no map' => ['[]', 3, [], false],
			'OK' => [json_encode([1 => 1, 13 => 0, 42 => 2]), 3, [1 => 1, 13 => 0, 42 => 2], false],
			'Not an array' => ['"hello"', 3, null, true],
			'Room above max' => [json_encode([1 => 0, 13 => 1, 42 => 2]), 2, null, true],
			'Room below min' => [json_encode([1 => 0, 13 => -1, 42 => 2]), 3, null, true],
			'Room not int' => [json_encode([1 => 0, 13 => 'foo', 42 => 2]), 3, null, true],
			'Room null' => [json_encode([1 => 0, 13 => null, 42 => 2]), 3, null, true],
			'Attendee not int' => [json_encode([1 => 0, 'foo' => 1, 42 => 2]), 3, null, true],
			'Attendee negative' => [json_encode([1 => 0, -13 => 1, 42 => 2]), 3, null, true],
			'Attendee zero' => [json_encode([1 => 0, 0 => 1, 42 => 2]), 3, null, true],
		];
	}

	/**
	 * @dataProvider dataParseAttendeeMap
	 */
	public function testParseAttendeeMap(string $json, int $max, ?array $expected, bool $throws): void {
		if ($throws) {
			$this->expectException(\InvalidArgumentException::class);
		}

		$actual = self::invokePrivate($this->service, 'parseAttendeeMap', [$json, $max]);
		$this->assertEquals($expected, $actual);
	}
}
