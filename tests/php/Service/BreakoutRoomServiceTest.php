<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class BreakoutRoomServiceTest extends TestCase {
	protected Config&MockObject $config;
	protected Manager&MockObject $manager;
	protected RoomService&MockObject $roomService;
	protected ParticipantService&MockObject $participantService;
	protected ChatManager&MockObject $chatManager;
	protected INotificationManager&MockObject $notificationManager;
	protected ITimeFactory&MockObject $timeFactory;
	protected IEventDispatcher&MockObject $dispatcher;
	protected IL10N&MockObject $l;
	protected BreakoutRoomService $service;

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

	#[DataProvider('dataParseAttendeeMap')]
	public function testParseAttendeeMap(string $json, int $max, ?array $expected, bool $throws): void {
		if ($throws) {
			$this->expectException(\InvalidArgumentException::class);
		}

		$actual = self::invokePrivate($this->service, 'parseAttendeeMap', [$json, $max]);
		$this->assertEquals($expected, $actual);
	}
}
