<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setLastMessageId(int $lastMessageId)
 * @method int getLastMessageId()
 * @method void setNumReplies(int $numReplies)
 * @method int getNumReplies()
 * @method void setLastActivity(\DateTime $lastActivity)
 * @method \DateTime|null getLastActivity()
 * @method void setName(string $name)
 *
 * @psalm-import-type TalkThread from ResponseDefinitions
 */
class Thread extends Entity {
	public const THREAD_NONE = 0;
	public const THREAD_CREATE = -1;
	protected int $roomId = 0;
	protected int $lastMessageId = 0;
	protected int $numReplies = 0;
	protected ?\DateTime $lastActivity = null;
	protected string $name = '';

	public function __construct() {
		$this->addType('roomId', Types::BIGINT);
		$this->addType('lastMessageId', Types::BIGINT);
		$this->addType('numReplies', Types::BIGINT);
		$this->addType('lastActivity', Types::DATETIME);
		$this->addType('name', Types::STRING);
	}

	public static function createFromRow(array $row): Thread {
		$thread = new Thread();
		$thread->setId((int)$row['t_id']);
		$thread->setRoomId((int)$row['room_id']);
		$thread->setLastMessageId((int)$row['last_message_id']);
		$thread->setNumReplies((int)$row['num_replies']);
		$thread->setLastActivity(new \DateTime($row['last_activity']));
		$thread->setName($row['name']);
		return $thread;
	}

	/**
	 * @param string $json
	 * @return Thread
	 * @throws \JsonException
	 */
	public static function fromJson(string $json): Thread {
		$row = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
		$thread = new Thread();
		$thread->setId((int)$row['id']);
		$thread->setRoomId((int)$row['room_id']);
		$thread->setLastMessageId((int)$row['last_message_id']);
		$thread->setNumReplies((int)$row['num_replies']);
		$thread->setLastActivity(new \DateTime('@' . $row['last_activity']));
		$thread->setName($row['name']);
		return $thread;
	}

	/**
	 * @return string
	 * @throws \JsonException
	 */
	public function toJson(): string {
		return json_encode([
			'id' => $this->getId(),
			'room_id' => $this->getRoomId(),
			'last_message_id' => $this->getLastMessageId(),
			'num_replies' => $this->getNumReplies(),
			'last_activity' => $this->getLastActivity()?->getTimestamp() ?? 0,
			'name' => $this->getName(),
		], flags: JSON_THROW_ON_ERROR);
	}

	public function getName(): string {
		if ($this->name !== '') {
			return $this->name;
		}

		// FIXME temporary workaround against empty titles
		return 'Thread #' . $this->getId();
	}

	/**
	 * @return TalkThread
	 */
	public function toArray(Room $room): array {
		return [
			'id' => max(1, $this->getId()),
			// 'roomId' => max(1, $this->getRoomId()),
			'roomToken' => $room->getToken(),
			'lastMessageId' => max(0, $this->getLastMessageId()),
			'numReplies' => max(0, $this->getNumReplies()),
			'lastActivity' => max(0, $this->getLastActivity()?->getTimestamp() ?? 0),
			'title' => $this->getName(),
		];
	}
}
