<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setStart(int $start)
 * @method int getStart()
 * @method void setEnd(string $end)
 * @method int getEnd()
 * @method void setDescription(string $description)
 * @method string getDescription()
 */
class RoomEvent extends Entity {
	protected int $roomId = 0;
	protected int $start = 0;
	protected int $end = 0;
	protected ?string $description = null;

	public function __construct() {
		$this->addType('roomId', Types::BIGINT);
		$this->addType('start', Types::INTEGER);
		$this->addType('end', Types::INTEGER);
		$this->addType('description', Types::STRING);
	}

	/**
	 * @return TalkPollVote
	 */
	public function asArray(): array {
		return [
			'roomId' => $this->getRoomId(),
			'start' => $this->getStart(),
			'end' => $this->getEnd(),
			'description' => $this->getDescription(),
		];
	}
}
