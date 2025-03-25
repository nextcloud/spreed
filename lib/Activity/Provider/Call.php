<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Activity\Provider;

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Room;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\IL10N;

class Call extends Base {
	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws UnknownActivityException
	 * @since 11.0.0
	 */
	#[\Override]
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		$event = $this->preParse($event);

		if ($event->getSubject() === 'call') {
			$l = $this->languageFactory->get('spreed', $language);
			$parameters = $event->getSubjectParameters();

			try {
				$room = $this->manager->getRoomForUser((int)$parameters['room'], $this->activityManager->getCurrentUserId());
			} catch (RoomNotFoundException) {
				$room = null;
			}

			$result = $this->parseCall($room, $event, $l);
			$result['subject'] .= ' ' . $this->getDuration($l, (int)$parameters['duration']);
			// $result['params']['call'] = $roomParameter;
			$this->setSubjects($event, $result['subject'], $result['params']);
		} else {
			throw new UnknownActivityException('subject');
		}

		return $event;
	}

	protected function getDuration(IL10N $l, int $seconds): string {
		$hours = floor($seconds / 3600);
		$seconds %= 3600;
		$minutes = floor($seconds / 60);
		$seconds %= 60;

		if ($hours > 0) {
			$duration = sprintf('%1$d:%2$02d:%3$02d', $hours, $minutes, $seconds);
		} else {
			$duration = sprintf('%1$d:%2$02d', $minutes, $seconds);
		}

		return $l->t('(Duration %s)', $duration);
	}

	protected function parseCall(?Room $room, IEvent $event, IL10N $l): array {
		$parameters = $event->getSubjectParameters();

		$currentUser = array_search($this->activityManager->getCurrentUserId(), $parameters['users'], true);
		if ($currentUser === false) {
			throw new UnknownActivityException('Unknown case');
		}
		unset($parameters['users'][$currentUser]);
		sort($parameters['users']);

		if (!isset($parameters['cloudIds'])) {
			// Compatibility with old messages
			$parameters['cloudIds'] = [];
		}
		sort($parameters['users']);
		sort($parameters['cloudIds']);

		$numUsers = $numRealUsers = count($parameters['users']);

		// Without room, we can not resolve cloudIds, so we list them as guests instead
		if (!$room instanceof Room) {
			$numUsers += count($parameters['cloudIds']);
		} else {
			$parameters['guests'] += count($parameters['cloudIds']);
		}
		$displayedUsers = $numUsers;

		switch ($numUsers) {
			case 0:
				$subject = $l->t('You attended a call with {user1}');
				$subject = str_replace('{user1}', $l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				break;
			case 1:
				if ($parameters['guests'] === 0) {
					$subject = $l->t('You attended a call with {user1}');
				} else {
					$subject = $l->t('You attended a call with {user1} and {user2}');
					$subject = str_replace('{user2}', $l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 2:
				if ($parameters['guests'] === 0) {
					$subject = $l->t('You attended a call with {user1} and {user2}');
				} else {
					$subject = $l->t('You attended a call with {user1}, {user2} and {user3}');
					$subject = str_replace('{user3}', $l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 3:
				if ($parameters['guests'] === 0) {
					$subject = $l->t('You attended a call with {user1}, {user2} and {user3}');
				} else {
					$subject = $l->t('You attended a call with {user1}, {user2}, {user3} and {user4}');
					$subject = str_replace('{user4}', $l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 4:
				if ($parameters['guests'] === 0) {
					$subject = $l->t('You attended a call with {user1}, {user2}, {user3} and {user4}');
				} else {
					$subject = $l->t('You attended a call with {user1}, {user2}, {user3}, {user4} and {user5}');
					$subject = str_replace('{user5}', $l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 5:
			default:
				$subject = $l->t('You attended a call with {user1}, {user2}, {user3}, {user4} and {user5}');
				if ($numUsers === 5 && $parameters['guests'] === 0) {
					$displayedUsers = 5;
				} else {
					$displayedUsers = 4;
					$numOthers = $parameters['guests'] + $numUsers - $displayedUsers;
					$subject = str_replace('{user5}', $l->n('%n other', '%n others', $numOthers), $subject);
				}
		}

		$params = [];
		for ($i = 1; $i <= $displayedUsers; $i++) {
			if ($i <= $numRealUsers) {
				$params['user' . $i] = $this->getUser($parameters['users'][$i - 1]);
			} else {
				$params['user' . $i] = $this->getRemoteUser($room, $parameters['cloudIds'][$i - $numRealUsers - 1]);
			}
		}

		return [
			'subject' => $subject,
			'params' => $params,
		];
	}
}
