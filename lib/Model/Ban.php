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
 * @method void setModeratorActorType(string $moderatorActorType)
 * @method string getModeratorActorType()
 * @method void setModeratorActorId(string $moderatorActorId)
 * @method string getModeratorActorId()
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setBannedActorType(string $bannedActorType)
 * @method string getBannedActorType()
 * @method void setBannedActorId(string $bannedActorId)
 * @method string getBannedActorId()
 * @method void setBannedTime(\DateTime $bannedTime)
 * @method \DateTime getBannedTime()
 * @method void setInternalNote(null|string $internalNote)
 * @method null|string getInternalNote()
 */
class Ban extends Entity implements \JsonSerializable {
	public const NOTE_MAX_LENGTH = 4000;

	protected string $moderatorActorType = '';
	protected string $moderatorActorId = '';
	protected int $roomId = 0;
	protected string $bannedActorType = '';
	protected string $bannedActorId = '';
	protected ?\DateTime $bannedTime = null;
	protected ?string $internalNote = null;

	public function __construct() {
		$this->addType('id', 'int');
		$this->addType('moderatorActorType', 'string');
		$this->addType('moderatorActorId', 'string');
		$this->addType('roomId', 'int');
		$this->addType('bannedActorType', 'string');
		$this->addType('bannedActorId', 'string');
		$this->addType('bannedTime', 'datetime');
		$this->addType('internalNote', 'string');
	}

	/**
	 * @return TalkBan
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'moderatorActorType' => $this->getModeratorActorType(),
			'moderatorActorId' => $this->getModeratorActorId(),
			'bannedActorType' => $this->getBannedActorType(),
			'bannedActorId' => $this->getBannedActorId(),
			'bannedTime' => $this->getBannedTime()->getTimestamp(),
			'internalNote' => $this->getInternalNote() ?? '',
		];
	}
}
