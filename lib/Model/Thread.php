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
 * @method void setLastMessageId(int $lastMessageId)
 * @method int getLastMessageId()
 * @method void setNumReplies(int $numReplies)
 * @method int getNumReplies()
 *
 * @psalm-import-type TalkThread from ResponseDefinitions
 */
class Thread extends Entity implements \JsonSerializable {
	protected int $roomId = 0;
	protected int $lastMessageId = 0;
	protected int $numReplies = 0;

	public function __construct() {
		$this->addType('roomId', Types::BIGINT);
		$this->addType('lastMessageId', Types::BIGINT);
		$this->addType('numReplies', Types::BIGINT);
	}

	/**
	 * @return TalkThread
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => max(1, $this->getId()),
			'roomId' => max(1, $this->getRoomId()),
			'lastMessageId' => max(0, $this->getLastMessageId()),
			'numReplies' => max(0, $this->getNumReplies()),
		];
	}
}
