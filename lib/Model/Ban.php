<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-import-type TalkBan from ResponseDefinitions
 *
 * @method void setId(int $id)
 * @method int getId()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setBannedType(string $bannedType)
 * @method string getBannedType()
 * @method void setBannedId(string $bannedId)
 * @method string getBannedId()
 * @method void setBannedTime(\DateTime $bannedTime)
 * @method \DateTime getBannedTime()
 * @method void setInternalNote(null|string $internalNote)
 * @method null|string getInternalNote()
 */
class Ban extends Entity implements \JsonSerializable {
	public const NOTE_MAX_LENGTH = 4000;

	protected string $actorType = '';
	protected string $actorId = '';
	protected int $roomId = 0;
	protected string $bannedType = '';
	protected string $bannedId = '';
	protected ?\DateTime $bannedTime = null;
	protected ?string $internalNote = null;

	public function __construct() {
		$this->addType('id', 'int');
		$this->addType('actorType', 'string');
		$this->addType('actorId', 'string');
		$this->addType('roomId', 'int');
		$this->addType('bannedType', 'string');
		$this->addType('bannedId', 'string');
		$this->addType('bannedTime', 'datetime');
		$this->addType('internalNote', 'string');
	}

	/**
	 * @return TalkBan
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'actorType' => $this->getActorType(),
			'actorId' => $this->getActorId(),
			'bannedType' => $this->getBannedType(),
			'bannedId' => $this->getBannedId(),
			'bannedTime' => $this->getBannedTime()->getTimestamp(),
			'internalNote' => $this->getInternalNote() ?? '',
		];
	}
}
