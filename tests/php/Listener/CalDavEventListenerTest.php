<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\DAV\CalDAV\TimezoneService;
use OCA\Talk\Events\ACallEndedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Listener\CalDavEventListener;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Webinary;
use OCP\Calendar\Events\CalendarObjectCreatedEvent;
use OCP\Calendar\Events\CalendarObjectDeletedEvent;
use OCP\Calendar\Events\CalendarObjectUpdatedEvent;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[Group('DB')]
class CalDavEventListenerTest extends TestCase {
	private Manager&MockObject $manager;
	private RoomService&MockObject $roomService;
	private LoggerInterface&MockObject $logger;
	private TimezoneService&MockObject $timezoneService;
	private ParticipantService&MockObject $participantService;
	private string $calData;
	private Participant&MockObject $participant;
	private string $userId;
	private string $userUri;
	private IL10N&MockObject $l10n;
	private CalDavEventListener $listener;

	public static function roomUrl() {
		return [
			['http://talk.example.com/call/12345'],
			['http://talk.example.com/call/12345#message_789456'],
			['http://talk.example.com/call/12345#?message_789456'],
			['http://talk.example.com/call/12345?email=test@example.tld'],
			['http://talk.example.com/call/12345?email=test@example.tld#message_789456'],
			['http://talk.example.com/call/12345?email=test@example.tld#message_789456?email=test@example.tld'],
		];
	}

	public function setUp(): void {
		parent::setUp();

		$this->manager = $this->createMock(Manager::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->timezoneService = $this->createMock(TimezoneService::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->userId = '123';
		$this->userUri = 'principals/users/' . $this->userId;
		$this->l10n = $this->createMock(IL10N::class);
		$this->calData = <<<EOD
BEGIN:VCALENDAR
PRODID:-//IDN nextcloud.com//Calendar app 5.2.0-dev.1//EN
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
CREATED:20250310T171800Z
DTSTAMP:20250310T171819Z
LAST-MODIFIED:20250310T171819Z
SEQUENCE:2
UID:4d336aa1-a29e-4015-b1dd-98e1dae802db
DTSTART;TZID=Europe/Vienna:20250314T100000
DTEND;TZID=Europe/Vienna:20250314T110000
STATUS:CONFIRMED
SUMMARY:Test
LOCATION:{{{LOCATION}}}
END:VEVENT
BEGIN:VTIMEZONE
TZID:Europe/Vienna
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
END:VCALENDAR
EOD;

		$attendee = new Attendee();
		$attendee->setParticipantType(Participant::OWNER);
		$this->participant = $this->createMock(Participant::class);
		$this->participant->method('getAttendee')->willReturn($attendee);

		$this->listener = new CalDavEventListener(
			$this->manager,
			$this->roomService,
			$this->logger,
			$this->timezoneService,
			$this->participantService,
			$this->l10n,
		);
	}

	public function testIsNotCalendarEvent(): void {
		$event = $this->createMock(ACallEndedEvent::class);
		$this->manager->expects(self::never())
			->method('getRoomForUserByToken');
		$this->logger->expects(self::never())
			->method('warning');
		$this->logger->expects(self::never())
			->method('debug');
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::never())
			->method('setObject');
		$this->participantService->expects(self::never())
			->method('getParticipant');
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');
		$this->roomService->expects(self::never())
			->method('hasExistingCalendarEvents');

		$this->listener->handle($event);
	}

	public function testIsCalendarEventNoPrincipal(): void {
		$event = new CalendarObjectCreatedEvent(1, [], [], ['calendardata' => 'justSomeData']);

		$this->logger->expects(self::once())
			->method('debug')
			->with('No principal uri for the event, skipping for calendar event integration');

		$this->listener->handle($event);
	}

	public function testIsCalendarEventSystemCalendar(): void {
		$event = new CalendarObjectCreatedEvent(1, ['principaluri' => 'principals/system/system'], [], ['calendardata' => 'justSomeData']);

		$this->logger->expects(self::once())
			->method('debug')
			->with('System calendar, skipping for calendar event integration');

		$this->listener->handle($event);
	}

	public function testIsCalendarEventNoData(): void {
		$event = new CalendarObjectCreatedEvent(1, ['principaluri' => $this->userUri], [], []);

		$this->logger->expects(self::once())
			->method('debug')
			->with('No calendar data for the event, skipping for calendar event integration');

		$this->listener->handle($event);
	}

	public function testIsCalendarEventNoLocation(): void {
		$event = new CalendarObjectCreatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => 'justSomeData']);

		$this->logger->expects(self::once())
			->method('debug');
		$this->manager->expects(self::never())
			->method('getRoomForUserByToken');
		$this->logger->expects(self::never())
			->method('warning');
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::never())
			->method('setObject');
		$this->participantService->expects(self::never())
			->method('getParticipant');
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');
		$this->roomService->expects(self::never())
			->method('hasExistingCalendarEvents');

		$this->listener->handle($event);
	}

	public function testIsCalendarEventInvalidCalendarData(): void {
		$event = new CalendarObjectCreatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => 'justSomeData\nLOCATION:']);

		$this->logger->expects(self::once())
			->method('warning');
		$this->manager->expects(self::never())
			->method('getRoomForUserByToken');
		$this->logger->expects(self::never())
			->method('debug');
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::never())
			->method('setObject');
		$this->participantService->expects(self::never())
			->method('getParticipant');
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');
		$this->roomService->expects(self::never())
			->method('hasExistingCalendarEvents');

		$this->listener->handle($event);
	}

	public function testNoUrlInLocation(): void {
		$calData = <<<EOD
BEGIN:VCALENDAR
PRODID:-//IDN nextcloud.com//Calendar app 5.2.0-dev.1//EN
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
CREATED:20250310T171800Z
DTSTAMP:20250310T171819Z
LAST-MODIFIED:20250310T171819Z
SEQUENCE:2
UID:4d336aa1-a29e-4015-b1dd-98e1dae802db
DTSTART;TZID=Europe/Vienna:20250314T100000
DTEND;TZID=Europe/Vienna:20250314T110000
STATUS:CONFIRMED
SUMMARY:Test
LOCATION:Donde esta la biblioteca
END:VEVENT
BEGIN:VTIMEZONE
TZID:Europe/Vienna
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
END:VCALENDAR
EOD;
		$event = new CalendarObjectUpdatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);

		$this->logger->expects(self::once())
			->method('debug');
		$this->manager->expects(self::never())
			->method('getRoomForUserByToken');
		$this->logger->expects(self::never())
			->method('warning');
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::never())
			->method('setObject');
		$this->participantService->expects(self::never())
			->method('getParticipant');
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');
		$this->roomService->expects(self::never())
			->method('hasExistingCalendarEvents');

		$this->listener->handle($event);
	}

	#[DataProvider('roomUrl')]
	public function testRoomNotFound(string $roomUrl): void {
		$calData = str_replace('{{{LOCATION}}}', $roomUrl, $this->calData);
		$event = new CalendarObjectUpdatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);

		$this->manager->expects(self::once())
			->method('getRoomForUserByToken')
			->willThrowException(new RoomNotFoundException());
		$this->logger->expects(self::once())
			->method('warning');
		$this->logger->expects(self::never())
			->method('debug');
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::never())
			->method('setObject');
		$this->participantService->expects(self::never())
			->method('getParticipant');
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');
		$this->roomService->expects(self::never())
			->method('hasExistingCalendarEvents');

		$this->listener->handle($event);
	}

	#[DataProvider('roomUrl')]
	public function testUserNotParticipant(string $roomUrl): void {
		$calData = str_replace('{{{LOCATION}}}', $roomUrl, $this->calData);
		$event = new CalendarObjectUpdatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);

		$this->manager->expects(self::once())
			->method('getRoomForUserByToken');
		$this->participantService->expects(self::once())
			->method('getParticipant')
			->willThrowException(new ParticipantNotFoundException());
		$this->logger->expects(self::never())
			->method('warning');
		$this->logger->expects(self::once())
			->method('debug');
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::never())
			->method('setObject');
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');
		$this->roomService->expects(self::never())
			->method('hasExistingCalendarEvents');

		$this->listener->handle($event);
	}

	#[DataProvider('roomUrl')]
	public function testUserNotOwner(string $roomUrl): void {
		$calData = str_replace('{{{LOCATION}}}', $roomUrl, $this->calData);
		$event = new CalendarObjectUpdatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);
		$attendee = new Attendee();
		$attendee->setParticipantType(Participant::USER);
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);

		$this->manager->expects(self::once())
			->method('getRoomForUserByToken');
		$this->participantService->expects(self::once())
			->method('getParticipant')
			->willReturn($participant);
		$this->logger->expects(self::once())
			->method('debug')
			->with("Participant $this->userId is not owner for calendar event integration");
		$this->logger->expects(self::never())
			->method('warning');
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::never())
			->method('setObject');
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');
		$this->roomService->expects(self::never())
			->method('hasExistingCalendarEvents');

		$this->listener->handle($event);
	}

	#[DataProvider('roomUrl')]
	public function testRoomNotEventRoom(string $roomUrl): void {
		$calData = str_replace('{{{LOCATION}}}', $roomUrl, $this->calData);
		$event = new CalendarObjectUpdatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_PHONE_LEGACY);

		$this->manager->expects(self::once())
			->method('getRoomForUserByToken')
			->willReturn($room);
		$this->participantService->expects(self::once())
			->method('getParticipant')
			->willReturn($this->participant);
		$this->logger->expects(self::once())
			->method('debug');
		$this->logger->expects(self::never())
			->method('warning');
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::never())
			->method('setObject');
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');
		$this->roomService->expects(self::never())
			->method('hasExistingCalendarEvents');

		$this->listener->handle($event);
	}

	public function testEventHasRRULE(): void {
		$calData = <<<EOF
BEGIN:VCALENDAR
PRODID:-//IDN nextcloud.com//Calendar app 5.2.0-dev.1//EN
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
CREATED:20250310T175122Z
DTSTAMP:20250310T175146Z
LAST-MODIFIED:20250310T175146Z
SEQUENCE:2
UID:2fb2416e-13f3-4945-936e-28df560b00a2
DTSTART;TZID=Europe/Vienna:20250315T100000
DTEND;TZID=Europe/Vienna:20250315T110000
STATUS:CONFIRMED
LOCATION:https://nextcloud.local/index.php/call/44wd9tvp
RRULE:FREQ=DAILY;UNTIL=20250322T090000Z
DESCRIPTION:Test
END:VEVENT
BEGIN:VTIMEZONE
TZID:Europe/Vienna
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
END:VCALENDAR
EOF;

		$event = new CalendarObjectCreatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_EVENT);

		$this->manager->expects(self::once())
			->method('getRoomForUserByToken')
			->willReturn($room);
		$this->participantService->expects(self::once())
			->method('getParticipant')
			->willReturn($this->participant);
		$this->roomService->expects(self::once())
			->method('resetObject')
			->with($room);
		$this->roomService->expects(self::once())
			->method('setDescription')
			->with($room, 'Test');
		$this->roomService->expects(self::never())
			->method('setObject');
		$this->logger->expects(self::once())
			->method('debug');
		$this->logger->expects(self::never())
			->method('warning');
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');
		$this->roomService->expects(self::never())
			->method('hasExistingCalendarEvents');

		$this->listener->handle($event);
	}

	#[DataProvider('roomUrl')]
	public function testHasExistingRooms(string $roomUrl): void {
		$calData = str_replace('{{{LOCATION}}}', $roomUrl, $this->calData);
		$event = new CalendarObjectCreatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_EVENT);

		$this->manager->expects(self::once())
			->method('getRoomForUserByToken')
			->willReturn($room);
		$this->participantService->expects(self::once())
			->method('getParticipant')
			->willReturn($this->participant);
		$this->roomService->expects(self::once())
			->method('hasExistingCalendarEvents')
			->willReturn(true);
		$this->roomService->expects(self::once())
			->method('resetObject')
			->with($room);
		$this->roomService->expects(self::never())
			->method('setObject');
		$this->logger->expects(self::once())
			->method('debug');
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->logger->expects(self::never())
			->method('warning');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');

		$this->listener->handle($event);
	}

	#[DataProvider('roomUrl')]
	public function testDeletedEvents(string $roomUrl): void {
		$calData = str_replace('{{{LOCATION}}}', $roomUrl, $this->calData);
		$event = new CalendarObjectDeletedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_EVENT);

		$this->manager->expects(self::once())
			->method('getRoomForUserByToken')
			->willReturn($room);
		$this->participantService->expects(self::once())
			->method('getParticipant')
			->willReturn($this->participant);
		$this->roomService->expects(self::once())
			->method('hasExistingCalendarEvents')
			->willReturn(false);
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::once())
			->method('setReadOnly')
			->with($room, Room::READ_ONLY);
		// $this->roomService->expects(self::never())
		// ->method('setLobby');
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->logger->expects(self::never())
			->method('debug');
		$this->logger->expects(self::never())
			->method('warning');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');

		$this->listener->handle($event);
	}

	#[DataProvider('roomUrl')]
	public function testTime(string $roomUrl): void {
		$calData = str_replace('{{{LOCATION}}}', $roomUrl, $this->calData);
		$event = new CalendarObjectCreatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_EVENT);

		$this->manager->expects(self::once())
			->method('getRoomForUserByToken')
			->willReturn($room);
		$this->participantService->expects(self::once())
			->method('getParticipant')
			->willReturn($this->participant);
		$this->roomService->expects(self::once())
			->method('hasExistingCalendarEvents')
			->willReturn(false);
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::once())
			->method('setObject')
			->with($room, Room::OBJECT_TYPE_EVENT, '1741942800#1741946400');
		// $this->roomService->expects(self::once())
		// ->method('setLobby')
		// ->with($room, Webinary::LOBBY_NON_MODERATORS, null);
		$this->timezoneService->expects(self::never())
			->method('getUserTimezone');
		$this->logger->expects(self::never())
			->method('debug');
		$this->logger->expects(self::never())
			->method('warning');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');

		$this->listener->handle($event);
	}

	public function testTimezone(): void {
		$calData = <<<EOF
BEGIN:VCALENDAR
PRODID:-//IDN nextcloud.com//Calendar app 5.2.0-dev.1//EN
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
CREATED:20250310T180746Z
DTSTAMP:20250310T180758Z
LAST-MODIFIED:20250310T180758Z
SEQUENCE:2
UID:75847de7-3754-4aae-87a4-f03755163b66
DTSTART;VALUE=DATE:20250313
DTEND;VALUE=DATE:20250314
STATUS:CONFIRMED
LOCATION:https://nextcloud.local/index.php/call/jpmrumps
END:VEVENT
END:VCALENDAR
EOF;

		$event = new CalendarObjectCreatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_EVENT);

		$this->manager->expects(self::once())
			->method('getRoomForUserByToken')
			->willReturn($room);
		$this->participantService->expects(self::once())
			->method('getParticipant')
			->willReturn($this->participant);
		$this->roomService->expects(self::once())
			->method('hasExistingCalendarEvents')
			->willReturn(false);
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::once())
			->method('setObject')
			->with($room, Room::OBJECT_TYPE_EVENT, '1741820400#1741906800');
		// $this->roomService->expects(self::once())
		// ->method('setLobby')
		// ->with($room, Webinary::LOBBY_NON_MODERATORS, null);
		$this->timezoneService->expects(self::once())
			->method('getUserTimezone')
			->willReturn('Europe/Vienna');
		$this->timezoneService->expects(self::never())
			->method('getDefaultTimezone');
		$this->logger->expects(self::never())
			->method('debug');
		$this->logger->expects(self::never())
			->method('warning');

		$this->listener->handle($event);
	}

	public function testTimezoneDefaultFallback(): void {
		$calData = <<<EOF
BEGIN:VCALENDAR
PRODID:-//IDN nextcloud.com//Calendar app 5.2.0-dev.1//EN
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
CREATED:20250310T180746Z
DTSTAMP:20250310T180758Z
LAST-MODIFIED:20250310T180758Z
SEQUENCE:2
UID:75847de7-3754-4aae-87a4-f03755163b66
DTSTART;VALUE=DATE:20250313
DTEND;VALUE=DATE:20250314
STATUS:CONFIRMED
LOCATION:https://nextcloud.local/index.php/call/jpmrumps
END:VEVENT
END:VCALENDAR
EOF;

		$event = new CalendarObjectCreatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_EVENT);

		$this->manager->expects(self::once())
			->method('getRoomForUserByToken')
			->willReturn($room);
		$this->participantService->expects(self::once())
			->method('getParticipant')
			->willReturn($this->participant);
		$this->roomService->expects(self::once())
			->method('hasExistingCalendarEvents')
			->willReturn(false);
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::once())
			->method('setObject')
			->with($room, Room::OBJECT_TYPE_EVENT, '1741820400#1741906800');
		// $this->roomService->expects(self::once())
		// ->method('setLobby')
		// ->with($room, Webinary::LOBBY_NON_MODERATORS, null);
		$this->timezoneService->expects(self::once())
			->method('getUserTimezone')
			->willReturn(null);
		$this->timezoneService->expects(self::once())
			->method('getDefaultTimezone')
			->willReturn('Europe/Vienna');
		$this->logger->expects(self::never())
			->method('debug');
		$this->logger->expects(self::never())
			->method('warning');

		$this->listener->handle($event);
	}

	public function testTimezoneUTC(): void {
		$calData = <<<EOF
BEGIN:VCALENDAR
PRODID:-//IDN nextcloud.com//Calendar app 5.2.0-dev.1//EN
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
CREATED:20250310T180746Z
DTSTAMP:20250310T180758Z
LAST-MODIFIED:20250310T180758Z
SEQUENCE:2
UID:75847de7-3754-4aae-87a4-f03755163b66
DTSTART;VALUE=DATE:20250313
DTEND;VALUE=DATE:20250314
STATUS:CONFIRMED
LOCATION:https://nextcloud.local/index.php/call/jpmrumps
END:VEVENT
END:VCALENDAR
EOF;

		$event = new CalendarObjectCreatedEvent(1, ['principaluri' => $this->userUri], [], ['calendardata' => $calData]);
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_EVENT);

		$this->manager->expects(self::once())
			->method('getRoomForUserByToken')
			->willReturn($room);
		$this->participantService->expects(self::once())
			->method('getParticipant')
			->willReturn($this->participant);
		$this->roomService->expects(self::once())
			->method('hasExistingCalendarEvents')
			->willReturn(false);
		$this->roomService->expects(self::never())
			->method('resetObject');
		$this->roomService->expects(self::once())
			->method('setObject')
			->with($room, Room::OBJECT_TYPE_EVENT, '1741824000#1741910400');
		$this->timezoneService->expects(self::once())
			->method('getUserTimezone')
			->willReturn(null);
		$this->timezoneService->expects(self::once())
			->method('getDefaultTimezone')
			->willReturn('Garbage');
		$this->logger->expects(self::never())
			->method('debug');
		$this->logger->expects(self::once())
			->method('warning');

		$this->listener->handle($event);
	}
}
