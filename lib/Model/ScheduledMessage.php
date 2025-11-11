<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Chat\ChatManager;
use OCP\AppFramework\Db\Entity;
use OCP\Comments\MessageTooLongException;
use OCP\DB\Types;

/**
 * @method void setRoomToken(string $localToken)
 * @method string getRoomToken()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method int getThreadId()
 * @method void setThreadId(int $threadId)
 * @method int getParentId()
 * @method void setParentId(int $parentId)
 * @method string getMessage()
 * @method void setMessageType(string $messageType)
 * @method string getMessageType()
 * @method \DateTime getCreatedAt()
 * @method void setSendAt(?\DateTime $sendAt)
 * @method \DateTime|null getSendAt(),
 */
class ScheduledMessage extends Entity implements \JsonSerializable {
	protected string $roomToken = '';
	protected string $actorId = '';
	protected string $actorType = '';
	protected ?int $threadId = null;
	protected ?int $parentId = null;
	protected string $message = '';
	protected string $messageType = '';
	protected ?string $metaData = null;
	protected ?\DateTime $createdAt = null;
	protected ?\DateTime $sendAt = null;

	public function __construct() {
		$this->addType('room_token', Types::STRING);
		$this->addType('actorId', Types::STRING);
		$this->addType('actorType', Types::STRING);
		$this->addType('threadId', Types::INTEGER);
		$this->addType('parentId', Types::INTEGER);
		$this->addType('message', Types::JSON);
		$this->addType('messageType', Types::STRING);
		$this->addType('metaData', Types::JSON);
		$this->addType('sendAt', Types::DATETIME);

		$this->createdAt = new \DateTime();
	}

	public function getMetaData(): array {
		return json_decode($this->metaData ?? '[]', true, 512, JSON_THROW_ON_ERROR);
	}

	public function setMetaData(?array $metaData): void {
		$this->metaData = json_encode($metaData, JSON_THROW_ON_ERROR);
	}

	public function setMessage(string $message, int $maxLength = ChatManager::MAX_CHAT_LENGTH): void {
		$message = trim($message);
		if ($maxLength && mb_strlen($message, 'UTF-8') > $maxLength) {
			throw new MessageTooLongException('Comment message must not exceed ' . $maxLength . ' characters');
		}
		$this->message = $message;
	}

	#[\Override]
	public function jsonSerialize(): array {
		return [
			'room_token' => $this->getRoomToken(),
			'actorId' => $this->getActorId(),
			'actorType' => $this->getActorType(),
			'threadId' => $this->getThreadId(),
			'parentId' => $this->getParentId(),
			'message' => $this->getMessage(),
			'messageType' => $this->getMessageType(),
			'createdAt' => $this->getCreatedAt()->getTimestamp(),
			'sendAt' => $this->getSendAt()?->getTimestamp(),
			'metaData' => $this->getMetaData(),
		];
	}
}
