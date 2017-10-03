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

use OCP\Comments\ICommentsManager;

/**
 * Basic polling chat manager.
 *
 * sendMessage() saves a comment using the ICommentsManager, while
 * receiveMessages() tries to read comments from ICommentsManager (with a little
 * wait between reads) until comments are found or until the timeout expires.
 */
class ChatManager {

	/** @var ICommentsManager */
	private $commentsManager;

	/**
	 * @param ICommentsManager $commentsManager
	 */
	public function __construct(ICommentsManager $commentsManager) {
		$this->commentsManager = $commentsManager;
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
	}

	/**
	 * Receives the messages from the given chat.
	 *
	 * It is possible to limit the returned messages to those not older than
	 * certain date and time setting the $notOlderThan parameter. In the same
	 * way it is possible to ignore the first N messages setting the $offset
	 * parameter. Both parameters are optional; if not set all the messages from
	 * the chat are returned.
	 *
	 * In any case, receiveMessages will wait (hang) until there is at least one
	 * message to be returned. It will not wait indefinitely, though; the
	 * maximum time to wait must be set using the $timeout parameter.
	 *
	 * @param string $chatId
	 * @param int $timeout the maximum number of seconds to wait for messages
	 * @param int $offset optional, starting point
	 * @param \DateTime|null $notOlderThan optional, the date and time of the
	 *        oldest message that may be returned
	 * @return IComment[] the messages found (only the id, actor type and id,
	 *         creation date and message are relevant), or an empty array if the
	 *         timeout expired.
	 */
	public function receiveMessages($chatId, $timeout, $offset = 0, \DateTime $notOlderThan = null) {
		$comments = [];

		$commentsFound = false;
		$elapsedTime = 0;
		while (!$commentsFound && $elapsedTime < $timeout) {
			$numberOfComments = $this->commentsManager->getNumberOfCommentsForObject('chat', $chatId, $notOlderThan);

			if ($numberOfComments > $offset) {
				$commentsFound = true;
			} else {
				sleep(1);
				$elapsedTime++;
			}
		}

		if ($commentsFound) {
			// The limit and offset of getForObject can not be based on the
			// number of comments, as more comments may have been added between
			// that call and this one (very unlikely, yet possible).
			$comments = $this->commentsManager->getForObject('chat', $chatId, $noLimit = 0, $noOffset = 0, $notOlderThan);

			// The comments are ordered from newest to oldest, so get all the
			// comments before the $offset elements from the end of the array.
			$length = null;
			if ($offset) {
				$length = -$offset;
			}
			$comments = array_slice($comments, $noOffset, $length);
		}

		return $comments;
	}

}
