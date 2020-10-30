<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setRoomId(int $roomId)
 * @method string getRoomId()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setDisplayName(string $displayName)
 * @method string getDisplayName()
 * @method void setPin(string $pin)
 * @method string getPin()
 * @method void setParticipantType(int $participantType)
 * @method int getParticipantType()
 * @method void setFavorite(bool $favorite)
 * @method bool isFavorite()
 * @method void setNotificationLevel(int $notificationLevel)
 * @method int getNotificationLevel()
 * @method void setLastJoinedCall(int $lastJoinedCall)
 * @method int getLastJoinedCall()
 * @method void setLastReadMessage(int $lastReadMessage)
 * @method int getLastReadMessage()
 * @method void setLastMentionMessage(int $lastMentionMessage)
 * @method int getLastMentionMessage()
 */
class Attendee extends Entity {

	public const ACTOR_USERS = 'users';
	public const ACTOR_GUESTS = 'guests';
	public const ACTOR_EMAILS = 'emails';

	/** @var int */
	protected $roomId;

	/** @var string */
	protected $actorType;

	/** @var string */
	protected $actorId;

	/** @var string */
	protected $displayName;

	/** @var string */
	protected $pin;

	/** @var int */
	protected $participantType;

	/** @var bool */
	protected $favorite;

	/** @var int */
	protected $notificationLevel;

	/** @var int */
	protected $lastJoinedCall;

	/** @var int */
	protected $lastReadMessage;

	/** @var int */
	protected $lastMentionMessage;

	public function __construct() {
		$this->addType('roomId', 'int');
		$this->addType('actorType', 'string');
		$this->addType('actorId', 'string');
		$this->addType('displayName', 'string');
		$this->addType('pin', 'string');
		$this->addType('participantType', 'int');
		$this->addType('favorite', 'bool');
		$this->addType('notificationLevel', 'int');
		$this->addType('lastJoinedCall', 'int');
		$this->addType('lastReadMessage', 'int');
		$this->addType('lastMentionMessage', 'int');
	}

	/**
	 * @return array
	 */
	public function asArray(): array {
		return [
			'id' => $this->getId(),
			'room_id' => $this->getRoomId(),
			'actor_type' => $this->getActorType(),
			'actor_id' => $this->getActorId(),
			// FIXME 'display_name' => $this->getDisplayName(),
			'pin' => $this->getPin(),
			'participant_type' => $this->getParticipantType(),
			'favorite' => $this->isFavorite(),
			'notification_level' => $this->getNotificationLevel(),
			'last_joined_call' => $this->getLastJoinedCall(),
			'last_read_message' => $this->getLastReadMessage(),
			'last_mention_message' => $this->getLastMentionMessage(),
		];
	}
}
