<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Activity\Provider;

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
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		$event = $this->preParse($event);

		if ($event->getSubject() === 'call') {
			$l = $this->languageFactory->get('spreed', $language);
			$parameters = $event->getSubjectParameters();

			//			$roomParameter = $this->getFormerRoom($l, (int) $parameters['room']);
			//			try {
			//				$room = $this->manager->getRoomById((int) $parameters['room']);
			//				$roomParameter = $this->getRoom($l, $room);
			//			} catch (RoomNotFoundException $e) {
			//			}

			$result = $this->parseCall($event, $l);
			$result['subject'] .= ' ' . $this->getDuration($l, (int) $parameters['duration']);
			//			$result['params']['call'] = $roomParameter;
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

	protected function parseCall(IEvent $event, IL10N $l): array {
		$parameters = $event->getSubjectParameters();

		$currentUser = array_search($this->activityManager->getCurrentUserId(), $parameters['users'], true);
		if ($currentUser === false) {
			throw new UnknownActivityException('Unknown case');
		}
		unset($parameters['users'][$currentUser]);
		sort($parameters['users']);

		$numUsers = \count($parameters['users']);
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
			$params['user' . $i] = $this->getUser($parameters['users'][$i - 1]);
		}

		return [
			'subject' => $subject,
			'params' => $params,
		];
	}
}
