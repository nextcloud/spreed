<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setMessageId(int $messageId)
 * @method int getMessageId()
 * @method void setMessageTime(int $messageTime)
 * @method int getMessageTime()
 * @method void setObjectType(string $objectType)
 * @method string getObjectType()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 */
class Attachment extends Entity {
	public const TYPE_AUDIO = 'audio';
	public const TYPE_DECK_CARD = 'deckcard';
	public const TYPE_FILE = 'file';
	public const TYPE_LOCATION = 'location';
	public const TYPE_MEDIA = 'media';
	public const TYPE_OTHER = 'other';
	public const TYPE_POLL = 'poll';
	public const TYPE_RECORDING = 'recording';
	public const TYPE_VOICE = 'voice';

	public const ATTACHMENTS_NONE = 0;
	public const ATTACHMENTS_ATLEAST_ONE = 1;

	/** @var int */
	protected $roomId;

	/** @var int */
	protected $messageId;

	/** @var int */
	protected $messageTime;

	/** @var string */
	protected $objectType;

	/** @var string */
	protected $actorType;

	/** @var string */
	protected $actorId;

	public function __construct() {
		$this->addType('roomId', Types::BIGINT);
		$this->addType('messageId', Types::BIGINT);
		$this->addType('messageTime', Types::BIGINT);
		$this->addType('objectType', Types::STRING);
		$this->addType('actorType', Types::STRING);
		$this->addType('actorId', Types::STRING);
	}

	/**
	 * @return array
	 */
	public function asArray(): array {
		return [
			'id' => $this->getId(),
			'room_id' => $this->getRoomId(),
			'message_id' => $this->getMessageId(),
			'message_time' => $this->getMessageTime(),
			'object_type' => $this->getObjectType(),
			'actor_type' => $this->getActorType(),
			'actor_id' => $this->getActorId(),
		];
	}
}
