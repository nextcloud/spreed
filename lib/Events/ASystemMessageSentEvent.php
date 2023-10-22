<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
		protected bool $skipLastActivityUpdate = false,
	) {
		parent::__construct(
			$room,
			$comment,
			$participant,
			$silent
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
