<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Participant;
use OCA\Talk\Room;

abstract class ACallStartedEvent extends ARoomModifiedEvent {
	/**
	 * @param array<AParticipantModifiedEvent::DETAIL_*, bool> $details
	 */
	public function __construct(
		Room $room,
		?\DateTime $newValue,
		protected int $callFlag,
		protected array $details,
		?Participant $actor,
	) {
		parent::__construct(
			$room,
			self::PROPERTY_ACTIVE_SINCE,
			$newValue,
			null,
			$actor,
		);
	}

	public function getCallFlag(): int {
		return $this->callFlag;
	}

	/**
	 * @param AParticipantModifiedEvent::DETAIL_* $detail
	 */
	public function getDetail(string $detail): ?bool {
		return $this->details[$detail] ?? null;
	}
}
