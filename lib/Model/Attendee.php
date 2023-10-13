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
 * @method int getRoomId()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setDisplayName(string $displayName)
 * @method void setPin(string $pin)
 * @method null|string getPin()
 * @method void setParticipantType(int $participantType)
 * @method int getParticipantType()
 * @method void setFavorite(bool $favorite)
 * @method bool isFavorite()
 * @method void setNotificationLevel(int $notificationLevel)
 * @method int getNotificationLevel()
 * @method void setNotificationCalls(int $notificationCalls)
 * @method int getNotificationCalls()
 * @method void setLastJoinedCall(int $lastJoinedCall)
 * @method int getLastJoinedCall()
 * @method void setLastReadMessage(int $lastReadMessage)
 * @method int getLastReadMessage()
 * @method void setLastMentionMessage(int $lastMentionMessage)
 * @method int getLastMentionMessage()
 * @method void setLastMentionDirect(int $lastMentionDirect)
 * @method int getLastMentionDirect()
 * @method void setReadPrivacy(int $readPrivacy)
 * @method int getReadPrivacy()
 * @method void setPermissions(int $permissions)
 * @internal
 * @method int getPermissions()
 * @method void setAccessToken(string $accessToken)
 * @method null|string getAccessToken()
 * @method void setRemoteId(string $remoteId)
 * @method string getRemoteId()
 * @method void setPhoneNumber(?string $phoneNumber)
 * @method null|string getPhoneNumber()
 * @method void setCallId(?string $callId)
 * @method null|string getCallId()
 */
class Attendee extends Entity {
	public const ACTOR_USERS = 'users';
	public const ACTOR_GROUPS = 'groups';
	public const ACTOR_GUESTS = 'guests';
	public const ACTOR_EMAILS = 'emails';
	public const ACTOR_CIRCLES = 'circles';
	public const ACTOR_BRIDGED = 'bridged';
	public const ACTOR_BOTS = 'bots';
	public const ACTOR_FEDERATED_USERS = 'federated_users';
	public const ACTOR_PHONES = 'phones';

	// Special actor IDs
	public const ACTOR_BOT_PREFIX = 'bot-';
	public const ACTOR_ID_CLI = 'cli';
	public const ACTOR_ID_CHANGELOG = 'changelog';

	public const PERMISSIONS_DEFAULT = 0;
	public const PERMISSIONS_CUSTOM = 1;
	public const PERMISSIONS_CALL_START = 2;
	public const PERMISSIONS_CALL_JOIN = 4;
	public const PERMISSIONS_LOBBY_IGNORE = 8;
	public const PERMISSIONS_PUBLISH_AUDIO = 16;
	public const PERMISSIONS_PUBLISH_VIDEO = 32;
	public const PERMISSIONS_PUBLISH_SCREEN = 64;
	public const PERMISSIONS_CHAT = 128;
	public const PERMISSIONS_MAX_DEFAULT = // Max int (when all permissions are granted as default)
		self::PERMISSIONS_CALL_START
		| self::PERMISSIONS_CALL_JOIN
		| self::PERMISSIONS_LOBBY_IGNORE
		| self::PERMISSIONS_PUBLISH_AUDIO
		| self::PERMISSIONS_PUBLISH_VIDEO
		| self::PERMISSIONS_PUBLISH_SCREEN
		| self::PERMISSIONS_CHAT
	;
	public const PERMISSIONS_MAX_CUSTOM = self::PERMISSIONS_MAX_DEFAULT | self::PERMISSIONS_CUSTOM; // Max int (when all permissions are granted as custom)

	public const PERMISSIONS_MODIFY_SET = 'set';
	public const PERMISSIONS_MODIFY_REMOVE = 'remove';
	public const PERMISSIONS_MODIFY_ADD = 'add';

	/** @var int */
	protected $roomId;

	/** @var string */
	protected $actorType;

	/** @var string */
	protected $actorId;

	/** @var null|string */
	protected $displayName;

	/** @var null|string */
	protected $pin;

	/** @var int */
	protected $participantType;

	/** @var bool */
	protected $favorite;

	/** @var int */
	protected $notificationLevel;

	/** @var int */
	protected $notificationCalls;

	/** @var int */
	protected $lastJoinedCall;

	/** @var int */
	protected $lastReadMessage;

	/** @var int */
	protected $lastMentionMessage;

	/** @var int */
	protected $lastMentionDirect;

	/** @var int */
	protected $readPrivacy;

	/** @var int */
	protected $permissions;

	/** @var string */
	protected $accessToken;

	/** @var string */
	protected $remoteId;

	/** @var null|string */
	protected $phoneNumber;

	/** @var null|string */
	protected $callId;

	public function __construct() {
		$this->addType('roomId', 'int');
		$this->addType('actorType', 'string');
		$this->addType('actorId', 'string');
		$this->addType('displayName', 'string');
		$this->addType('pin', 'string');
		$this->addType('participantType', 'int');
		$this->addType('favorite', 'bool');
		$this->addType('notificationLevel', 'int');
		$this->addType('notificationCalls', 'int');
		$this->addType('lastJoinedCall', 'int');
		$this->addType('lastReadMessage', 'int');
		$this->addType('lastMentionMessage', 'int');
		$this->addType('lastMentionDirect', 'int');
		$this->addType('readPrivacy', 'int');
		$this->addType('permissions', 'int');
		$this->addType('accessToken', 'string');
		$this->addType('remote_id', 'string');
		$this->addType('phone_number', 'string');
		$this->addType('call_id', 'string');
	}

	public function getDisplayName(): string {
		return (string) $this->displayName;
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
			'display_name' => $this->getDisplayName(),
			'pin' => $this->getPin(),
			'participant_type' => $this->getParticipantType(),
			'favorite' => $this->isFavorite(),
			'notification_level' => $this->getNotificationLevel(),
			'notification_calls' => $this->getNotificationCalls(),
			'last_joined_call' => $this->getLastJoinedCall(),
			'last_read_message' => $this->getLastReadMessage(),
			'last_mention_message' => $this->getLastMentionMessage(),
			'last_mention_direct' => $this->getLastMentionDirect(),
			'read_privacy' => $this->getReadPrivacy(),
			'permissions' => $this->getPermissions(),
			'access_token' => $this->getAccessToken(),
			'remote_id' => $this->getRemoteId(),
			'phone_number' => $this->getPhoneNumber(),
			'call_id' => $this->getCallId(),
		];
	}
}
