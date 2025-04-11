<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\DashboardService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Calendar\ICalendar;
use OCP\Calendar\IManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * @group DB
 */
class DashboardServiceTest extends TestCase {
	protected Manager&MockObject $manager;
	protected IManager&MockObject $calendarManager;
	protected ITimeFactory&MockObject $timeFactory;
	protected LoggerInterface&MockObject $logger;
	protected ParticipantService&MockObject $participantService;
	protected RoomService&MockObject $roomService;
	protected DashboardService $service;
	protected string $userId = 'user1';

	public static function calendarData() {
		$date = new \DateTimeImmutable('tomorrow');
		return [
			[
				[['objects' => [['DTSTART' => [$date], 'LOCATION' => ['https://example.tld/call/12345?_ladida']]]]]
			],
			[
				[['objects' => [['DTSTART' => [$date], 'LOCATION' => ['https://example.tld/call/12345']]]]]
			],
			[
				[['objects' => [['DTSTART' => [$date], 'LOCATION' => ['https://example.tld/call/12345#_abcdefg']]]]]
			],
			[
				[['objects' => [['DTSTART' => [$date], 'LOCATION' => ['https://example.tld/call/12345#_geht?abcdefg']]]]]
			],
		];
	}

	public function setUp(): void {
		parent::setUp();

		$this->manager = $this->createMock(Manager::class);
		$this->calendarManager = $this->createMock(IManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->service = new DashboardService($this->manager,
			$this->calendarManager,
			$this->timeFactory,
			$this->logger,
			$this->participantService,
			$this->roomService,
		);
	}

	public function testNoCalendars(): void {
		$this->calendarManager->expects($this->once())
			->method('getCalendarsForPrincipal')
			->willReturn([]);
		$this->timeFactory->expects($this->never())
			->method('getDateTime');
		$this->manager->expects($this->never())
			->method('getRoomForUserByToken');
		$this->logger->expects($this->never())
			->method('debug');
		$this->participantService->expects($this->never())
			->method('getParticipant');

		$actual = $this->service->getEvents($this->userId);
		$this->assertEmpty($actual);
	}

	public function testNoEvents(): void {
		$calendar = $this->createMock(ICalendar::class);
		$this->calendarManager->expects($this->once())
			->method('getCalendarsForPrincipal')
			->willReturn([$calendar]);
		$this->timeFactory->expects($this->exactly(2))
			->method('getDateTime')
			->willReturn(new \DateTime());
		$calendar->expects($this->once())
			->method('search')
			->willReturn([]);
		$this->manager->expects($this->never())
			->method('getRoomForUserByToken');
		$this->logger->expects($this->never())
			->method('debug');
		$this->participantService->expects($this->never())
			->method('getParticipant');

		$actual = $this->service->getEvents($this->userId);
		$this->assertEmpty($actual);
	}

	public function testEventNoLocation(): void {
		$calData = [['objects' => [['DTSTART' => [new \DateTime('tomorrow')]]]]];
		$calendar = $this->createMock(ICalendar::class);
		$calendar->expects($this->once())
			->method('search')
			->willReturn($calData);
		$this->calendarManager->expects($this->once())
			->method('getCalendarsForPrincipal')
			->willReturn([$calendar]);
		$this->timeFactory->expects($this->exactly(2))
			->method('getDateTime')
			->willReturnOnConsecutiveCalls(new \DateTime(), new \DateTime());
		$this->roomService->expects($this->never())
			->method('parseRoomTokenFromUrl');
		$this->manager->expects($this->never())
			->method('getRoomForUserByToken');
		$this->logger->expects($this->never())
			->method('debug');
		$this->participantService->expects($this->never())
			->method('getParticipant');

		$actual = $this->service->getEvents($this->userId);
		$this->assertEmpty($actual);
	}

	public function testEventNoCallLocation(): void {
		$calData = [['objects' => [['DTSTART' => [new \DateTime('tomorrow')]], ['LOCATION' => 'Just a regular location']]]];
		$calendar = $this->createMock(ICalendar::class);
		$calendar->expects($this->once())
			->method('search')
			->willReturn($calData);
		$this->calendarManager->expects($this->once())
			->method('getCalendarsForPrincipal')
			->willReturn([$calendar]);
		$this->timeFactory->expects($this->exactly(2))
			->method('getDateTime')
			->willReturnOnConsecutiveCalls(new \DateTime(), new \DateTime());
		$this->roomService->expects($this->never())
			->method('parseRoomTokenFromUrl');
		$this->manager->expects($this->never())
			->method('getRoomForUserByToken');
		$this->logger->expects($this->never())
			->method('debug');
		$this->participantService->expects($this->never())
			->method('getParticipant');

		$actual = $this->service->getEvents($this->userId);
		$this->assertEmpty($actual);
	}

	/**
	 * @dataProvider calendarData
	 */
	public function testEventRoomNotFound($calData): void {
		$calendar = $this->createMock(ICalendar::class);
		$this->calendarManager->expects($this->once())
			->method('getCalendarsForPrincipal')
			->willReturn([$calendar]);
		$this->timeFactory->expects($this->exactly(2))
			->method('getDateTime')
			->willReturnOnConsecutiveCalls(new \DateTime(), new \DateTime());
		$calendar->expects($this->once())
			->method('search')
			->willReturn($calData);
		$this->roomService->expects($this->once())
			->method('parseRoomTokenFromUrl');
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->willThrowException(new RoomNotFoundException());
		$this->logger->expects($this->once())
			->method('debug');
		$this->participantService->expects($this->never())
			->method('getParticipant');

		$actual = $this->service->getEvents($this->userId);
		$this->assertEmpty($actual);
	}

	/**
	 * @dataProvider calendarData
	 */
	public function testEventParticipantNotFound($calData): void {
		$calendar = $this->createMock(ICalendar::class);
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')
			->willReturn(Room::OBJECT_TYPE_EVENT);
		$this->calendarManager->expects($this->once())
			->method('getCalendarsForPrincipal')
			->willReturn([$calendar]);
		$this->timeFactory->expects($this->exactly(2))
			->method('getDateTime')
			->willReturnOnConsecutiveCalls(new \DateTime(), new \DateTime());
		$calendar->expects($this->once())
			->method('search')
			->willReturn($calData);
		$this->roomService->expects($this->once())
			->method('parseRoomTokenFromUrl');
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->willReturn($room);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->willThrowException(new ParticipantNotFoundException());
		$this->logger->expects($this->once())
			->method('debug');

		$actual = $this->service->getEvents($this->userId);
		$this->assertEmpty($actual);
	}

	/**
	 * @dataProvider calendarData
	 */
	public function testEventSingleEvent($calData): void {
		$calendar = $this->createMock(ICalendar::class);
		$room = $this->createMock(Room::class);
		$participant = $this->createMock(Participant::class);
		$participant->method('getRoom')
			->willReturn($room);
		$room->method('getObjectType')
			->willReturn(Room::OBJECT_TYPE_EVENT);
		$this->calendarManager->expects($this->once())
			->method('getCalendarsForPrincipal')
			->willReturn([$calendar]);
		$this->timeFactory->expects($this->exactly(2))
			->method('getDateTime')
			->willReturnOnConsecutiveCalls(new \DateTime(), new \DateTime());
		$calendar->expects($this->once())
			->method('search')
			->willReturn($calData);
		$this->roomService->expects($this->once())
			->method('parseRoomTokenFromUrl');
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->willReturn($room);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->willReturn($participant);
		$this->logger->expects($this->never())
			->method('debug');

		$actual = $this->service->getEvents($this->userId);
		$this->assertCount(1, $actual);
	}

	public function testEventTwoEventsSorting(): void {
		$calData = [
			['objects' => [['DTSTART' => [new \DateTime('+3 days')], 'LOCATION' => ['https://example.tld/call/12345?_ladida']]]],
			['objects' => [['DTSTART' => [new \DateTime('+2 days')], 'LOCATION' => ['https://example.tld/call/789456']]]]
		];

		$calendar = $this->createMock(ICalendar::class);
		$room1 = $this->createMock(Room::class);
		$room2 = $this->createMock(Room::class);
		$participant1 = $this->createMock(Participant::class);
		$participant1->method('getRoom')
			->willReturn($room1);
		$participant2 = $this->createMock(Participant::class);
		$participant2->method('getRoom')
			->willReturn($room2);
		$room1->method('getObjectType')
			->willReturn(Room::OBJECT_TYPE_EVENT);
		$room2->method('getObjectType')
			->willReturn(Room::OBJECT_TYPE_EVENT);
		$room1->method('getObjectId')
			->willReturn('1');
		$room2->method('getObjectId')
			->willReturn('0');
		$room1->method('getId')
			->willReturn(1);
		$room2->method('getId')
			->willReturn(0);
		$this->calendarManager->expects($this->once())
			->method('getCalendarsForPrincipal')
			->willReturn([$calendar]);
		$this->timeFactory->expects($this->exactly(2))
			->method('getDateTime')
			->willReturnOnConsecutiveCalls(new \DateTime(), new \DateTime());
		$calendar->expects($this->once())
			->method('search')
			->willReturn($calData);
		$this->roomService->expects($this->exactly(2))
			->method('parseRoomTokenFromUrl');
		$this->manager->expects($this->exactly(2))
			->method('getRoomForUserByToken')
			->willReturnOnConsecutiveCalls($room1, $room2);
		$this->participantService->expects($this->exactly(2))
			->method('getParticipant')
			->willReturnOnConsecutiveCalls($participant1, $participant2);
		$this->logger->expects($this->never())
			->method('debug');

		$actual = $this->service->getEvents($this->userId);
		$this->assertCount(2, $actual);
		$this->assertEquals('0', $actual[0]->getRoom()->getObjectId());
		$this->assertEquals('1', $actual[1]->getRoom()->getObjectId());
	}
}
