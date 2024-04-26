<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;

abstract class ALobbyModifiedEvent extends ARoomModifiedEvent {
	public function __construct(
		Room $room,
		string|int $newValue,
		string|int|null $oldValue,
		protected ?\DateTime $lobbyTimer,
		protected bool $timerReached,
	) {
		parent::__construct(
			$room,
			self::PROPERTY_LOBBY,
			$newValue,
			$oldValue,
		);
	}

	public function getLobbyTimer(): ?\DateTime {
		return $this->lobbyTimer;
	}

	public function isTimerReached(): bool {
		return $this->timerReached;
	}
}
