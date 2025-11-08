<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OC\Comments\Comment;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\ScheduledMessageManager;
use OCP\AppFramework\Db\Entity;
use OCP\Comments\IComment;
use OCP\Comments\MessageTooLongException;
use OCP\DB\Types;

/**
 * @method void setRoomToken(string $localToken)
 * @method string getRoomToken()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method int getThreadId()
 * @method void setThreadId(int $threadId)
 * @method int getParentId()
 * @method void setParentId(int $parentId)
 * @method string getMessage()
 * @method void setMessageType(string $messageType)
 * @method string getMessageType()
 * @method void setMessageParameters(?string $messageParameters)
 * @method string|null getMessageParameters()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getCreatedAt()
 * @method void setSendAt(?\DateTime $sendAt)
 * @method \DateTime|null getSendAt(),
 * @method void setMetaData(?string $metaData)
 * @method string|null getMetaData()
 */
class ScheduledMessage extends Entity implements \JsonSerializable, IComment {
	protected string $roomToken = '';
	protected string $actorType = '';
	protected string $actorId = '';
	protected ?int $threadId = null;
	protected ?int $parentId = null;
	protected string $message = '';
	protected string $messageType = '';
	protected ?string $messageParameters = null;
	protected ?string $metaData = null;
	protected ?\DateTime $createdAt = null;
	protected ?\DateTime $sendAt = null;

	public function __construct() {
		$this->addType('room_token', Types::STRING);
		$this->addType('actorType', Types::STRING);
		$this->addType('actorId', Types::STRING);
		$this->addType('threadId', Types::INTEGER);
		$this->addType('parentId', Types::INTEGER);
		$this->addType('message', Types::JSON);
		$this->addType('messageType', Types::STRING);
		$this->addType('messageParameters', Types::TEXT);
		$this->addType('metaData', Types::JSON);
		$this->addType('sendAt', Types::DATETIME);
	}

	public function getParsedMessageParameters(): array {
		return json_decode($this->getMessageParameters() ?? '[]', true, 512, JSON_THROW_ON_ERROR);
	}

	public function getParsedMetaData(): array {
		return json_decode($this->getMetaData() ?? '[]', true, 512, JSON_THROW_ON_ERROR);
	}

	/**
	 * sets the message of the comment and returns itself
	 *
	 * @param string $message
	 * @param int $maxLength
	 * @return ScheduledMessage
	 */
	public function setMessage($message, $maxLength = ChatManager::MAX_CHAT_LENGTH): ScheduledMessage {
		if (!is_string($message)) {
			throw new \InvalidArgumentException('String expected.');
		}
		$message = trim($message);
		if ($maxLength && mb_strlen($message, 'UTF-8') > $maxLength) {
			throw new MessageTooLongException('Comment message must not exceed ' . $maxLength . ' characters');
		}
		$this->message = $message;
		return $this;
	}

	#[\Override]
	public function jsonSerialize(): array {
		return [
			'room_token' => $this->getRoomToken(),
			'actorType' => $this->getActorType(),
			'actorId' => $this->getActorId(),
			'threadId' => $this->getThreadId(),
			'parentId' => $this->getParentId(),
			'message' => $this->getMessage(),
			'messageType' => $this->getMessageType(),
			'messageParameters' => $this->getParsedMessageParameters(),
			'createdAt' => $this->getCreatedAt()?->getTimestamp(),
			'sendAt' => $this->getSendAt()?->getTimestamp() ?? 0,
			'metaData' => $this->getParsedMetaData(),
		];
	}

	/**
	 * @param string $format
	 * @psalm-param 'json'|'xml' $format
	 * @return TalkChatMessage
	 */
	public function toArray(string $format, ?Thread $thread): array {
		$expireDate = null;

		$reactions = [];
		if ($format === 'json' && empty($reactions)) {
			// Cheating here to make sure the reactions array is always a
			// JSON object on the API, even when there is no reaction at all.
			$reactions = new \stdClass();
		}

		$data = [
			'id' => $this->getId(),
			'token' => $this->getRoomToken(),
			'actorType' => $this->getActorType(),
			'actorId' => $this->getActorId(),
			'actorDisplayName' => $this->getActorDisplayName(),
			'timestamp' => $this->getCreatedAt()->getTimestamp(),
			'message' => $this->getMessage(),
			'messageParameters' => $this->getMessageParameters(),
			'systemMessage' => $this->getMessageType() === ChatManager::VERB_SYSTEM ? $this->getMessageRaw() : '',
			'messageType' => $this->getMessageType(),
			'isReplyable' => $this->isReplyable(),
			'referenceId' => (string)$this->getComment()->getReferenceId(),
			'reactions' => $reactions,
			'expirationTimestamp' => $expireDate ? $expireDate->getTimestamp() : 0,
			'markdown' => true,
			'threadId' => $this->threadId,
		];
		if ($thread !== null) {
			$data['isThread'] = true;
			$data['threadTitle'] = $thread->getName();
			$data['threadReplies'] = $thread->getNumReplies();
		}

		if ($this->lastEditActorType && $this->lastEditActorId && $this->lastEditTimestamp) {
			$data['lastEditActorType'] = $this->lastEditActorType;
			$data['lastEditActorId'] = $this->lastEditActorId;
			$data['lastEditActorDisplayName'] = $this->lastEditActorDisplayName;
			$data['lastEditTimestamp'] = $this->lastEditTimestamp;
		}

		if ($this->getMessageType() === ChatManager::VERB_MESSAGE_DELETED) {
			$data['deleted'] = true;
		}

		$metaData = $this->getComment()->getMetaData() ?? [];
		if (!empty($metaData[self::METADATA_SILENT])) {
			$data[self::METADATA_SILENT] = true;
		}

		$data['metaData'] = [];
		foreach (self::EXPOSED_METADATA_KEYS as $exposedKey => $exposedAs) {
			if (isset($this->metaData[$exposedKey])) {
				$data['metaData'][$exposedAs] = $this->metaData[$exposedKey];
			}
		}

		if (empty($data['metaData'])) {
			unset($data['metaData']);
		}

		return $data;
	}

	public function getTopmostParentId()
	{
		// TODO: Implement getTopmostParentId() method.
	}

	public function setTopmostParentId($id)
	{
		// TODO: Implement setTopmostParentId() method.
	}

	public function getChildrenCount()
	{
		// TODO: Implement getChildrenCount() method.
	}

	public function setChildrenCount($count)
	{
		// TODO: Implement setChildrenCount() method.
	}

	public function getMentions()
	{
		// TODO: Implement getMentions() method.
	}

	public function getVerb()
	{
		// TODO: Implement getVerb() method.
	}

	public function setVerb($verb)
	{
		// TODO: Implement setVerb() method.
	}

	public function setActor($actorType, $actorId)
	{
		// TODO: Implement setActor() method.
	}

	public function getCreationDateTime()
	{
		// TODO: Implement getCreationDateTime() method.
	}

	public function setCreationDateTime(\DateTime $dateTime)
	{
		// TODO: Implement setCreationDateTime() method.
	}

	public function getLatestChildDateTime()
	{
		// TODO: Implement getLatestChildDateTime() method.
	}

	public function setLatestChildDateTime(?\DateTime $dateTime = null)
	{
		// TODO: Implement setLatestChildDateTime() method.
	}

	public function getObjectType()
	{
		// TODO: Implement getObjectType() method.
	}

	public function getObjectId()
	{
		// TODO: Implement getObjectId() method.
	}

	public function setObject($objectType, $objectId)
	{
		// TODO: Implement setObject() method.
	}

	public function getReferenceId(): ?string
	{
		// TODO: Implement getReferenceId() method.
	}

	public function setReferenceId(?string $referenceId): IComment
	{
		// TODO: Implement setReferenceId() method.
	}

	public function getReactions(): array
	{
		// TODO: Implement getReactions() method.
	}

	public function setReactions(?array $reactions): IComment
	{
		// TODO: Implement setReactions() method.
	}

	public function setExpireDate(?\DateTime $dateTime): IComment
	{
		// TODO: Implement setExpireDate() method.
	}

	public function getExpireDate(): ?\DateTime
	{
		// TODO: Implement getExpireDate() method.
	}
}
