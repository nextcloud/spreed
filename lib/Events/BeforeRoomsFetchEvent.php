<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCP\EventDispatcher\Event;

class BeforeRoomsFetchEvent extends Event {
	public function __construct(
		protected string $userId,
	) {
		parent::__construct();
	}

	public function getUserId(): string {
		return $this->userId;
	}
}
