<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Federation\Proxy\TalkV1\Notifier;

use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Federation\ICloudIdManager;

/**
 * @template-implements IEventListener<Event>
 */
class RoomModifiedListener implements IEventListener {
	public function __construct(
		protected BackendNotifier $backendNotifier,
		protected ParticipantService $participantService,
		protected ICloudIdManager $cloudIdManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof RoomModifiedEvent) {
			return;
		}

		if (!in_array($event->getProperty(), [
			ARoomModifiedEvent::PROPERTY_AVATAR,
			ARoomModifiedEvent::PROPERTY_DESCRIPTION,
			ARoomModifiedEvent::PROPERTY_NAME,
			ARoomModifiedEvent::PROPERTY_READ_ONLY,
			ARoomModifiedEvent::PROPERTY_TYPE,
		], true)) {
			return;
		}

		$participants = $this->participantService->getParticipantsByActorType($event->getRoom(), Attendee::ACTOR_FEDERATED_USERS);
		foreach ($participants as $participant) {
			$cloudId = $this->cloudIdManager->resolveCloudId($participant->getAttendee()->getActorId());

			$this->backendNotifier->sendRoomModifiedUpdate(
				$cloudId->getRemote(),
				$participant->getAttendee()->getId(),
				$participant->getAttendee()->getAccessToken(),
				$event->getRoom()->getToken(),
				$event->getProperty(),
				$event->getNewValue(),
				$event->getOldValue(),
			);
		}
	}
}
