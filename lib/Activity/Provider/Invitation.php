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

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;

class Invitation extends Base {
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

		if ($event->getSubject() === 'invitation') {
			$l = $this->languageFactory->get('spreed', $language);
			$parameters = $event->getSubjectParameters();

			$roomParameter = $this->getFormerRoom($l, (int) $parameters['room']);
			try {
				$room = $this->manager->getRoomById((int) $parameters['room']);
				$roomParameter = $this->getRoom($room, $event->getAffectedUser());
			} catch (RoomNotFoundException $e) {
			}

			$this->setSubjects($event, $l->t('{actor} invited you to {call}'), [
				'actor' => $this->getUser($parameters['user']),
				'call' => $roomParameter,
			]);
		} else {
			throw new UnknownActivityException('subject');
		}

		return $event;
	}
}
