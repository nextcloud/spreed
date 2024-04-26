<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;

abstract class AttendeesEvent extends ARoomEvent {
	/**
	 * @param Attendee[] $attendees
	 */
	public function __construct(
		Room $room,
		protected array $attendees,
	) {
		parent::__construct($room);
	}

	/**
	 * @return Attendee[]
	 */
	public function getAttendees(): array {
		return $this->attendees;
	}
}
