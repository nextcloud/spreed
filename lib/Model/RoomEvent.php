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
 * @method void setRoomToken(string $roomId)
 * @method string getRoomToken()
 * @method void setStart(int $start)
 * @method int getStart()
 * @method void setEnd(string $end)
 * @method int getEnd()
 * @method void setDescription(string $description)
 * @method string getDescription()
 *
 * @psalm-import-type TalkRoomEvent from ResponseDefinitions
 */
class RoomEvent extends Entity {
	protected string $roomToken = '';
	protected int $start = 0;
	protected int $end = 0;
	protected ?string $description = null;

	public function __construct() {
		$this->addType('roomToken', Types::STRING);
		$this->addType('start', Types::INTEGER);
		$this->addType('end', Types::INTEGER);
		$this->addType('description', Types::STRING);
	}

	/**
	 * @return TalkRoomEvent
	 */
	public function asArray(): array {
		return [
			'roomToken' => $this->getRoomToken(),
			'start' => $this->getStart(),
			'end' => $this->getEnd(),
			'description' => $this->getDescription(),
		];
	}
}
