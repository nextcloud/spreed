<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;
use OCP\Comments\MessageTooLongException;
use OCP\DB\Types;

/**
 * @method string getId()
 * @method void setId(string $id)
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method int getThreadId()
 * @method void setThreadId(int $threadId)
 * @method int|null getParentId()
 * @method void setParentId(int|null $parentId)
 * @method string getMessage()
 * @method void setMessageType(string $messageType)
 * @method string getMessageType()
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method void setSendAt(\DateTime|null $sendAt)
 * @method \DateTime|null getSendAt()
 *
 * @psalm-import-type TalkScheduledMessage from ResponseDefinitions
 */
class ScheduledMessage extends Entity {
	public const METADATA_THREAD_TITLE = 'threadTitle';
	public const METADATA_THREAD_ID = 'threadId';
	public const METADATA_SILENT = 'silent';
	public const METADATA_LAST_EDITED_TIME = 'lastEditedTime';
	public const METADATA_SEND_AT = 'sendAt';

	protected ?int $roomId = 0;
	protected string $actorId = '';
	protected string $actorType = '';
	protected int $threadId = 0;
	protected ?int $parentId = null;
	protected string $message = '';
	protected string $messageType = '';
	protected ?string $metaData = null;
	protected ?\DateTime $createdAt = null;
	protected ?\DateTime $sendAt = null;

	public function __construct() {
		$this->addType('room_id', Types::BIGINT);
		$this->addType('actorId', Types::STRING);
		$this->addType('actorType', Types::STRING);
		$this->addType('threadId', Types::BIGINT);
		$this->addType('parentId', Types::BIGINT);
		$this->addType('message', Types::TEXT);
		$this->addType('messageType', Types::STRING);
		$this->addType('metaData', Types::TEXT);
		$this->addType('sendAt', Types::DATETIME);
		$this->addType('createdAt', Types::DATETIME);
	}

	/**
	 * @return array{silent: bool, threadId: int, threadTitle?: string, lastEditedTime?: int, sendAt: ?int}
	 */
	public function getDecodedMetaData(): array {
		return json_decode($this->metaData, true, 512, JSON_THROW_ON_ERROR);
	}

	public function getThreadTitle(): string {
		$metaData = $this->getDecodedMetaData();
		return $metaData[self::METADATA_THREAD_TITLE] ?? '';
	}

	/**
	 * @param array{silent: bool, threadId: int, threadTitle?: string, lastEditedTime?: int, sendAt: ?int} $metaData
	 */
	public function setMetaData(array $metaData): void {
		$this->metaData = json_encode($metaData, JSON_THROW_ON_ERROR);
		$this->markFieldUpdated('metaData');
	}

	/**
	 * @throws MessageTooLongException When the message is too long (~32k characters)
	 */
	public function setMessage(string $message): void {
		$message = trim($message);
		if (mb_strlen($message, 'UTF-8') > ChatManager::MAX_CHAT_LENGTH) {
			throw new MessageTooLongException('Comment message must not exceed ' . ChatManager::MAX_CHAT_LENGTH . ' characters');
		}
		$this->message = $message;
		$this->markFieldUpdated('message');
	}

	/**
	 * @param string $format
	 * @psalm-param 'json'|'xml' $format
	 * @return TalkScheduledMessage
	 */
	public function toArray(string $format, ?Message $parent, ?Thread $thread) : array {
		$metaData = $this->getDecodedMetaData();
		$data = [
			'id' => (string)$this->id,
			'actorId' => $this->getActorId(),
			'actorType' => $this->getActorType(),
			'threadId' => $this->getThreadId(),
			'message' => $this->getMessage(),
			'messageType' => $this->getMessageType(),
			'createdAt' => $this->getCreatedAt()->getTimestamp(),
			'sendAt' => $this->getSendAt()?->getTimestamp() ?? 0,
			'silent' => $metaData[self::METADATA_SILENT] ?? false,
		];

		if ($parent !== null) {
			$data['parent'] = $parent->toArray($format, $thread);
		}

		if ($thread !== null) {
			$data['threadTitle'] = $thread->getName();
		} elseif (isset($metaData[self::METADATA_THREAD_TITLE]) && $this->getThreadId() === Thread::THREAD_CREATE) {
			$data['threadTitle'] = (string)$metaData[self::METADATA_THREAD_TITLE];
		}
		return $data;
	}
}
