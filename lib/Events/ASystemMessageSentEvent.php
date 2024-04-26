<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\Comments\IComment;

abstract class ASystemMessageSentEvent extends AMessageSentEvent {
	public function __construct(
		Room $room,
		IComment $comment,
		?Participant $participant = null,
		bool $silent = false,
		?IComment $parent = null,
		protected bool $skipLastActivityUpdate = false,
	) {
		parent::__construct(
			$room,
			$comment,
			$participant,
			$silent,
			$parent,
		);
	}

	/**
	 * If multiple messages will be posted (e.g. when adding multiple users to a room)
	 * we can skip the last message and last activity update until the last entry
	 * was created and then update with those values.
	 * This will replace O(n) with 1 database update.
	 *
	 * A {@see SystemMessagesMultipleSentEvent} will be triggered
	 * as a final event when all system messages have been created.
	 *
	 * @return bool
	 */
	public function shouldSkipLastActivityUpdate(): bool {
		return $this->skipLastActivityUpdate;
	}
}
