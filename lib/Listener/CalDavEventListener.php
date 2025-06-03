<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Listener;

use OCA\DAV\CalDAV\TimezoneService;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Webinary;
use OCP\Calendar\Events\CalendarObjectCreatedEvent;
use OCP\Calendar\Events\CalendarObjectDeletedEvent;
use OCP\Calendar\Events\CalendarObjectUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Sabre\VObject\ParseException;
use Sabre\VObject\Property\ICalendar\Date;
use Sabre\VObject\Property\ICalendar\DateTime;
use Sabre\VObject\Reader;

/** @template-implements IEventListener<CalendarObjectCreatedEvent|CalendarObjectUpdatedEvent> */
class CalDavEventListener implements IEventListener {

	public function __construct(
		private Manager $manager,
		private RoomService $roomService,
		private LoggerInterface $logger,
		private TimezoneService $timezoneService,
		private ParticipantService $participantService,
		private IL10N $l10n,
	) {

	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof CalendarObjectCreatedEvent && !$event instanceof CalendarObjectUpdatedEvent && !$event instanceof CalendarObjectDeletedEvent) {
			return;
		}

		$principaluri = $event->getCalendarData()['principaluri'] ?? null;
		if (!$principaluri) {
			$this->logger->debug('No principal uri for the event, skipping for calendar event integration');
			return;
		}

		if ($principaluri === 'principals/system/system') {
			$this->logger->debug('System calendar, skipping for calendar event integration');
			return;
		}

		// The principal uri is in the format 'principals/users/<userId>'
		$userId = substr($principaluri, 17);

		$calData = $event->getObjectData()['calendardata'] ?? null;
		if (!$calData) {
			$this->logger->debug('No calendar data for the event, skipping for calendar event integration');
			return;
		}

		if (!str_contains($calData, 'LOCATION:')) {
			$this->logger->debug('No location for the even, skipping for calendar event integration');
			return;
		}

		try {
			$vobject = Reader::read($calData);
		} catch (ParseException $e) { /** Undocumented in sabre code */
			$this->logger->warning($e->getMessage());
			return;
		}

		$vevent = $vobject->VEVENT;
		// Check if the location is set and if the location string contains a call url
		$location = $vevent->LOCATION?->getValue();
		if ($location === null || !str_contains($location, '/call/')) {
			$this->logger->debug('No location for the event or event is not a call link, skipping for calendar event integration');
			return;
		}

		// Check if room exists and check if user is part of room
		$array = explode('/', $location);
		$roomToken = end($array);
		// Cut off any excess characters from the room token
		if (str_contains($roomToken, '?')) {
			$roomToken = substr($roomToken, 0, strpos($roomToken, '?'));
		}
		if (str_contains($roomToken, '#')) {
			$roomToken = substr($roomToken, 0, strpos($roomToken, '#'));
		}
		try {
			$room = $this->manager->getRoomForUserByToken($roomToken, $userId);
		} catch (RoomNotFoundException) {
			// Change log level if log is spammed too much
			$this->logger->warning('Room with ' . $roomToken . ' not found for calendar event integration');
			return;
		}

		try {
			$participant = $this->participantService->getParticipant($room, $userId, false);
		} catch (ParticipantNotFoundException) {
			$this->logger->debug('Room with ' . $roomToken . ' not found for user ' . $userId . ' for calendar event integration');
			return;
		}

		if ($participant->getAttendee()->getParticipantType() !== Participant::OWNER) {
			$this->logger->debug('Participant ' . $userId . ' is not owner for calendar event integration');
			return;
		}

		// get room type and if it is not Room Object Event, return
		if ($room->getObjectType() !== Room::OBJECT_TYPE_EVENT) {
			$this->logger->debug("Room $roomToken not an event room for calendar event integration");
			return;
		}

		$name = $vevent->SUMMARY?->getValue();
		if ($name !== null) {
			$name = strlen($name) > 254 ? substr($name, 0, 254) . "\u{2026}" : $name;
		}

		$description = $vevent->DESCRIPTION?->getValue();
		if ($description !== null) {
			$description = strlen($description) > 1999 ? substr($description, 0, 1999) . "\u{2026}" : $description;
		}

		$rrule = $vevent->RRULE;
		$recurrenceId = $vevent->{'RECURRENCE-ID'}?->getValue();
		// We don't handle events with RRULEs
		// And you cannot create an event room for a recurrence exception
		if (!empty($rrule) || !empty($recurrenceId)) {
			$this->roomService->resetObject($room);
			$this->logger->debug("Room $roomToken calendar event contains an RRULE, converting to regular room for calendar event integration");
			if ($event instanceof CalendarObjectCreatedEvent && $description !== null) {
				// We still need to set the description for newly created events
				// Since the room is still a type event when sending the data from the frontend
				// So set the description for newly created events here to restore previous behaviour
				$this->roomService->setDescription($room, $description);
			}
			return;
		}

		if ($this->roomService->hasExistingCalendarEvents($room, $userId, $vevent->UID->getValue())) {
			$this->roomService->resetObject($room);
			$this->logger->debug("Room $roomToken calendar event was already used previously, converting to regular room for calendar event integration");
			if ($event instanceof CalendarObjectCreatedEvent && $description !== null) {
				// We still need to set the description for newly created events
				// Since the room is still a type event when sending the data from the frontend
				// So set the description for newly created events here to restore previous behaviour
				$this->roomService->setDescription($room, $description);
			}
			return;
		}

		// If the calendar event was deleted, we lock the room
		if ($event instanceof CalendarObjectDeletedEvent) {
			$this->roomService->setReadOnly($room, Room::READ_ONLY);
			return;
		}


		// So we can unset names & descriptions in case the user deleted them
		$this->roomService->setName($room, $name ?? $this->l10n->t('Talk conversation for event'));
		$this->roomService->setDescription($room, $description ?? '');

		/** @var DateTime $start */
		$start = $vevent->DTSTART;
		/** @var DateTime $end */
		$end = $vevent->DTEND;
		if ($start instanceof Date) {
			// Full day events don't have a timezone so we need to get the user's timezone
			// If we don't have that we can use the default server timezone
			$timezone = $this->timezoneService->getUserTimezone($userId) ?? $this->timezoneService->getDefaultTimezone();
			try {
				$start = $start->getDateTime(new \DateTimeZone($timezone))->getTimestamp();
				$end = $end->getDateTime(new \DateTimeZone($timezone))->getTimestamp();
			} catch (\Exception $e) {
				$this->logger->warning("Invalid date time zone for user for room $roomToken, continuing with UTC+0: " . $e->getMessage() . ' for calendar event integration');
				// Since this is for a full day event, we set a timestamp with UTC+0 instead
				$start = $start->getDateTime(new \DateTimeZone('UTC'))->getTimestamp();
				$end = $end->getDateTime(new \DateTimeZone('UTC'))->getTimestamp();
			}
		} elseif ($start instanceof DateTime) {
			// This already includes a TZ in the object
			$start = $start->getDateTime()->getTimestamp();
			$end = $end->getDateTime()->getTimestamp();
		}

		$objectId = $start . '#' . $end;
		$this->roomService->setObject($room, Room::OBJECT_TYPE_EVENT, $objectId);
		// TODO Reconsider the lobby later, but it needs more thoughts to:
		// 1. Allow others to ping the owner/host
		// 2. Automatically disable
		//    but how to recover from adding a second event to a conversation then?
		// $this->roomService->setLobby($room, Webinary::LOBBY_NON_MODERATORS, null);
	}
}
