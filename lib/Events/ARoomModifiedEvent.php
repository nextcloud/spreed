<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

abstract class ARoomModifiedEvent extends RoomEvent {
	public const PROPERTY_AVATAR = 'avatar';
	public const PROPERTY_BREAKOUT_ROOM_MODE = 'breakoutRoomMode';
	public const PROPERTY_BREAKOUT_ROOM_STATUS = 'breakoutRoomStatus';
	public const PROPERTY_CALL_PERMISSIONS = 'callPermissions';
	public const PROPERTY_CALL_RECORDING = 'callRecording';
	public const PROPERTY_DEFAULT_PERMISSIONS = 'defaultPermissions';
	public const PROPERTY_DESCRIPTION = 'description';
	public const PROPERTY_IN_CALL = 'inCall';
	public const PROPERTY_LISTABLE = 'listable';
	public const PROPERTY_LOBBY = 'lobby';
	public const PROPERTY_MESSAGE_EXPIRATION = 'messageExpiration';
	public const PROPERTY_NAME = 'name';
	public const PROPERTY_PASSWORD = 'password';
	public const PROPERTY_READ_ONLY = 'readOnly';
	public const PROPERTY_RECORDING_CONSENT = 'recordingConsent';
	public const PROPERTY_SIP_ENABLED = 'sipEnabled';
	public const PROPERTY_TYPE = 'type';

	/**
	 * @param self::PROPERTY_* $property
	 */
	public function __construct(
		Room $room,
		protected string $property,
		protected string|int $newValue,
		protected string|int|null $oldValue = null,
		protected ?Participant $actor = null,
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

	public function getActor(): ?Participant {
		return $this->actor;
	}
}
