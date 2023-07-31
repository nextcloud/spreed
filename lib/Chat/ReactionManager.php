<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

namespace OCA\Talk\Chat;

use OCA\Talk\Exceptions\ReactionAlreadyExistsException;
use OCA\Talk\Exceptions\ReactionNotSupportedException;
use OCA\Talk\Exceptions\ReactionOutOfContextException;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\NotFoundException;
use OCP\IL10N;
use OCP\PreConditionNotMetException;

/**
 * @psalm-import-type SpreedReaction from ResponseDefinitions
 */
class ReactionManager {

	public function __construct(
		private ChatManager $chatManager,
		private CommentsManager $commentsManager,
		private IL10N $l,
		private MessageParser $messageParser,
		private Notifier $notifier,
		protected ITimeFactory $timeFactory,
	) {
	}

	/**
	 * Add reaction
	 *
	 * @param Room $chat
	 * @param string $actorType
	 * @param string $actorId
	 * @param integer $messageId
	 * @param string $reaction
	 * @return IComment
	 * @throws NotFoundException
	 * @throws ReactionAlreadyExistsException
	 * @throws ReactionNotSupportedException
	 * @throws ReactionOutOfContextException
	 */
	public function addReactionMessage(Room $chat, string $actorType, string $actorId, int $messageId, string $reaction): IComment {
		$parentMessage = $this->getCommentToReact($chat, (string) $messageId);
		try {
			// Check if the user already reacted with the same reaction
			$this->commentsManager->getReactionComment(
				(int) $parentMessage->getId(),
				$actorType,
				$actorId,
				$reaction
			);
			throw new ReactionAlreadyExistsException();
		} catch (NotFoundException $e) {
		}

		$comment = $this->commentsManager->create(
			$actorType,
			$actorId,
			'chat',
			(string) $chat->getId()
		);
		$comment->setParentId($parentMessage->getId());
		$comment->setMessage($reaction);
		$comment->setVerb(ChatManager::VERB_REACTION);
		$this->commentsManager->save($comment);

		$this->notifier->notifyReacted($chat, $parentMessage, $comment);
		return $comment;
	}

	/**
	 * Delete reaction
	 *
	 * @param Room $chat
	 * @param string $actorType
	 * @param string $actorId
	 * @param integer $messageId
	 * @param string $reaction
	 * @return IComment
	 * @throws NotFoundException
	 * @throws ReactionNotSupportedException
	 * @throws ReactionOutOfContextException
	 */
	public function deleteReactionMessage(Room $chat, string $actorType, string $actorId, int $messageId, string $reaction): IComment {
		// Just to verify that messageId is part of the room and throw error if not.
		$this->getCommentToReact($chat, (string) $messageId);

		$comment = $this->commentsManager->getReactionComment(
			$messageId,
			$actorType,
			$actorId,
			$reaction
		);
		$comment->setMessage(
			json_encode([
				'deleted_by_type' => $actorType,
				'deleted_by_id' => $actorId,
				'deleted_on' => $this->timeFactory->getDateTime()->getTimestamp(),
			])
		);
		$comment->setVerb(ChatManager::VERB_REACTION_DELETED);
		$this->commentsManager->save($comment);

		$this->chatManager->addSystemMessage(
			$chat,
			$actorType,
			$actorId,
			json_encode(['message' => 'reaction_revoked', 'parameters' => ['message' => (int) $comment->getId()]]),
			$this->timeFactory->getDateTime(),
			false,
			null,
			$messageId,
			true
		);

		return $comment;
	}

	/**
	 * @return array<string, SpreedReaction[]>
	 * @throws PreConditionNotMetException
	 */
	public function retrieveReactionMessages(Room $chat, Participant $participant, int $messageId, ?string $reaction = null): array {
		if ($reaction) {
			$comments = $this->commentsManager->retrieveAllReactionsWithSpecificReaction($messageId, $reaction);
		} else {
			$comments = $this->commentsManager->retrieveAllReactions($messageId);
		}

		$reactions = [];
		foreach ($comments as $comment) {
			$message = $this->messageParser->createMessage($chat, $participant, $comment, $this->l);
			$this->messageParser->parseMessage($message);

			$reactions[$comment->getMessage()][] = [
				'actorType' => $comment->getActorType(),
				'actorId' => $comment->getActorId(),
				'actorDisplayName' => $message->getActorDisplayName(),
				'timestamp' => $comment->getCreationDateTime()->getTimestamp(),
			];
		}
		return $reactions;
	}

	/**
	 * @param Participant $participant
	 * @param array $messageIds
	 * @return array[]
	 * @psalm-return array<int, string[]>
	 */
	public function getReactionsByActorForMessages(Participant $participant, array $messageIds): array {
		return $this->commentsManager->retrieveReactionsByActor(
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId(),
			$messageIds
		);
	}

	/**
	 * @param Room $chat
	 * @param string $messageId
	 * @return IComment
	 * @throws NotFoundException
	 * @throws ReactionNotSupportedException
	 * @throws ReactionOutOfContextException
	 */
	public function getCommentToReact(Room $chat, string $messageId): IComment {
		if (!$this->commentsManager->supportReactions()) {
			throw new ReactionNotSupportedException();
		}
		$comment = $this->commentsManager->get($messageId);

		if ($comment->getObjectType() !== 'chat'
			|| $comment->getObjectId() !== (string) $chat->getId()
			|| !in_array($comment->getVerb(), [
				ChatManager::VERB_MESSAGE,
				ChatManager::VERB_OBJECT_SHARED,
			], true)) {
			throw new ReactionOutOfContextException();
		}

		return $comment;
	}
}
