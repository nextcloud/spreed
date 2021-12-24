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

use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IL10N;

class ReactionManager {
	/** @var ICommentsManager|CommentsManager */
	private $commentsManager;
	/** @var IL10N */
	private $l;
	/** @var MessageParser */
	private $messageParser;
	/** @var ITimeFactory */
	protected $timeFactory;

	public function __construct(CommentsManager $commentsManager,
								IL10N $l,
								MessageParser $messageParser,
								ITimeFactory $timeFactory) {
		$this->commentsManager = $commentsManager;
		$this->l = $l;
		$this->messageParser = $messageParser;
		$this->timeFactory = $timeFactory;
	}

	public function addReactionMessage(Room $chat, Participant $participant, int $messageId, string $reaction): IComment {
		$comment = $this->commentsManager->create(
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId(),
			'chat',
			(string) $chat->getId()
		);
		$comment->setParentId((string) $messageId);
		$comment->setMessage($reaction);
		$comment->setVerb('reaction');
		$this->commentsManager->save($comment);
		return $comment;
	}

	public function deleteReactionMessage(Participant $participant, int $messageId, string $reaction): IComment {
		$comment = $this->commentsManager->getReactionComment(
			$messageId,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId(),
			$reaction
		);
		$comment->setMessage(
			json_encode([
				'deleted_by_type' => $participant->getAttendee()->getActorType(),
				'deleted_by_id' => $participant->getAttendee()->getActorId(),
				'deleted_on' => $this->timeFactory->getDateTime()->getTimestamp(),
			])
		);
		$comment->setVerb('reaction_deleted');
		$this->commentsManager->save($comment);
		return $comment;
	}

	public function retrieveReactionMessages(Room $chat, Participant $participant, int $messageId, ?string $reaction): array {
		if ($reaction) {
			$comments = $this->commentsManager->retrieveAllReactionsWithSpecificReaction($messageId, $reaction);
		} else {
			$comments = $this->commentsManager->retrieveAllReactions($messageId);
		}

		$reactions = [];
		foreach ($comments as $comment) {
			$message = $this->messageParser->createMessage($chat, $participant, $comment, $this->l);
			$this->messageParser->parseMessage($message);

			$reactions[] = [
				'actorType' => $comment->getActorType(),
				'actorId' => $comment->getActorId(),
				'actorDisplayName' => $message->getActorDisplayName(),
				'timestamp' => $comment->getCreationDateTime()->getTimestamp(),
				'reaction' => $comment->getMessage(),
			];
		}
		return $reactions;
	}
}
