<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;

class RoomExtendedEvent extends ARoomEvent {
	public function __construct(
		Room $oldRoom,
		protected Room $newRoom,
	) {
		parent::__construct($oldRoom);
	}

	public function getNewRoom(): Room {
		return $this->newRoom;
	}
}
