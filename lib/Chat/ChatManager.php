<?php
declare(strict_types=1);
/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Spreed\Chat;

use OCA\Spreed\Room;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\IUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Basic polling chat manager.
 *
 * sendMessage() saves a comment using the ICommentsManager, while
 * receiveMessages() tries to read comments from ICommentsManager (with a little
 * wait between reads) until comments are found or until the timeout expires.
 *
 * When a message is saved the mentioned users are notified as needed, and
 * pending notifications are removed if the messages are deleted.
 */
class ChatManager {

	/** @var CommentsManager|ICommentsManager */
	private $commentsManager;
	/** @var EventDispatcherInterface */
	private $dispatcher;
	/** @var Notifier */
	private $notifier;

	/**
	 * @param CommentsManager $commentsManager
	 * @param EventDispatcherInterface $dispatcher
	 * @param Notifier $notifier
	 */
	public function __construct(CommentsManager $commentsManager,
								EventDispatcherInterface $dispatcher,
								Notifier $notifier) {
		$this->commentsManager = $commentsManager;
		$this->dispatcher = $dispatcher;
		$this->notifier = $notifier;
	}

	/**
	 * Sends a new message to the given chat.
	 *
	 * @param Room $chat
	 * @param string $actorType
	 * @param string $actorId
	 * @param string $message
	 * @param \DateTime $creationDateTime
	 * @param bool $sendNotifications
	 * @return IComment
	 */
	public function addSystemMessage(Room $chat, $actorType, $actorId, $message, \DateTime $creationDateTime, bool $sendNotifications): IComment {
		$comment = $this->commentsManager->create($actorType, $actorId, 'chat', (string) $chat->getId());
		$comment->setMessage($message);
		$comment->setCreationDateTime($creationDateTime);
		$comment->setVerb('system');
		try {
			$this->commentsManager->save($comment);

			// Update last_message
			$chat->setLastMessage($comment);

			if ($sendNotifications) {
				$this->notifier->notifyOtherParticipant($chat, $comment, []);
			}

			$this->dispatcher->dispatch(self::class . '::sendSystemMessage', new GenericEvent($chat, [
				'comment' => $comment,
			]));
		} catch (NotFoundException $e) {
		}

		return $comment;
	}

	/**
	 * Sends a new message to the given chat.
	 *
	 * @param Room $chat
	 * @param string $actorType
	 * @param string $actorId
	 * @param string $message
	 * @param \DateTime $creationDateTime
	 * @return IComment
	 */
	public function sendMessage(Room $chat, $actorType, $actorId, $message, \DateTime $creationDateTime): IComment {
		$comment = $this->commentsManager->create($actorType, $actorId, 'chat', (string) $chat->getId());
		$comment->setMessage($message);
		$comment->setCreationDateTime($creationDateTime);
		// A verb ('comment', 'like'...) must be provided to be able to save a
		// comment
		$comment->setVerb('comment');

		try {
			$this->commentsManager->save($comment);

			// Update last_message
			$chat->setLastMessage($comment);

			$mentionedUsers = $this->notifier->notifyMentionedUsers($chat, $comment);
			if (!empty($mentionedUsers)) {
				$chat->markUsersAsMentioned($notifiedUsers, (int) $comment->getId());
			}

			// User was not mentioned, send a normal notification
			$this->notifier->notifyOtherParticipant($chat, $comment, $mentionedUsers);

			$this->dispatcher->dispatch(self::class . '::sendMessage', new GenericEvent($chat, [
				'comment' => $comment,
			]));
		} catch (NotFoundException $e) {
		}

		return $comment;
	}

	public function getLastReadMessageFromLegacy(Room $chat, IUser $user): int {
		$marker = $this->commentsManager->getReadMark('chat', $chat->getId(), $user);
		if ($marker === null) {
			return 0;
		}

		return $this->commentsManager->getLastCommentBeforeDate('chat', (string) $chat->getId(), $marker, 'comment');
	}

	public function getUnreadCount(Room $chat, int $lastReadMessage): int {
		return $this->commentsManager->getNumberOfCommentsForObjectSinceComment('chat', (string) $chat->getId(), $lastReadMessage, 'comment');
	}

	/**
	 * Receive the history of a chat
	 *
	 * @param Room $chat
	 * @param int $offset Last known message id
	 * @param int $limit
	 * @return IComment[] the messages found (only the id, actor type and id,
	 *         creation date and message are relevant), or an empty array if the
	 *         timeout expired.
	 */
	public function getHistory(Room $chat, $offset, $limit): array {
		return $this->commentsManager->getForObjectSince('chat', (string) $chat->getId(), $offset, 'desc', $limit);
	}

	/**
	 * If there are currently no messages the response will not be sent
	 * immediately. Instead, HTTP connection will be kept open waiting for new
	 * messages to arrive and, when they do, then the response will be sent. The
	 * connection will not be kept open indefinitely, though; the number of
	 * seconds to wait for new messages to arrive can be set using the timeout
	 * parameter; the default timeout is 30 seconds, maximum timeout is 60
	 * seconds. If the timeout ends a successful but empty response will be
	 * sent.
	 *
	 * @param Room $chat
	 * @param int $offset Last known message id
	 * @param int $limit
	 * @param int $timeout
	 * @param IUser|null $user
	 * @return IComment[] the messages found (only the id, actor type and id,
	 *         creation date and message are relevant), or an empty array if the
	 *         timeout expired.
	 */
	public function waitForNewMessages(Room $chat, $offset, $limit, $timeout, $user): array {
		if ($user instanceof IUser) {
			$this->notifier->markMentionNotificationsRead($chat, $user->getUID());
		}
		$elapsedTime = 0;

		$comments = $this->commentsManager->getForObjectSince('chat', (string) $chat->getId(), $offset, 'asc', $limit);
		while (empty($comments) && $elapsedTime < $timeout) {
			sleep(1);
			$elapsedTime++;

			$comments = $this->commentsManager->getForObjectSince('chat', (string) $chat->getId(), $offset, 'asc', $limit);
		}

		return $comments;
	}

	/**
	 * Deletes all the messages for the given chat.
	 *
	 * @param Room $chat
	 */
	public function deleteMessages(Room $chat) {
		$this->commentsManager->deleteCommentsAtObject('chat', (string) $chat->getId());

		$this->notifier->removePendingNotificationsForRoom($chat);
	}
}
