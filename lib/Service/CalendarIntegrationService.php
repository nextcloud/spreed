<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Dashboard\Event;
use OCA\Talk\Exceptions\InvalidRoomException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Calendar\ICalendar;
use OCP\Calendar\IManager;
use OCP\IDateTimeZone;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkDashboardEvent from ResponseDefinitions
 */
class CalendarIntegrationService {
	public function __construct(
		private Manager $manager,
		private IManager $calendarManager,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private RoomService $roomService,
		private IDateTimeZone $dateTimeZone,
		private AvatarService $avatarService,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
	) {

	}

	/**
	 * @param string $userId
	 * @return list<TalkDashboardEvent>
	 */
	public function getDashboardEvents(string $userId): array {
		$principaluri = 'principals/users/' . $userId;
		$calendars = $this->calendarManager->getCalendarsForPrincipal($principaluri);
		if (count($calendars) === 0) {
			return [];
		}

		// Only use personal calendars
		// Events for shared calendars where you are an ATTENDEE will be in your personal calendar
		$calendars = array_filter($calendars, static function (ICalendar $calendar) {
			if ($calendar->getUri() === 'contact_birthdays') {
				// The birthday calendar does not contain events with a location matching a talk room.
				return false;
			}
			if (method_exists($calendar, 'isShared')) {
				return $calendar->isShared() === false;
			}
			return true;
		});

		$userTimezone = $this->dateTimeZone->getTimezone();
		// Midnight for the current user so we also include ongoing events (might be all day events)
		$start = $this->timeFactory->getDateTime()->setTimezone($userTimezone)->setTime(0, 0);
		$start = $start->setTimezone(new \DateTimeZone('UTC'));
		$end = clone($start);
		$end = $end->add(\DateInterval::createFromDateString('1 week'));
		$options = [
			'timerange' => [
				'start' => $start,
				'end' => $end,
			],
		];

		$pattern = '/call/';
		$searchProperties = ['LOCATION'];
		$events = [];
		/** @var ICalendar $calendar */
		foreach ($calendars as $calendar) {
			$searchResult = $calendar->search($pattern, $searchProperties, $options, 100);
			foreach ($searchResult as $calendarEvent) {
				// Find first recurrence in the future
				$event = null;
				$dashboardEvent = new Event();

				foreach ($calendarEvent['objects'] as $object) {
					if (!isset($object['DTEND'][0])) {
						// Don't show events without end since they should not take up any time
						// @link https://www.kanzaki.com/docs/ical/vevent.html
						continue;
					}
					$dashboardEvent->setStart(\DateTime::createFromImmutable($object['DTSTART'][0])->setTimezone($userTimezone)->getTimestamp());
					$dashboardEvent->setEnd(\DateTime::createFromImmutable($object['DTEND'][0])->setTimezone($userTimezone)->getTimestamp());
					// Filter out events in the past
					if ($dashboardEvent->getEnd() <= $this->timeFactory->getDateTime('now', $userTimezone)->getTimestamp()) {
						continue;
					}

					$event = $object;
					break;
				}

				$location = $event['LOCATION'][0] ?? null;
				if ($event === null || $location === null) {
					continue;
				}

				if (isset($event['STATUS']) && $event['STATUS'][0] === 'CANCELLED') {
					continue;
				}

				try {
					$token = $this->roomService->parseRoomTokenFromUrl($location);
					// Already returns public / open conversations
					$room = $this->manager->getRoomForUserByToken($token, $userId);
				} catch (RoomNotFoundException) {
					$this->logger->debug("Room for url $location not found in dashboard service");
					continue;
				}

				$dashboardEvent->setRoomToken($token);
				$dashboardEvent->setRoomType($room->getType());
				$dashboardEvent->setRoomName($room->getName());
				$dashboardEvent->setRoomDisplayName($room->getDisplayName($userId));

				if (isset($event['ATTENDEE'])) {
					$dashboardEvent->generateAttendance($event['ATTENDEE']);
				}

				$dashboardEvent->setEventName($event['SUMMARY'][0] ?? '');
				$dashboardEvent->setEventDescription($event['DESCRIPTION'][0] ?? null);

				if (isset($event['ATTACH'])) {
					$dashboardEvent->handleCalendarAttachments($calendar->getUri(), $event['ATTACH']);
				}

				if (isset($events[$dashboardEvent->generateEventIdentifier()])) {
					/** @var Event $existing */
					$existing = $events[$dashboardEvent->generateEventIdentifier()];
					$existing->addCalendar($calendar->getUri(), $calendar->getDisplayName(), $calendar->getDisplayColor());
					// Merge attachments
					$existing->mergeAttachments($dashboardEvent);

					// If original SUMMARY is empty, use the duplicate content if it exists
					if ($existing->getEventDescription() === null) {
						$existing->setEventDescription($dashboardEvent->getEventDescription() ?? '');
					}

					// We continue here as the same event already exists in a different calendar
					$events[$existing->generateEventIdentifier()] = $existing;
					continue;
				}

				$dashboardEvent->addCalendar($calendar->getUri(), $calendar->getDisplayName(), $calendar->getDisplayColor());
				$dashboardEvent->setRoomAvatarVersion($this->avatarService->getAvatarVersion($room));
				$dashboardEvent->setRoomActiveSince($room->getActiveSince()?->getTimestamp());
				$objectId = base64_encode($this->urlGenerator->getWebroot() . '/remote.php/dav/calendars/' . $userId . '/' . $calendar->getUri() . '/' . $calendarEvent['uri']);

				if (isset($event['RECURRENCE-ID'])) {
					$dashboardEvent->setEventLink(
						$this->urlGenerator->linkToRouteAbsolute(
							'calendar.view.indexdirect.edit',
							[
								'objectId' => $objectId,
								'recurrenceId' => $event['RECURRENCE-ID'][0],
							]
						)
					);
				} else {
					$dashboardEvent->setEventLink(
						$this->urlGenerator->linkToRouteAbsolute('calendar.view.indexdirect.edit', ['objectId' => $objectId])
					);
				}

				$events[$dashboardEvent->generateEventIdentifier()] = $dashboardEvent;
				if (count($events) === 10) {
					break;
				}
			}
		}

		if (empty($events)) {
			return $events;
		}

		usort($events, static function (Event $a, Event $b) {
			return $a->getStart() - $b->getStart();
		});

		return array_map(static function (Event $event) {
			return $event->jsonSerialize();
		}, array_slice($events, 0, 10));
	}

	/**
	 * @param string $userId
	 * @param Room $room
	 * @return list<TalkDashboardEvent>
	 */
	public function getMutualEvents(string $userId, Room $room): array {
		if ($room->getType() !== Room::TYPE_ONE_TO_ONE) {
			throw new InvalidRoomException();
		}

		try {
			$userIds = json_decode($room->getName(), false, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			throw new InvalidRoomException();
		}

		$participants = array_filter($userIds, static function (string $participantId) use ($userId) {
			return $participantId !== $userId;
		});

		if (count($participants) !== 1) {
			throw new InvalidRoomException();
		}

		$otherParticipant = $this->userManager->get(array_pop($participants));
		if ($otherParticipant === null) {
			// Change to correct exception
			throw new ParticipantNotFoundException();
		}

		$pattern = $otherParticipant->getEMailAddress();
		if ($pattern === null) {
			return [];
		}

		$principaluri = 'principals/users/' . $userId;
		$calendars = $this->calendarManager->getCalendarsForPrincipal($principaluri);
		if (count($calendars) === 0) {
			return [];
		}

		// Only use personal calendars
		$calendars = array_filter($calendars, static function (ICalendar $calendar) {
			if (method_exists($calendar, 'isShared')) {
				return $calendar->isShared() === false;
			}
			return true;
		});

		$start = $this->timeFactory->getDateTime();
		$end = clone($start);
		$end = $end->add(\DateInterval::createFromDateString('1 week'));
		$options = [
			'timerange' => [
				'start' => $start,
				'end' => $end,
			],
		];

		$userTimezone = $this->dateTimeZone->getTimezone();
		$searchProperties = ['ATTENDEE', 'ORGANIZER'];
		$events = [];
		/** @var ICalendar $calendar */
		foreach ($calendars as $calendar) {
			$searchResult = $calendar->search($pattern, $searchProperties, $options);
			foreach ($searchResult as $calendarEvent) {
				// Find first recurrence in the future
				$event = null;
				$dashboardEvent = new Event();
				foreach ($calendarEvent['objects'] as $object) {
					$dashboardEvent->setStart(\DateTime::createFromImmutable($object['DTSTART'][0])->setTimezone($userTimezone)->getTimestamp());
					$dashboardEvent->setEnd(\DateTime::createFromImmutable($object['DTEND'][0])->setTimezone($userTimezone)->getTimestamp());

					if ($dashboardEvent->getStart() >= $start->getTimestamp()) {
						$event = $object;
						break;
					}
				}

				if ($event === null) {
					continue;
				}

				if (!isset($event['ORGANIZER']) && !isset($event['ATTENDEE'])) {
					// Don't show events without attendees
					continue;
				}

				if (!$dashboardEvent->isOrganizer($event['ORGANIZER'], $otherParticipant->getEMailAddress()) && !$dashboardEvent->isAttendee($event['ATTENDEE'], $otherParticipant->getEMailAddress())) {
					// Due to a bug in the caldav search, we will get a search result for recurring events
					// even if the pattern does not match the current recurrence
					// So make sure that $otherParticipant is an attendee on the current event
					continue;
				}

				$dashboardEvent->generateAttendance($event['ATTENDEE']);
				$dashboardEvent->setEventName($event['SUMMARY'][0] ?? '');
				$dashboardEvent->setEventDescription($event['DESCRIPTION'][0] ?? null);
				$dashboardEvent->addCalendar($calendar->getUri(), $calendar->getDisplayName(), $calendar->getDisplayColor());

				$location = $event['LOCATION'][0] ?? null;
				if ($location !== null && str_contains($location, '/call/') === true) {
					try {
						$token = $this->roomService->parseRoomTokenFromUrl($location);
						// Already returns public / open conversations
						$eventRoom = $this->manager->getRoomForUserByToken($token, $userId);
					} catch (RoomNotFoundException) {
						$this->logger->debug("Room for url $location not found in dashboard service");
						continue;
					}
					$dashboardEvent->setRoomType($eventRoom->getType());
					$dashboardEvent->setRoomName($eventRoom->getName());
					$dashboardEvent->setRoomToken($eventRoom->getToken());
					$dashboardEvent->setRoomDisplayName($eventRoom->getDisplayName($userId));
					$dashboardEvent->setRoomAvatarVersion($this->avatarService->getAvatarVersion($eventRoom));
					$dashboardEvent->setRoomActiveSince($eventRoom->getActiveSince()?->getTimestamp());
				}

				if (isset($event['ATTACH'])) {
					$dashboardEvent->handleCalendarAttachments($calendar->getUri(), $event['ATTACH']);
				}

				$objectId = base64_encode($this->urlGenerator->getWebroot() . '/remote.php/dav/calendars/' . $userId . '/' . $calendar->getUri() . '/' . $calendarEvent['uri']);
				if (isset($event['RECURRENCE-ID'])) {
					$dashboardEvent->setEventLink(
						$this->urlGenerator->linkToRouteAbsolute(
							'calendar.view.indexdirect.edit',
							[
								'objectId' => $objectId,
								'recurrenceId' => $event['RECURRENCE-ID'][0],
							]
						)
					);
				} else {
					$dashboardEvent->setEventLink(
						$this->urlGenerator->linkToRouteAbsolute('calendar.view.indexdirect.edit', ['objectId' => $objectId])
					);
				}

				$events[] = $dashboardEvent;
			}
		}

		if (empty($events)) {
			return $events;
		}

		usort($events, static function (Event $a, Event $b) {
			return $a->getStart() - $b->getStart();
		});

		return array_map(static function (Event $event) {
			return $event->jsonSerialize();
		}, array_slice($events, 0, 3));
	}
}
