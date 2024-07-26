<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;

abstract class AActiveSinceModifiedEvent extends ARoomModifiedEvent {
	public function __construct(
		Room $room,
		?\DateTime $newValue,
		?\DateTime $oldValue,
		protected int $callFlag,
		protected int $oldCallFlag,
	) {
		parent::__construct(
			$room,
			self::PROPERTY_ACTIVE_SINCE,
			$newValue,
			$oldValue,
		);
	}

	public function getCallFlag(): int {
		return $this->callFlag;
	}

	public function getOldCallFlag(): int {
		return $this->oldCallFlag;
	}
}
