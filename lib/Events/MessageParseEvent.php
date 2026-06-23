<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Model\Message;
use OCA\Talk\Room;

class MessageParseEvent extends ARoomEvent {
	public function __construct(
		Room $room,
		private readonly Message $message,
		private readonly bool $allowInaccurate,
	) {
		parent::__construct($room);
	}

	public function getMessage(): Message {
		return $this->message;
	}

	public function allowInaccurate(): bool {
		return $this->allowInaccurate;
	}
}
