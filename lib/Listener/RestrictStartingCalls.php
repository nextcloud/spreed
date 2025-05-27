<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	#[\Override]
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

		if (in_array($room->getObjectType(), [
			Room::OBJECT_TYPE_PHONE_LEGACY,
			Room::OBJECT_TYPE_PHONE_TEMPORARY,
			Room::OBJECT_TYPE_PHONE_PERSIST,
		], true) && $room->getObjectId() === Room::OBJECT_ID_PHONE_INCOMING) {
			// Always allow guests to use the direct-dialin
			return;
		}

		if (!$event->getParticipant()->canStartCall($this->serverConfig)
			&& !$this->participantService->hasActiveSessionsInCall($room)) {
			throw new ForbiddenException('Can not start a call');
		}
	}
}
