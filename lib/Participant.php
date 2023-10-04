<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCP\IConfig;
use OCP\Server;

class Participant {
	public const OWNER = 1;
	public const MODERATOR = 2;
	public const USER = 3;
	public const GUEST = 4;
	public const USER_SELF_JOINED = 5;
	public const GUEST_MODERATOR = 6;

	public const FLAG_DISCONNECTED = 0;
	public const FLAG_IN_CALL = 1;
	public const FLAG_WITH_AUDIO = 2;
	public const FLAG_WITH_VIDEO = 4;
	public const FLAG_WITH_PHONE = 8;

	public const NOTIFY_DEFAULT = 0;
	public const NOTIFY_ALWAYS = 1;
	public const NOTIFY_MENTION = 2;
	public const NOTIFY_NEVER = 3;

	public const NOTIFY_CALLS_OFF = 0;
	public const NOTIFY_CALLS_ON = 1;

	public const PRIVACY_PUBLIC = 0;
	public const PRIVACY_PRIVATE = 1;

	public function __construct(
		protected Room $room,
		protected Attendee $attendee,
		protected ?Session $session,
	) {
	}

	public function getRoom(): Room {
		return $this->room;
	}

	public function getAttendee(): Attendee {
		return $this->attendee;
	}

	public function getSession(): ?Session {
		return $this->session;
	}

	public function setSession(Session $session): void {
		$this->session = $session;
	}

	public function isGuest(): bool {
		$participantType = $this->attendee->getParticipantType();
		return \in_array($participantType, [self::GUEST, self::GUEST_MODERATOR], true);
	}

	public function hasModeratorPermissions(bool $guestModeratorAllowed = true): bool {
		$participantType = $this->attendee->getParticipantType();
		if (!$guestModeratorAllowed) {
			return \in_array($participantType, [self::OWNER, self::MODERATOR], true);
		}

		return \in_array($participantType, [self::OWNER, self::MODERATOR, self::GUEST_MODERATOR], true);
	}

	public function canStartCall(IConfig $config): bool {
		if ($this->room->getType() === Room::TYPE_NOTE_TO_SELF) {
			return false;
		}

		$defaultStartCall = (int) $config->getAppValue('spreed', 'start_calls', (string) Room::START_CALL_EVERYONE);

		if ($defaultStartCall === Room::START_CALL_NOONE) {
			return false;
		}

		if (!($this->getPermissions() & Attendee::PERMISSIONS_CALL_START)) {
			return false;
		}

		if ($defaultStartCall === Room::START_CALL_EVERYONE) {
			return true;
		}

		if ($defaultStartCall === Room::START_CALL_USERS && (!$this->isGuest() || $this->hasModeratorPermissions())) {
			return true;
		}

		if ($defaultStartCall === Room::START_CALL_MODERATORS && $this->hasModeratorPermissions()) {
			return true;
		}

		return false;
	}

	public function getPermissions(): int {
		$permissions = $this->getPermissionsFromFallbackChain();

		if ($this->hasModeratorPermissions()) {
			// Moderators can always do everything
			$permissions = Attendee::PERMISSIONS_MAX_DEFAULT;
		}

		return $permissions;
	}

	protected function getPermissionsFromFallbackChain(): int {
		if ($this->getAttendee()->getPermissions() !== Attendee::PERMISSIONS_DEFAULT) {
			return $this->getAttendee()->getPermissions();
		}

		if ($this->room->getCallPermissions() !== Attendee::PERMISSIONS_DEFAULT) {
			// The currently ongoing call is in a special mode
			return $this->room->getCallPermissions();
		}

		if ($this->room->getDefaultPermissions() !== Attendee::PERMISSIONS_DEFAULT) {
			// The conversation has some permissions set
			return $this->room->getDefaultPermissions();
		}

		return Server::get(Config::class)->getDefaultPermissions();
	}
}
