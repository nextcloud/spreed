<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setBannedByActorId(string $bannedByActorId)
 * @method string getBannedByActorId()
 * @method void setBannedByActorType(string $bannedByActorType)
 * @method string getBannedByActorType()
 * @method void setBannedAt(\DateTime $bannedAt)
 * @method \DateTime getBannedAt()
 * @method void setReason(string $reason)
 * @method string getReason()
 */
class Ban extends Entity implements \JsonSerializable {
	protected $actorId = '';
	protected string $actorType = '';
	protected int $roomId = 0;
	protected string $bannedByActorId = '';
	protected string $bannedByActorType = '';
	protected ?\DateTime $bannedAt = null;
	protected ?string $reason = null;

	public function __construct() {
		$this->addType('actorId', 'string');
		$this->addType('actorType', 'string');
		$this->addType('roomId', 'int');
		$this->addType('bannedByActorId', 'string');
		$this->addType('bannedByActorType', 'string');
		$this->addType('bannedAt', 'datetime');
		$this->addType('reason', 'string');
	}

	public function jsonSerialize(): array {
		return [
			'actorId' => $this->getActorId(),
			'actorType' => $this->getActorType(),
			'roomId' => $this->getRoomId(),
			'bannedByActorId' => $this->getBannedByActorId(),
			'bannedByActorType' => $this->getBannedByActorType(),
			'bannedAt' => $this->getBannedAt() ? $this->getBannedAt()->getTimestamp() : null,
			'reason' => $this->getReason(),
		];
	}
}
