<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Room;

abstract class AAttendeeRemovedEvent extends ARoomEvent {
	public const REASON_REMOVED = 'remove';
	public const REASON_REMOVED_ALL = 'remove_all';
	public const REASON_LEFT = 'leave';

	/**
	 * @param self::REASON_* $reason
	 * @param Session[] $sessions
	 */
	public function __construct(
		Room $room,
		protected Attendee $attendee,
		protected string $reason,
		protected array $sessions,
	) {
		parent::__construct($room);
	}

	public function getAttendee(): Attendee {
		return $this->attendee;
	}

	/**
	 * @return self::REASON_*
	 */
	public function getReason(): string {
		return $this->reason;
	}

	/**
	 * @return Session[]
	 */
	public function getSessions(): array {
		return $this->sessions;
	}
}
