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
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Calendar\IManager;
use Psr\Log\LoggerInterface;

class DashboardService {
	public function __construct(
		private Manager $manager,
		private IManager $calendarManager,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private ParticipantService $participantService,
	) {

	}

	/**
	 * @param string $userId
	 * @return Participant[]
	 */
	public function getEventRooms(string $userId): array {
		$calendars = $this->calendarManager->getCalendarsForPrincipal('principals/users/' . $userId);
		if (count($calendars) === 0) {
			return [];
		}
		$start = $this->timeFactory->getDateTime();
		$end = $this->timeFactory->getDateTime()->add(\DateInterval::createFromDateString('1 week'));
		$options = [
			'timerange' => [
				'start' => $start,
				'end' => $end,
			],
		];

		$pattern = '/call/';
		$searchProperties = ['LOCATION'];
		$participants = [];
		foreach ($calendars as $calendar) {
			$searchResult = $calendar->search($pattern, $searchProperties, $options, 10);
			foreach ($searchResult as $calendarEvent) {
				// Find first recurrence in the future
				$event = null;
				$location = null;
				foreach ($calendarEvent['objects'] as $object) {
					// We do not allow recurrences
					if (isset($object['RRULE']) || isset($object['RECURRENCE-ID'])) {
						continue;
					}
					/** @var \DateTimeImmutable $startDate */
					$startDate = $object['DTSTART'][0];
					$location = $object['LOCATION'][0] ?? null;
					if ($startDate->getTimestamp() >= $start->getTimestamp()) {
						$event = $object;
						break;
					}
				}

				if ($event === null || $location === null) {
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
				$participants[$participant->getRoom()->getId()] = $participant;
			}
		}

		usort($participants, static function (Participant $a, Participant $b) {
			return (int)$a->getRoom()->getObjectId() - (int)$b->getRoom()->getObjectId();
		});
		return array_slice($participants, 0, 10);
	}
}
