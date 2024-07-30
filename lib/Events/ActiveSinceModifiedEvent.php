<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;

class ActiveSinceModifiedEvent extends AActiveSinceModifiedEvent {
	public function __construct(
		Room $room,
		?\DateTime $newValue,
		?\DateTime $oldValue,
		int $callFlag,
		int $oldCallFlag,
		protected bool $updatedActiveSince,
	) {
		parent::__construct(
			$room,
			$newValue,
			$oldValue,
			$callFlag,
			$oldCallFlag,
		);
	}

	public function hasUpdatedActiveSince(): bool {
		return $this->updatedActiveSince;
	}
}
