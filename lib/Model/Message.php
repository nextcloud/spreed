<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Kate Döen <kate.doeen@nextcloud.com>
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
		protected IComment $comment,
		protected IL10N $l,
	) {
	}

	/*
	 * Meta information
	 */

	public function getRoom(): Room {
		return $this->room;
	}

	public function getComment(): IComment {
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
		return $this->getMessageType() !== ChatManager::VERB_SYSTEM &&
			$this->getMessageType() !== ChatManager::VERB_COMMAND &&
			$this->getMessageType() !== ChatManager::VERB_MESSAGE_DELETED &&
			$this->getMessageType() !== ChatManager::VERB_REACTION &&
			$this->getMessageType() !== ChatManager::VERB_REACTION_DELETED &&
			\in_array($this->getActorType(), [Attendee::ACTOR_USERS, Attendee::ACTOR_GUESTS, Attendee::ACTOR_BOTS]);
	}

	/**
	 * @param string $format
	 * @psalm-param 'json'|'xml' $format
	 * @return TalkChatMessage
	 */
	public function toArray(string $format): array {
		$expireDate = $this->getComment()->getExpireDate();

		$reactions = $this->getComment()->getReactions();
		if ($format === 'json' && empty($reactions)) {
			// Cheating here to make sure the reactions array is always a
			// JSON object on the API, even when there is no reaction at all.
			$reactions = new \stdClass();
		}

		$data = [
			'id' => (int) $this->getComment()->getId(),
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
			'referenceId' => (string) $this->getComment()->getReferenceId(),
			'reactions' => $reactions,
			'expirationTimestamp' => $expireDate ? $expireDate->getTimestamp() : 0,
			'markdown' => $this->getMessageType() === ChatManager::VERB_SYSTEM ? false : true,
		];

		if ($this->lastEditActorType && $this->lastEditActorId && $this->lastEditTimestamp) {
			$data['lastEditActorType'] = $this->lastEditActorType;
			$data['lastEditActorId'] = $this->lastEditActorId;
			$data['lastEditActorDisplayName'] = $this->lastEditActorDisplayName;
			$data['lastEditTimestamp'] = $this->lastEditTimestamp;
		}

		if ($this->getMessageType() === ChatManager::VERB_MESSAGE_DELETED) {
			$data['deleted'] = true;
		}

		return $data;
	}
}
