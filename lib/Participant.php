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

namespace OCA\Spreed;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class Participant {
	const OWNER = 1;
	const MODERATOR = 2;
	const USER = 3;
	const GUEST = 4;
	const USER_SELF_JOINED = 5;
	const GUEST_MODERATOR = 6;

	const FLAG_DISCONNECTED = 0;
	const FLAG_IN_CALL = 1;
	const FLAG_WITH_AUDIO = 2;
	const FLAG_WITH_VIDEO = 4;

	const NOTIFY_DEFAULT = 0;
	const NOTIFY_ALWAYS = 1;
	const NOTIFY_MENTION = 2;
	const NOTIFY_NEVER = 3;

	/** @var IDBConnection */
	protected $db;
	/** @var Room */
	protected $room;
	/** @var string */
	protected $user;
	/** @var int */
	protected $participantType;
	/** @var int */
	protected $lastPing;
	/** @var string */
	protected $sessionId;
	/** @var int */
	protected $inCall;
	/** @var int */
	protected $notificationLevel;
	/** @var bool */
	private $isFavorite;
	/** @var int */
	private $lastReadMessage;
	/** @var int */
	private $lastMentionMessage;

	public function __construct(IDBConnection $db, Room $room, string $user, int $participantType, int $lastPing, string $sessionId, int $inCall, int $notificationLevel,  bool $isFavorite, int $lastReadMessage, int $lastMentionMessage) {
		$this->db = $db;
		$this->room = $room;
		$this->user = $user;
		$this->participantType = $participantType;
		$this->lastPing = $lastPing;
		$this->sessionId = $sessionId;
		$this->inCall = $inCall;
		$this->notificationLevel = $notificationLevel;
		$this->isFavorite = $isFavorite;
		$this->lastReadMessage = $lastReadMessage;
		$this->lastMentionMessage = $lastMentionMessage;
	}

	public function getUser(): string {
		return $this->user;
	}

	public function getParticipantType(): int {
		return $this->participantType;
	}

	public function isGuest(): bool {
		return \in_array($this->participantType, [self::GUEST, self::GUEST_MODERATOR], true);
	}

	public function hasModeratorPermissions(bool $guestModeratorAllowed = true): bool {
		if (!$guestModeratorAllowed) {
			return \in_array($this->participantType, [self::OWNER, self::MODERATOR], true);
		}

		return \in_array($this->participantType, [self::OWNER, self::MODERATOR, self::GUEST_MODERATOR], true);
	}

	public function getLastPing(): int {
		return $this->lastPing;
	}

	public function getSessionId(): string {
		return $this->sessionId;
	}

	public function getInCallFlags(): int {
		return $this->inCall;
	}

	public function isFavorite(): bool {
		return $this->isFavorite;
	}

	public function setFavorite(bool $favor): bool {
		if (!$this->user) {
			return false;
		}

		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('favorite', $query->createNamedParameter((int) $favor, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('user_id', $query->createNamedParameter($this->user)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($this->room->getId())));
		$query->execute();

		$this->isFavorite = $favor;
		return true;
	}

	public function getNotificationLevel(): int {
		return $this->notificationLevel;
	}

	public function setNotificationLevel(int $notificationLevel): bool {
		if (!$this->user) {
			return false;
		}

		if (!\in_array($notificationLevel, [
			self::NOTIFY_ALWAYS,
			self::NOTIFY_MENTION,
			self::NOTIFY_NEVER
		], true)) {
			return false;
		}

		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('notification_level', $query->createNamedParameter($notificationLevel, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('user_id', $query->createNamedParameter($this->user)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($this->room->getId())));
		$query->execute();

		$this->notificationLevel = $notificationLevel;
		return true;
	}

	public function getLastReadMessage(): int {
		return $this->lastReadMessage;
	}

	public function setLastReadMessage(int $messageId): bool {
		if (!$this->user) {
			return false;
		}

		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('last_read_message', $query->createNamedParameter($messageId, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('user_id', $query->createNamedParameter($this->user)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($this->room->getId())));
		$query->execute();

		$this->lastReadMessage = $messageId;
		return true;
	}

	public function getLastMentionMessage(): int {
		return $this->lastMentionMessage;
	}

	public function setLastMentionMessage(int $messageId): bool {
		if (!$this->user) {
			return false;
		}

		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('last_mention_message', $query->createNamedParameter($messageId, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('user_id', $query->createNamedParameter($this->user)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($this->room->getId())));
		$query->execute();

		$this->lastMentionMessage = $messageId;
		return true;
	}
}
