<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCP\EventDispatcher\Event;

/**
 * @psalm-type ChatMessageParentData = array{
 *     type: 'Note',
 *     actor: array{
 *         type: 'Person',
 *         id: non-empty-string,
 *         name: non-empty-string,
 *     },
 *     object: array{
 *         type: 'Note',
 *         id: numeric-string,
 *         name: string,
 *         content: non-empty-string,
 *         mediaType: 'text/markdown'|'text/plain',
 *     },
 * }
 * @psalm-type ChatMessageData = array{
 *     type: 'Activity'|'Create',
 *     actor: array{
 *         type: 'Person',
 *         id: non-empty-string,
 *         name: non-empty-string,
 *         talkParticipantType: numeric-string,
 *     },
 *     object: array{
 *         type: 'Note',
 *         id: numeric-string,
 *         name: string,
 *         content: non-empty-string,
 *         mediaType: 'text/markdown'|'text/plain',
 *         inReplyTo?: ChatMessageParentData,
 *     },
 *     target: array{
 *         type: 'Collection',
 *         id: non-empty-string,
 *         name: non-empty-string,
 *     },
 * }
 * @psalm-type ReactionMessageData = array{
 *     type: 'Like',
 *     actor: array{
 *         type: 'Person',
 *         id: non-empty-string,
 *         name: non-empty-string,
 *         talkParticipantType?: numeric-string,
 *     },
 *     object: array{
 *         type: 'Note',
 *         id: numeric-string,
 *         name: string,
 *         content: non-empty-string,
 *         mediaType: 'text/markdown'|'text/plain',
 *         inReplyTo?: ChatMessageParentData,
 *     },
 *     target: array{
 *         type: 'Collection',
 *         id: non-empty-string,
 *         name: non-empty-string,
 *     },
 *     content: string,
 * }
 * @psalm-type UndoReactionMessageData = array{
 *     type: 'Undo',
 *     actor: array{
 *         type: 'Person',
 *         id: non-empty-string,
 *         name: non-empty-string,
 *         talkParticipantType?: numeric-string,
 *     },
 *     object: ReactionMessageData,
 *     target: array{
 *         type: 'Collection',
 *         id: non-empty-string,
 *         name: non-empty-string,
 *     },
 * }
 * @psalm-type BotManagementData = array{
 *     type: 'Join'|'Leave',
 *     actor: array{
 *         type: 'Application',
 *         id: non-empty-string,
 *         name: non-empty-string,
 *     },
 *     object: array{
 *         type: 'Collection',
 *         id: non-empty-string,
 *         name: non-empty-string,
 *     },
 * }
 * @psalm-type InvocationData = ChatMessageData|ReactionMessageData|UndoReactionMessageData|BotManagementData
 */
class BotInvokeEvent extends Event {
	/** @var list<string> */
	protected array $reactions = [];
	/** @var list<array{message: string, referenceId: string, reply: bool|int, silent: bool}> */
	protected array $answers = [];

	/**
	 * @param InvocationData $message
	 */
	public function __construct(
		protected string $botUrl,
		protected array $message,
	) {
		parent::__construct();
	}

	public function getBotUrl(): string {
		return $this->botUrl;
	}

	/**
	 * @return InvocationData
	 */
	public function getMessage(): array {
		return $this->message;
	}

	public function addReaction(string $emoji): void {
		$this->reactions[] = $emoji;
	}

	/**
	 * @return list<string>
	 */
	public function getReactions(): array {
		return $this->reactions;
	}

	public function addAnswer(string $message, bool|int $reply = false, bool $silent = false, string $referenceId = ''): void {
		$this->answers[] = [
			'message' => $message,
			'referenceId' => $referenceId,
			'reply' => $reply,
			'silent' => $silent,
		];
	}

	/**
	 * @return list<array{message: string, referenceId: string, reply: bool|int, silent: bool}>
	 */
	public function getAnswers(): array {
		return $this->answers;
	}
}
