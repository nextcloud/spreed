<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\IUser;

class UserJoinedRoomEvent extends ARoomEvent {
	public function __construct(
		Room $room,
		protected IUser $user,
		protected Participant $participant,
	) {
		parent::__construct($room);
	}

	public function getUser(): IUser {
		return $this->user;
	}

	public function getParticipant(): Participant {
		return $this->participant;
	}
}
