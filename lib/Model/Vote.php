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
 * @method void setPollId(int $pollId)
 * @method int getPollId()
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setDisplayName(string $displayName)
 * @method string getDisplayName()
 * @method void setOptionId(int $optionId)
 * @method int getOptionId()
 *
 * @psalm-import-type TalkPollVote from ResponseDefinitions
 */
class Vote extends Entity {
	protected int $pollId = 0;
	protected int $roomId = 0;
	protected string $actorType = '';
	protected string $actorId = '';
	protected ?string $displayName = null;
	protected ?int $optionId = null;

	public function __construct() {
		$this->addType('pollId', Types::BIGINT);
		$this->addType('roomId', Types::BIGINT);
		$this->addType('actorType', Types::STRING);
		$this->addType('actorId', Types::STRING);
		$this->addType('displayName', Types::STRING);
		$this->addType('optionId', Types::INTEGER);
	}

	/**
	 * @return TalkPollVote
	 */
	public function asArray(): array {
		return [
			// The ids are not needed on the API level but only internally for optimising database queries
			// 'id' => $this->getId(),
			// 'pollId' => $this->getPollId(),
			// 'roomId' => $this->getRoomId(),
			'actorType' => $this->getActorType(),
			'actorId' => $this->getActorId(),
			'actorDisplayName' => $this->getDisplayName(),
			'optionId' => $this->getOptionId(),
		];
	}
}
