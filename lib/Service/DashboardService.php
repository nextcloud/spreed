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
	 * @param int $roomLimit
	 * @return Room[]
	 */
	public function getItems(string $userId, int $roomLimit = 10): array {
		$calendars = $this->calendarManager->getCalendarsForPrincipal('principals/users/' . $userId);
		if (count($calendars) === 0) {
			return [];
		}
		$start = $this->timeFactory->getDateTime();
		// should we leave the interval to search to the frontend?
		// Maybe to re- request a list further in the future if the current one is empty?
		// Probably YAGNI
		$end = $start->add(new \DateInterval('P7D'));
		$options = [
			'timerange' => [
				'start' => $start,
				'end' => $end,
			],
		];

		// This will most likely also find federated events
		// But we like that
		// Only question is if the room service does too
		$pattern = '/call/';
		$searchProperties = ['LOCATION'];

		$rooms = [];
		foreach ($calendars as $calendar) {
			$searchResult = $calendar->search($pattern, $searchProperties, $options);
			var_dump($searchResult);
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

				try {
					$room = $this->manager->getRoomForUserByToken($roomToken, $userId);
				} catch (RoomNotFoundException $e) {
					$this->logger->warning('Room not found: ' . $e->getMessage());
					continue;
				}

				if ($room->getObjectType() !== Room::OBJECT_TYPE_EVENT) {
					$this->logger->debug("Room " . $room->getToken() . " not an event room");
					continue;
				}

				try {
					$participant = $this->participantService->getParticipant($room, $userId, false);
				} catch (ParticipantNotFoundException $e) {
					$this->logger->debug('Participant not found: ' . $e->getMessage());
					continue;
				}
				$rooms[] = $room;
				if (count($rooms) >= $roomLimit) {
					break 2;
				}
			}
		}

//      Should the backend sort the rooms by their objectId (which is the timestamp for when the event starts)?
//		usort($widgetItems, static function (WidgetItem $a, WidgetItem $b) {
//			return (int)$a->getSinceId() - (int)$b->getSinceId();
//		});

		return $rooms;



	}
}
