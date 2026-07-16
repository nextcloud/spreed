<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Events\ACallEndedEvent;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Queues classified conversations for automatic deletion once a call happened.
 *
 * When a call ends in a classified conversation the room is bound to the
 * `classified` object type, which makes {@see \OCA\Talk\BackgroundJob\ExpireObjectRooms}
 * delete it after the classified retention window. A moderator can prevent this
 * by unbinding the room, which converts it to the `classified_persist` object
 * type. Because we only bind rooms with an empty object type, a persisted (or
 * otherwise bound) room is never re-queued.
 *
 * @template-implements IEventListener<Event>
 */
class ClassifiedRoomAutoDeleteListener implements IEventListener {
	public function __construct(
		private readonly RoomService $roomService,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof ACallEndedEvent) {
			return;
		}

		$room = $event->getRoom();
		if (!$room->isClassified()
			|| $room->getObjectType() !== ''
			|| $room->isPreserved()) {
			return;
		}

		$this->roomService->setObject($room, Room::OBJECT_TYPE_CLASSIFIED, (string)$this->timeFactory->getTime());
	}
}
