<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

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
 * @method void setArchived(bool $archived)
 * @method bool isArchived()
 * @method void setImportant(bool $important)
 * @method bool isImportant()
 * @method void setSensitive(bool $sensitive)
 * @method bool isSensitive()
 * @internal
 * @method int getPermissions()
 * @method void setAccessToken(string $accessToken)
 * @method null|string getAccessToken()
 * @method void setRemoteId(string $remoteId)
 * @method string getRemoteId()
 * @method void setInvitedCloudId(string $invitedCloudId)
 * @method string getInvitedCloudId()
 * @method void setPhoneNumber(?string $phoneNumber)
 * @method null|string getPhoneNumber()
 * @method void setCallId(?string $callId)
 * @method null|string getCallId()
 * @method void setState(int $state)
 * @method int getState()
 * @method void setUnreadMessages(int $unreadMessages)
 * @method int getUnreadMessages()
 * @method void setLastAttendeeActivity(int $lastAttendeeActivity)
 * @method int getLastAttendeeActivity()
 * @method void setHasUnreadThreads(bool $hasUnreadThreads)
 * @method bool getHasUnreadThreads()
 * @method void setHasUnreadThreadMentions(bool $hasUnreadThreadMentions)
 * @method bool getHasUnreadThreadMentions()
 * @method void setHasUnreadThreadDirects(bool $hasUnreadThreadDirects)
 * @method bool getHasUnreadThreadDirects()
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
	public const ACTOR_ID_SYSTEM = 'system';
	public const ACTOR_ID_SAMPLE = 'sample';
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
	public const PERMISSIONS_MAX_DEFAULT // Max int (when all permissions are granted as default)
		= self::PERMISSIONS_CALL_START
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

	protected int $roomId = 0;
	protected string $actorType = '';
	protected string $actorId = '';
	protected ?string $displayName = null;
	protected ?string $pin = null;
	protected int $participantType = 0;
	protected bool $favorite = false;
	protected int $notificationLevel = 0;
	protected int $notificationCalls = 0;
	protected bool $archived = false;
	protected bool $important = false;
	protected bool $sensitive = false;
	protected int $lastJoinedCall = 0;
	protected int $lastReadMessage = 0;
	protected int $lastMentionMessage = 0;
	protected int $lastMentionDirect = 0;
	protected int $readPrivacy = 0;
	protected int $permissions = 0;
	protected ?string $accessToken = null;
	protected ?string $remoteId = null;
	protected ?string $invitedCloudId = null;
	protected ?string $phoneNumber = null;
	protected ?string $callId = null;
	protected int $state = 0;
	protected int $unreadMessages = 0;
	protected int $lastAttendeeActivity = 0;
	protected bool $hasUnreadThreads = false;
	protected bool $hasUnreadThreadMentions = false;
	protected bool $hasUnreadThreadDirects = false;

	public function __construct() {
		$this->addType('roomId', Types::BIGINT);
		$this->addType('actorType', Types::STRING);
		$this->addType('actorId', Types::STRING);
		$this->addType('displayName', Types::STRING);
		$this->addType('pin', Types::STRING);
		$this->addType('participantType', Types::SMALLINT);
		$this->addType('favorite', Types::BOOLEAN);
		$this->addType('archived', Types::BOOLEAN);
		$this->addType('important', Types::BOOLEAN);
		$this->addType('sensitive', Types::BOOLEAN);
		$this->addType('notificationLevel', Types::INTEGER);
		$this->addType('notificationCalls', Types::INTEGER);
		$this->addType('lastJoinedCall', Types::INTEGER);
		$this->addType('lastReadMessage', Types::INTEGER);
		$this->addType('lastMentionMessage', Types::INTEGER);
		$this->addType('lastMentionDirect', Types::BIGINT);
		$this->addType('readPrivacy', Types::SMALLINT);
		$this->addType('permissions', Types::INTEGER);
		$this->addType('accessToken', Types::STRING);
		$this->addType('remoteId', Types::STRING);
		$this->addType('invitedCloudId', Types::STRING);
		$this->addType('phoneNumber', Types::STRING);
		$this->addType('callId', Types::STRING);
		$this->addType('state', Types::SMALLINT);
		$this->addType('unreadMessages', Types::BIGINT);
		$this->addType('lastAttendeeActivity', Types::BIGINT);
		$this->addType('hasUnreadThreads', Types::BOOLEAN);
		$this->addType('hasUnreadThreadMentions', Types::BOOLEAN);
		$this->addType('hasUnreadThreadDirects', Types::BOOLEAN);
	}

	public function getDisplayName(): string {
		return (string)$this->displayName;
	}
}
