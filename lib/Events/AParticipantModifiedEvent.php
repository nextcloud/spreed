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

abstract class AParticipantModifiedEvent extends RoomEvent {
	public const PROPERTY_IN_CALL = 'inCall';
	public const PROPERTY_NAME = 'name';
	public const PROPERTY_PERMISSIONS = 'permissions';
	public const PROPERTY_TYPE = 'type';

	public const DETAIL_IN_CALL_SILENT = 'silent';
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
