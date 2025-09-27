<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\Comments\IComment;
use OCP\IL10N;

/**
 * @psalm-import-type TalkChatMessage from ResponseDefinitions
 */
class Message {
	public const METADATA_LAST_EDITED_BY_TYPE = 'last_edited_by_type';
	public const METADATA_LAST_EDITED_BY_ID = 'last_edited_by_id';
	public const METADATA_LAST_EDITED_TIME = 'last_edited_time';
	public const METADATA_SILENT = 'silent';
	public const METADATA_CAN_MENTION_ALL = 'can_mention_all';
	public const METADATA_THREAD_ID = 'thread_id';

	/** @var bool */
	protected $visible = true;

	/** @var string */
	protected $type = '';

	/** @var string */
	protected $message = '';

	/** @var string */
	protected $rawMessage = '';

	/** @var array */
	protected $parameters = [];

	/** @var string */
	protected $actorType = '';

	/** @var string */
	protected $actorId = '';

	/** @var string */
	protected $actorDisplayName = '';

	/** @var string */
	protected $lastEditActorType = '';

	/** @var string */
	protected $lastEditActorId = '';

	/** @var string */
	protected $lastEditActorDisplayName = '';

	/** @var int */
	protected $lastEditTimestamp = 0;

	public function __construct(
		protected Room $room,
		protected ?Participant $participant,
		protected ?IComment $comment,
		protected IL10N $l,
		protected ?ProxyCacheMessage $proxy = null,
	) {
	}

	/*
	 * Meta information
	 */

	public function getRoom(): Room {
		return $this->room;
	}

	public function getComment(): ?IComment {
		return $this->comment;
	}

	public function getL10n(): IL10N {
		return $this->l;
	}

	public function getParticipant(): ?Participant {
		return $this->participant;
	}

	/*
	 * Parsed message information
	 */

	public function getMessageId(): int {
		return $this->comment ? (int)$this->comment->getId() : $this->proxy->getRemoteMessageId();
	}

	public function getExpirationDateTime(): ?\DateTimeInterface {
		return $this->comment ? $this->comment->getExpireDate() : $this->proxy->getExpirationDatetime();
	}

	public function setVisibility(bool $visible): void {
		$this->visible = $visible;
	}

	public function getVisibility(): bool {
		return $this->visible;
	}

	public function setMessage(string $message, array $parameters, string $rawMessage = ''): void {
		$this->message = $message;
		$this->parameters = $parameters;
		$this->rawMessage = $rawMessage;
	}

	public function getMessage(): string {
		return $this->message;
	}

	public function getMessageParameters(): array {
		return $this->parameters;
	}

	public function getMessageRaw(): string {
		return $this->rawMessage;
	}

	public function setMessageType(string $type): void {
		$this->type = $type;
	}

	public function getMessageType(): string {
		return $this->type;
	}

	public function setActor(string $type, string $id, string $displayName): void {
		$this->actorType = $type;
		$this->actorId = $id;
		$this->actorDisplayName = $displayName;
	}

	public function setLastEdit(string $type, string $id, string $displayName, int $timestamp): void {
		$this->lastEditActorType = $type;
		$this->lastEditActorId = $id;
		$this->lastEditActorDisplayName = $displayName;
		$this->lastEditTimestamp = $timestamp;
	}

	public function getActorType(): string {
		return $this->actorType;
	}

	public function getActorId(): string {
		return $this->actorId;
	}

	public function getActorDisplayName(): string {
		return $this->actorDisplayName;
	}

	/**
	 * Specifies whether a message can be replied to
	 */
	public function isReplyable(): bool {
		return $this->getMessageType() !== ChatManager::VERB_SYSTEM
			&& $this->getMessageType() !== ChatManager::VERB_COMMAND
			&& $this->getMessageType() !== ChatManager::VERB_MESSAGE_DELETED
			&& $this->getMessageType() !== ChatManager::VERB_REACTION
			&& $this->getMessageType() !== ChatManager::VERB_REACTION_DELETED
			&& \in_array($this->getActorType(), [
				Attendee::ACTOR_USERS,
				Attendee::ACTOR_FEDERATED_USERS,
				Attendee::ACTOR_GUESTS,
				Attendee::ACTOR_EMAILS,
				Attendee::ACTOR_BOTS,
			], true);
	}

	/**
	 * @param string $format
	 * @psalm-param 'json'|'xml' $format
	 * @return TalkChatMessage
	 */
	public function toArray(string $format, ?Thread $thread): array {
		$expireDate = $this->getComment()->getExpireDate();

		$reactions = $this->getComment()->getReactions();
		if ($format === 'json' && empty($reactions)) {
			// Cheating here to make sure the reactions array is always a
			// JSON object on the API, even when there is no reaction at all.
			$reactions = new \stdClass();
		}

		$id = (int)$this->getComment()->getId();
		$threadId = (int)$this->getComment()->getTopmostParentId() ?: $id;

		$data = [
			'id' => $id,
			'token' => $this->getRoom()->getToken(),
			'actorType' => $this->getActorType(),
			'actorId' => $this->getActorId(),
			'actorDisplayName' => $this->getActorDisplayName(),
			'timestamp' => $this->getComment()->getCreationDateTime()->getTimestamp(),
			'message' => $this->getMessage(),
			'messageParameters' => $this->getMessageParameters(),
			'systemMessage' => $this->getMessageType() === ChatManager::VERB_SYSTEM ? $this->getMessageRaw() : '',
			'messageType' => $this->getMessageType(),
			'isReplyable' => $this->isReplyable(),
			'referenceId' => (string)$this->getComment()->getReferenceId(),
			'reactions' => $reactions,
			'expirationTimestamp' => $expireDate ? $expireDate->getTimestamp() : 0,
			'markdown' => $this->getMessageType() === ChatManager::VERB_SYSTEM ? false : true,
			'threadId' => $threadId,
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

		return $data;
	}
}
