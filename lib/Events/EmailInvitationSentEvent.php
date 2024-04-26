<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;

class EmailInvitationSentEvent extends ARoomEvent {

	public function __construct(
		Room $room,
		protected Attendee $attendee,
	) {
		parent::__construct($room);
	}

	public function getAttendee(): Attendee {
		return $this->attendee;
	}
}
