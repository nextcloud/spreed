<?php

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

use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;

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

	/** @var Notifier */
	private $notifier;

	/**
	 * @param ICommentsManager $commentsManager
	 * @param Notifier $notifier
	 */
	public function __construct(ICommentsManager $commentsManager,
								Notifier $notifier) {
		$this->commentsManager = $commentsManager;
		$this->notifier = $notifier;
	}

	/**
	 * Sends a new message to the given chat.
	 *
	 * @param string $chatId
	 * @param string $actorType
	 * @param string $actorId
	 * @param string $message
	 * @param \DateTime $creationDateTime
	 */
	public function sendMessage($chatId, $actorType, $actorId, $message, \DateTime $creationDateTime) {
		$comment = $this->commentsManager->create($actorType, $actorId, 'chat', $chatId);
		$comment->setMessage($message);
		$comment->setCreationDateTime($creationDateTime);
		// A verb ('comment', 'like'...) must be provided to be able to save a
		// comment
		$comment->setVerb('comment');

		$this->commentsManager->save($comment);

		$this->notifier->notifyMentionedUsers($comment);
	}

	/**
	 * Receive the history of a chat
	 *
	 * @param string $chatId
	 * @param int $offset Last known message id
	 * @param int $limit
	 * @return IComment[] the messages found (only the id, actor type and id,
	 *         creation date and message are relevant), or an empty array if the
	 *         timeout expired.
	 */
	public function getHistory($chatId, $offset, $limit) {
		return $this->commentsManager->getForObjectSinceTalkVersion('chat', $chatId, $offset, 'desc', $limit);
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
	 * @param string $chatId
	 * @param int $offset Last known message id
	 * @param int $timeout
	 * @param int $limit
	 * @param string $userId
	 * @return IComment[] the messages found (only the id, actor type and id,
	 *         creation date and message are relevant), or an empty array if the
	 *         timeout expired.
	 */
	public function waitForNewMessages($chatId, $offset, $timeout, $limit, $userId) {
		$this->notifier->markMentionNotificationsRead($chatId, $userId);
		$elapsedTime = 0;

		$comments = $this->commentsManager->getForObjectSinceTalkVersion('chat', $chatId, $offset, 'asc', $limit);

		while (empty($comments) && $elapsedTime < $timeout) {
			sleep(1);
			$elapsedTime++;

			$comments = $this->commentsManager->getForObjectSinceTalkVersion('chat', $chatId, $offset, 'asc', $limit);
		}

		return $comments;
	}

	/**
	 * Deletes all the messages for the given chat.
	 *
	 * @param string $chatId
	 */
	public function deleteMessages($chatId) {
		$this->commentsManager->deleteCommentsAtObject('chat', $chatId);

		$this->notifier->removePendingNotificationsForRoom($chatId);
	}

}
