<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Signaling;

use OCA\Talk\Events\BeforeSignalingRoomPropertiesSentEvent;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCP\EventDispatcher\IEventDispatcher;

class RoomPropertiesHelper {

	public function __construct(
		private readonly IEventDispatcher $dispatcher,
		private readonly RoomService $roomService,
	) {
	}

	public function getPropertiesForSignaling(Room $room, string $userId, bool $roomModified = true): array {
		$this->roomService->validateLobbyTimer($room);

		$properties = [
			'name' => $room->getDisplayName($userId),
			'type' => $room->getType(),
			'lobby-state' => $room->getLobbyState(),
			'lobby-timer' => $room->getLobbyTimer(),
			'read-only' => $room->getReadOnly(),
			'listable' => $room->getListable(),
			'active-since' => $room->getActiveSince(),
			'sip-enabled' => $room->getSIPEnabled(),
		];

		if ($roomModified) {
			$properties['description'] = $room->getDescription();
		} else {
			$properties['participant-list'] = 'refresh';
		}

		$event = new BeforeSignalingRoomPropertiesSentEvent($room, $userId, $properties);
		$this->dispatcher->dispatchTyped($event);
		return $event->getProperties();
	}
}
