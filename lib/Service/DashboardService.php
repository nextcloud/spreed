<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Calendar\IManager;
use Psr\Log\LoggerInterface;

class DashboardService {
	public function __construct(
		private IManager $calendarManager,
		private ITimeFactory $timeFactory,
		private Manager $manager,
		private LoggerInterface $logger,
		private ParticipantService $participantService,
	) {

	}

	/**
	 * @param string $userId
	 * @return Room[]
	 */
	public function getItems(string $userId): array {
		$calendars = $this->calendarManager->getCalendarsForPrincipal('principals/users/' . $userId);
		if (count($calendars) === 0) {
			return [];
		}
		$start = $this->timeFactory->getDateTime();
		$end = $start->add(new \DateInterval('P7D'));
		$options = [
			'timerange' => [
				'start' => $start,
				'end' => $end,
			],
		];

		$pattern = '/call/';
		$searchProperties = ['LOCATION'];
		$rooms = [];
		foreach ($calendars as $calendar) {
			$searchResult = $calendar->search($pattern, $searchProperties, $options, 10);
			foreach ($searchResult as $calendarEvent) {
				// Find first recurrence in the future
				$recurrence = null;
				$location = null;
				foreach ($calendarEvent['objects'] as $object) {
					// We do not allow recurrences
					if ($object['RRULE'] !== null || $object['RECURRENCE-ID'] !== null) {
						continue;
					}
					/** @var \DateTimeImmutable $startDate */
					$startDate = $object['DTSTART'][0];
					$location = $object['LOCATION'];
					if ($startDate->getTimestamp() >= $start->getTimestamp()) {
						$recurrence = $object;
						break;
					}
				}

				if ($recurrence === null || $location === null) {
					continue;
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
					$this->logger->debug("Room $roomToken not found in dashboard service");
					continue;
				}

				if ($room->getObjectType() !== Room::OBJECT_TYPE_EVENT) {
					$this->logger->debug('Room ' . $room->getToken() . ' not an event room in dashboard service');
					continue;
				}

				try {
					$participant = $this->participantService->getParticipant($room, $userId, false);
				} catch (ParticipantNotFoundException) {
					$this->logger->debug("Participant $userId not found in dashboard service");
					continue;
				}
				$rooms[] = $room;
			}
		}

		usort($rooms, static function (Room $a, Room $b) {
			return (int)$a->getObjectId() - (int)$b->getObjectId();
		});
		return array_slice($rooms, 0, 10);
	}
}
