<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	#[\Override]
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		$event = $this->preParse($event);

		if ($event->getSubject() === 'invitation') {
			$l = $this->languageFactory->get('spreed', $language);
			$parameters = $event->getSubjectParameters();

			try {
				$room = $this->manager->getRoomById((int)$parameters['room']);
				$roomParameter = $this->getRoom($room, $event->getAffectedUser());
			} catch (RoomNotFoundException) {
				$roomParameter = $this->getFormerRoom($l);
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
