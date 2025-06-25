<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setThreadId(int $threadId)
 * @method int getThreadId()
 * @method void setAttendeeId(int $attendeeId)
 * @method int getAttendeeId()
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
 *
 * @psalm-import-type TalkThreadAttendee from ResponseDefinitions
 */
class ThreadAttendee extends Entity implements \JsonSerializable {
	protected int $roomId = 0;
	protected int $threadId = 0;
	protected int $attendeeId = 0;
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

	/**
	 * @return TalkThreadAttendee
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'notificationLevel' => min(3, max(0, $this->getNotificationLevel())),
			'lastReadMessage' => max(0, $this->getLastReadMessage()),
			'lastMentionMessage' => max(0, $this->getLastMentionMessage()),
			'lastMentionDirect' => max(0, $this->getLastMentionDirect()),
			'readPrivacy' => min(1, max(0, $this->getReadPrivacy())),
		];
	}
}
