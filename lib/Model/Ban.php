<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setId(int $id)
 * @method int getId()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setBannedId(string $bannedId)
 * @method string getBannedId()
 * @method void setBannedType(string $bannedType)
 * @method string getBannedType()
 * @method void setBannedTime(\DateTime $bannedTime)
 * @method \DateTime getBannedTime()
 * @method void setInternalNote(string $internalNote)
 * @method string getInternalNote()
 */
class Ban extends Entity implements \JsonSerializable {
	protected string $actorId = '';
	protected string $actorType = '';
	protected int $roomId = 0;
	protected string $bannedId = '';
	protected string $bannedType = '';
	protected ?\DateTime $bannedTime = null;
	protected ?string $internalNote = null;

	public function __construct() {
		$this->addType('id', 'int');
		$this->addType('actorId', 'string');
		$this->addType('actorType', 'string');
		$this->addType('roomId', 'int');
		$this->addType('bannedId', 'string');
		$this->addType('bannedType', 'string');
		$this->addType('bannedTime', 'datetime');
		$this->addType('internalNote', 'string');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'actorId' => $this->getActorId(),
			'actorType' => $this->getActorType(),
			'roomId' => $this->getRoomId(),
			'bannedId' => $this->getBannedId(),
			'bannedType' => $this->getBannedType(),
			'bannedTime' => $this->getBannedTime() ? $this->getBannedTime()->getTimestamp() : null,
			'internalNote' => $this->getInternalNote(),
		];
	}
}
