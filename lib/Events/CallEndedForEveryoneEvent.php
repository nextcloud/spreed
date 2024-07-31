<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Participant;
use OCA\Talk\Room;

class CallEndedForEveryoneEvent extends ACallEndedForEveryoneEvent {
	public function __construct(
		Room $room,
		?Participant $actor,
		\DateTime $oldActiveSince,
		/** @var string[] */
		protected array $sessionIds = [],
		/** @var string[] */
		protected array $userIds = [],
	) {
		parent::__construct(
			$room,
			$actor,
			$oldActiveSince,
		);
	}

	/**
	 * @return string[]
	 */
	public function getSessionIds(): array {
		return $this->sessionIds;
	}

	/**
	 * @return string[]
	 */
	public function getUserIds(): array {
		return $this->userIds;
	}
}
