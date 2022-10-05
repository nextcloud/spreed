<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Model;

use OCA\Talk\Participant;
use OCA\Talk\Webinary;
use OCP\AppFramework\Db\Entity;
use OCP\Comments\IComment;

/**
 * @method void setType(int $type)
 * @method int getType()
 * @method void setReadOnly(int $readOnly)
 * @method int getReadOnly()
 * @method void setListable(int $listable)
 * @method int getListable()
 * @method void setMessageExpiration(int $messageExpiration)
 * @method int getMessageExpiration()
 * @method void setLobbyState(int $lobbyState)
 * @method int getLobbyState()
 * @method void setSipEnabled(int $sipEnabled)
 * @method int getSipEnabled()
 * @method void setAssignedSignalingServer(?int $assignedSignalingServer)
 * @method int|null getAssignedSignalingServer()
 * @method void setToken(string $token)
 * @method string getToken()
 * @method void setName(string $name)
 * @method string getName()
 * @method void setDescription(string $description)
 * @method string getDescription()
 * @method void setPassword(string $password)
 * @method string getPassword()
 * @method void setRemoteServer(string $remoteServer)
 * @method string getRemoteServer()
 * @method void setRemoteToken(string $remoteToken)
 * @method string getRemoteToken()
 * @method void setActiveGuests(int $activeGuests)
 * @method int getActiveGuests()
 * @method void setDefaultPermissions(int $defaultPermissions)
 * @method int getDefaultPermissions()
 * @method void setCallPermissions(int $callPermissions)
 * @method int getCallPermissions()
 * @method void setCallFlag(int $callFlag)
 * @method int getCallFlag()
 * @method void setActiveSince(?\DateTime $activeSince)
 * @method \DateTime|null getActiveSince()
 * @method void setLastActivity(?\DateTime $lastActivity)
 * @method \DateTime|null getLastActivity()
 * @method void setLastMessageId(int $lastMessageId)
 * @method int getLastMessageId()
 * @method void setLobbyTimer(?\DateTime $lobbyTimer)
 * @method \DateTime|null getLobbyTimer()
 * @method void setObjectType(string $objectType)
 * @method string getObjectType()
 * @method void setObjectId(string $objectId)
 * @method string getObjectId()
 */
class Room extends Entity {
	/**
	 * Regex that matches SIP incompatible rooms:
	 * 1. duplicate digit: …11…
	 * 2. leading zero: 0…
	 * 3. non-digit: …a…
	 */
	public const SIP_INCOMPATIBLE_REGEX = '/((\d)(?=\2+)|^0|\D)/';

	public const TYPE_UNKNOWN = -1;
	public const TYPE_ONE_TO_ONE = 1;
	public const TYPE_GROUP = 2;
	public const TYPE_PUBLIC = 3;
	public const TYPE_CHANGELOG = 4;

	public const READ_WRITE = 0;
	public const READ_ONLY = 1;

	/**
	 * Only visible when joined
	 */
	public const LISTABLE_NONE = 0;

	/**
	 * Searchable by all regular users and moderators, even when not joined, excluding users from the guest app
	 */
	public const LISTABLE_USERS = 1;

	/**
	 * Searchable by everyone, which includes guest users (from guest app), even when not joined
	 */
	public const LISTABLE_ALL = 2;

	public const START_CALL_EVERYONE = 0;
	public const START_CALL_USERS = 1;
	public const START_CALL_MODERATORS = 2;
	public const START_CALL_NOONE = 3;

	protected int $type = self::TYPE_UNKNOWN;
	protected int $readOnly = self::READ_WRITE;
	protected int $listable = self::LISTABLE_NONE;
	protected int $messageExpiration = 0;
	protected int $lobbyState = Webinary::LOBBY_NONE;
	protected int $sipEnabled = Webinary::SIP_DISABLED;
	protected ?int $assignedSignalingServer = null;
	protected string $token = '';
	protected string $name = '';
	protected string $description = '';
	protected string $password = '';
	protected string $remoteServer = '';
	protected string $remoteToken = '';
	protected int $activeGuests = 0;
	protected int $defaultPermissions = Attendee::PERMISSIONS_DEFAULT;
	protected int $callPermissions = Attendee::PERMISSIONS_DEFAULT;
	protected int $callFlag = Participant::FLAG_DISCONNECTED;
	protected ?\DateTime $activeSince = null;
	protected ?\DateTime $lastActivity = null;
	protected int $lastMessageId = 0;
	protected ?\DateTime $lobbyTimer = null;
	protected string $objectType = '';
	protected string $objectId = '';

	public function __construct() {
		$this->addType('type', 'int');
		$this->addType('readOnly', 'int');
		$this->addType('listable', 'int');
		$this->addType('messageExpiration', 'int');
		$this->addType('lobbyState', 'int');
		$this->addType('sipEnabled', 'int');
		$this->addType('assignedSignalingServer', 'int'); // FIXME nullable?
		$this->addType('token', 'string');
		$this->addType('name', 'string');
		$this->addType('description', 'string');
		$this->addType('password', 'string');
		$this->addType('remoteServer', 'string');
		$this->addType('remoteToken', 'string');
		$this->addType('activeGuests', 'int');
		$this->addType('defaultPermissions', 'int');
		$this->addType('callPermissions', 'int');
		$this->addType('callFlag', 'int');
		$this->addType('activeSince', 'datetime'); // FIXME nullable?
		$this->addType('lastActivity', 'datetime'); // FIXME nullable?
		$this->addType('lastMessageId', 'int');
		// FIXME $this->addType('lastMessage', 'IComment');
		$this->addType('lobbyTimer', 'datetime'); // FIXME nullable?
		$this->addType('objectType', 'string');
		$this->addType('objectId', 'string');
	}
}
