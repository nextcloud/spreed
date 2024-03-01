<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setMessageId(int $messageId)
 * @method int getMessageId()
 * @method void setMessageTime(int $messageTime)
 * @method int getMessageTime()
 * @method void setObjectType(string $objectType)
 * @method string getObjectType()
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

	/** @var int */
	protected $roomId;

	/** @var int */
	protected $messageId;

	/** @var int */
	protected $messageTime;

	/** @var string */
	protected $objectType;

	/**
	 * @var string
	 * @psalm-var Attendee::ACTOR_*
	 */
	protected $actorType;

	/** @var string */
	protected $actorId;

	public function __construct() {
		$this->addType('roomId', 'int');
		$this->addType('messageId', 'int');
		$this->addType('messageTime', 'int');
		$this->addType('objectType', 'string');
		$this->addType('actorType', 'string');
		$this->addType('actorId', 'string');
	}

	/**
	 * @psalm-param Attendee::ACTOR_* $actorType
	 */
	public function setActorType(string $actorType): void {
		$this->actorType = $actorType;
	}

	/**
	 * @psalm-return Attendee::ACTOR_*
	 */
	public function getActorType(): string {
		return $this->actorType;
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
