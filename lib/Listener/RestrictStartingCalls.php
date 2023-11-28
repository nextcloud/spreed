<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Listener;

use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;

/**
 * @template-implements IEventListener<Event>
 */
class RestrictStartingCalls implements IEventListener {

	public function __construct(
		protected IConfig $serverConfig,
		protected ParticipantService $participantService,
	) {
	}

	/**
	 * @throws ForbiddenException
	 */
	public function handle(Event $event): void {
		if (!$event instanceof BeforeParticipantModifiedEvent) {
			return;
		}

		if ($event->getProperty() !== AParticipantModifiedEvent::PROPERTY_IN_CALL) {
			return;
		}

		if ($event->getNewValue() === Participant::FLAG_DISCONNECTED
			|| $event->getOldValue() !== Participant::FLAG_DISCONNECTED) {
			return;
		}

		$room = $event->getRoom();
		if ($room->getType() === Room::TYPE_PUBLIC
			&& $room->getObjectType() === Room::OBJECT_TYPE_VIDEO_VERIFICATION) {
			// Always allow guests to start calls in password-request calls
			return;
		}

		if (!$event->getParticipant()->canStartCall($this->serverConfig)
			&& !$this->participantService->hasActiveSessionsInCall($room)) {
			throw new ForbiddenException('Can not start a call');
		}
	}
}
