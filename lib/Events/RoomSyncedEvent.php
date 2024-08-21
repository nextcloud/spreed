<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;

class RoomSyncedEvent extends ARoomSyncedEvent {
	/**
	 * @param array<array-key, ARoomModifiedEvent::PROPERTY_*|ARoomSyncedEvent::PROPERTY_*> $properties
	 */
	public function __construct(
		Room $room,
		protected array $properties,
	) {
		parent::__construct($room);
	}

	/**
	 * @return array<array-key, ARoomModifiedEvent::PROPERTY_*|ARoomSyncedEvent::PROPERTY_*>
	 */
	public function getProperties(): array {
		return $this->properties;
	}
}
