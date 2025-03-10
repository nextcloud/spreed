<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Listener;

use OCA\DAV\CalDAV\TimezoneService;
use OCA\DAV\Events\CalendarObjectCreatedEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Property\ICalendar\Date;
use Sabre\VObject\Property\ICalendar\DateTime;
use Sabre\VObject\Reader;

/** @template-implements IEventListener<CalendarObjectCreatedEvent|CalendarObjectUpdatedEvent> */
class CalDavEventListener implements IEventListener {

	public function __construct(
		private Manager $manager,
		private RoomService $roomService,
		private LoggerInterface $logger,
		private IUserManager $userManager,
		private TimezoneService $timezoneService,
		private string $userId,
	) {

	}

	public function handle(Event $event): void {
		if (!$event instanceof CalendarObjectCreatedEvent && !$event instanceof CalendarObjectUpdatedEvent) {
			return;
		}

		$calData = $event->getObjectData()['calendardata'] ?? null;
		if (!$calData) {
			return;
		}

		if (!str_contains($calData, 'LOCATION:')) {
			$this->logger->debug('No location for the even, skipping.');
			return;
		}

		$vobject = Reader::read($calData);
		$vevent = $vobject->VEVENT;
		// Check if the location is set and if the location string contains a call url
		$location = $vevent->LOCATION->getValue();
		if ($location === null || !str_contains($location, '/call/')) {
			$this->logger->debug('No location for the event or event is not call link, skipping.');
			return;
		}

		// Check if room exists and check if user is part of room
		$roomToken = array_reverse(explode('/', $location))[0];
		try {
			$room = $this->manager->getRoomByToken($roomToken, $this->userId);
		} catch (RoomNotFoundException $e) {
			$this->logger->warning('Room not found: ' . $e->getMessage());
			return;
		}

		// get room type and if it is not Room Object Event, return
		if ($room->getObjectType() !== Room::OBJECT_TYPE_EVENT) {
			$this->logger->debug("Room $roomToken not an event room");
			return;
		}

		$rrule = $vevent->RRULE;
		// We don't handle events with RRULEs
		if (!empty($rrule)) {
			$this->roomService->setObject($room);
			$this->logger->debug("Room $roomToken calendar event contains an RRULE, converting to regular room");
			return;
		}

		/** @var DateTime $start */
		$start = $vevent->DTSTART;
		if ($start instanceof Date) {
			// Full day events don't have a timezone so we need to get the user's timezone
			// If we don't have that we can use the default server timezone
			$timezone = $this->timezoneService->getUserTimezone($this->userId) ?? $this->timezoneService->getDefaultTimezone();
			try {
				$start = $start->getDateTime(new \DateTimeZone($timezone))->getTimestamp();
			} catch (\DateInvalidTimeZoneException $e) {
				$this->logger->warning("Invalid date time zone for user for room $roomToken, continuing with UTC+0: " . $e->getMessage());
				// Since this is for a full day event, we set a timestamp with UTC+0 instead
				$start = $start->getDateTime(new \DateTimeZone('UTC'))->getTimestamp();
			}
		} elseif ($start instanceof DateTime) {
			// This already includes a TZ in the object
			$start = $start->getDateTime()->getTimestamp();
		}

		$this->roomService->setObject($room, (string)$start, Room::OBJECT_TYPE_EVENT);

	}
}
