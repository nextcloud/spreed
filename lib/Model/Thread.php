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
 * @method string getName()
 *
 * @psalm-import-type TalkThread from ResponseDefinitions
 */
class Thread extends Entity {
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
			// 'name' => $this->getName(),
		];
	}
}
