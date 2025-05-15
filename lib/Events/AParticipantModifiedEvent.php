<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Participant;
use OCA\Talk\Room;

abstract class AParticipantModifiedEvent extends ARoomEvent {
	public const PROPERTY_IN_CALL = 'inCall';
	public const PROPERTY_NAME = 'name';
	public const PROPERTY_PERMISSIONS = 'permissions';
	public const PROPERTY_RESEND_CALL = 'resend_call_notification';
	public const PROPERTY_TYPE = 'type';

	public const DETAIL_IN_CALL_SILENT = 'silent';
	public const DETAIL_IN_CALL_SILENT_FOR = 'silentFor';
	public const DETAIL_IN_CALL_END_FOR_EVERYONE = 'endForEveryone';

	/**
	 * @param self::PROPERTY_* $property
	 * @param array<self::DETAIL_*, bool> $details
	 */
	public function __construct(
		Room $room,
		protected Participant $participant,
		protected string $property,
		protected string|int $newValue,
		protected string|int|null $oldValue = null,
		protected array $details = [],
	) {
		parent::__construct($room);
	}

	public function getParticipant(): Participant {
		return $this->participant;
	}

	public function getProperty(): string {
		return $this->property;
	}

	public function getNewValue(): string|int {
		return $this->newValue;
	}

	public function getOldValue(): string|int|null {
		return $this->oldValue;
	}

	/**
	 * @param self::DETAIL_* $detail
	 */
	public function getDetail(string $detail): ?bool {
		return $this->details[$detail] ?? null;
	}
}
