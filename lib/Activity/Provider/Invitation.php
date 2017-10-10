<?php
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

namespace OCA\Spreed\Activity\Provider;

use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Room;
use OCP\Activity\IEvent;
use OCP\IL10N;

class Invitation extends Base {

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		$event = parent::preParse($event);
		$l = $this->languageFactory->get('spreed', $language);

		try {
			$parameters = $event->getSubjectParameters();
			$room = $this->manager->getRoomById((int) $parameters['room']);

			if ($event->getSubject() === 'invitation') {
				$result = $this->parseInvitation($event, $l, $room);
				$this->setSubjects($event, $result['subject'], $result['params']);
			} else {
				throw new \InvalidArgumentException();
			}
		} catch (RoomNotFoundException $e) {
			throw new \InvalidArgumentException();
		}

		return $event;
	}

	protected function parseInvitation(IEvent $event, IL10N $l, Room $room) {
		$parameters = $event->getSubjectParameters();

		if ($room->getName() === '') {
			if ($room->getType() === Room::ONE_TO_ONE_CALL) {
				$subject = $l->t('{actor} invited you to a private call');
			} else {
				$subject = $l->t('{actor} invited you to a group call');
			}

			return [
				'subject' => $subject,
				'params' => $this->getParameters($parameters),
			];
		}

		return [
			'subject' => $l->t('{actor} invited you to the call {call}'),
			'params' => $this->getParameters($parameters, $room),
		];
	}

	/**
	 * @param array $parameters
	 * @param Room $room
	 * @return array
	 */
	protected function getParameters(array $parameters, Room $room = null) {
		if ($room === null) {
			return [
				'actor' => $this->getUser($parameters['user']),
			];
		}

		return [
			'actor' => $this->getUser($parameters['user']),
			'call' => $this->getRoom($room),
		];
	}

}
