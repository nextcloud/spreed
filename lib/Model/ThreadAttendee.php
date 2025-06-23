<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setThreadId(int $threadId)
 * @method int getThreadId()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setNotificationLevel(int $notificationLevel)
 * @method int getNotificationLevel()
 * @method void setLastReadMessage(int $lastReadMessage)
 * @method int getLastReadMessage()
 * @method void setLastMentionMessage(int $lastMentionMessage)
 * @method int getLastMentionMessage()
 * @method void setLastMentionDirect(int $lastMentionDirect)
 * @method int getLastMentionDirect()
 * @method void setReadPrivacy(int $readPrivacy)
 * @method int getReadPrivacy()
 */
class ThreadAttendee extends Entity {
	protected int $roomId = 0;
	protected int $threadId = 0;
	protected string $actorType = '';
	protected string $actorId = '';
	protected int $notificationLevel = 0;
	protected int $lastReadMessage = 0;
	protected int $lastMentionMessage = 0;
	protected int $lastMentionDirect = 0;
	protected int $readPrivacy = 0;

	public function __construct() {
		$this->addType('roomId', Types::BIGINT);
		$this->addType('threadId', Types::BIGINT);
		$this->addType('actorType', Types::STRING);
		$this->addType('actorId', Types::STRING);
		$this->addType('notificationLevel', Types::INTEGER);
		$this->addType('lastReadMessage', Types::INTEGER);
		$this->addType('lastMentionMessage', Types::INTEGER);
		$this->addType('lastMentionDirect', Types::BIGINT);
		$this->addType('readPrivacy', Types::SMALLINT);
	}
}
