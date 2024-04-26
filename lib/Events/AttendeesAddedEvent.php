<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCP\Comments\IComment;

class AttendeesAddedEvent extends AttendeesEvent {
	protected ?IComment $lastMessage = null;

	/**
	 * @param Attendee[] $attendees
	 */
	public function __construct(
		Room $room,
		array $attendees,
		protected bool $skipLastMessageUpdate = false,
	) {
		parent::__construct($room, $attendees);
	}

	public function shouldSkipLastMessageUpdate(): bool {
		return $this->skipLastMessageUpdate;
	}

	public function setLastMessage(IComment $lastMessage): void {
		$this->lastMessage = $lastMessage;
	}

	public function getLastMessage(): ?IComment {
		return $this->lastMessage;
	}
}
