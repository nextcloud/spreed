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
		private RoomService $roomService,
	) {

	}

	/**
	 * @param string $userId
	 * @return list<Participant>
	 */
	public function getEvents(string $userId): array {
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
		$startDates = [];
		foreach ($calendars as $calendar) {
			$searchResult = $calendar->search($pattern, $searchProperties, $options, 10);
			foreach ($searchResult as $calendarEvent) {
				// Find first recurrence in the future
				$event = null;
				$location = null;
				$startDate = null;
				foreach ($calendarEvent['objects'] as $object) {
					/** @var \DateTimeImmutable $startDate */
					$startDate = $object['DTSTART'][0];
					$location = $object['LOCATION'][0] ?? null;
					if ($startDate->getTimestamp() >= $start->getTimestamp()) {
						$event = $object;
						break;
					}
				}

				if ($event === null || $location === null || $startDate === null) {
					continue;
				}

				try {
					$token = $this->roomService->parseRoomTokenFromUrl($location);
					$room = $this->manager->getRoomForUserByToken($token, $userId);
				} catch (RoomNotFoundException) {
					$this->logger->debug("Room for url $location not found in dashboard service");
					continue;
				}

				try {
					$participant = $this->participantService->getParticipant($room, $userId, false);
				} catch (ParticipantNotFoundException) {
					$this->logger->debug("Participant $userId not found in dashboard service");
					continue;
				}
				// assign the participants (i.e. rooms) for each start date
				// so we can sort by start date (in case two have the same start date)
				$startDates[$startDate->getTimestamp()][$participant->getRoom()->getId()] = $participant;
			}
		}

		ksort($startDates);
		return array_slice(array_merge(...$startDates), 0, 10);
	}
}
