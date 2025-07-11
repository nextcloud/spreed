<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Participant;
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

	public function __construct() {
		$this->addType('roomId', Types::BIGINT);
		$this->addType('threadId', Types::BIGINT);
		$this->addType('attendeeId', Types::BIGINT);
		$this->addType('actorType', Types::STRING);
		$this->addType('actorId', Types::STRING);
		$this->addType('notificationLevel', Types::INTEGER);
	}

	public static function createFromParticipant(int $threadId, Participant $participant): ThreadAttendee {
		$attendee = new ThreadAttendee();
		$attendee->setRoomId($participant->getRoom()->getId());
		$attendee->setThreadId($threadId);
		$attendee->setAttendeeId($participant->getAttendee()->getId());
		$attendee->setNotificationLevel(Participant::NOTIFY_DEFAULT);
		$attendee->setActorType($participant->getAttendee()->getActorType());
		$attendee->setActorId($participant->getAttendee()->getActorId());
		return $attendee;
	}

	/**
	 * @return TalkThreadAttendee
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'notificationLevel' => min(3, max(0, $this->getNotificationLevel())),
		];
	}
}
