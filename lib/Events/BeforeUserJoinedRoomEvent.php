<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;
use OCP\IUser;

class BeforeUserJoinedRoomEvent extends ABeforeJoinedRoomEvent {
	public function __construct(
		Room $room,
		protected IUser $user,
		string $password,
		bool $passedPasswordProtection,
	) {
		parent::__construct($room, $password, $passedPasswordProtection);
	}

	public function getUser(): IUser {
		return $this->user;
	}

	#[\Override]
	public function getPassword(): string {
		return $this->password;
	}
}
