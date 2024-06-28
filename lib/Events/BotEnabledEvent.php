<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Model\BotServer;
use OCA\Talk\Room;

class BotEnabledEvent extends ARoomEvent {

	public function __construct(
		Room $room,
		protected BotServer $botServer,
	) {
		parent::__construct($room);
	}

	public function getBotServer(): BotServer {
		return $this->botServer;
	}
}
