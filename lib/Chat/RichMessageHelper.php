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
 * Helper class to get a rich message from a plain text message.
 */
class RichMessageHelper {

	/** @var ICommentsManager */
	private $commentsManager;

	/**
	 * @param ICommentsManager $commentsManager
	 */
	public function __construct(ICommentsManager $commentsManager) {
		$this->commentsManager = $commentsManager;
	}

	/**
	 * Returns the equivalent rich message to the given comment.
	 *
	 * The mentions in the comment are replaced by "{mention-$type$index}" in
	 * the returned rich message; each "mention-$type$index" parameter contains
	 * the following attributes:
	 *   -type: the type of the mention ("user")
	 *   -id: the ID of the user
	 *   -name: the display name of the user, or an empty string if it could
	 *     not be resolved.
	 *
	 * @param IComment $comment
	 * @return Array first element, the rich message; second element, the
	 *         parameters of the rich message (or an empty array if there are no
	 *         parameters).
	 */
	public function getRichMessage(IComment $comment) {
		$message = $comment->getMessage();
		$messageParameters = [];

		$mentionTypeCount = [];

		$mentions = $comment->getMentions();
		foreach ($mentions as $mention) {
			if (!array_key_exists($mention['type'], $mentionTypeCount)) {
				$mentionTypeCount[$mention['type']] = 0;
			}
			$mentionTypeCount[$mention['type']]++;

			// To keep a limited character set in parameter IDs ([a-zA-Z0-9-])
			// the mention parameter ID does not include the mention ID (which
			// could contain characters like '@' for user IDs) but a one-based
			// index of the mentions of that type.
			$mentionParameterId = 'mention-' . $mention['type'] . $mentionTypeCount[$mention['type']];

			$message = str_replace('@' . $mention['id'], '{' . $mentionParameterId . '}', $message);

			try {
				$displayName = $this->commentsManager->resolveDisplayName($mention['type'], $mention['id']);
			} catch (\OutOfBoundsException $e) {
				// There is no registered display name resolver for the mention
				// type, so the client decides what to display.
				$displayName = '';
			}

			$messageParameters[$mentionParameterId] = [
				'type' => $mention['type'],
				'id' => $mention['id'],
				'name' => $displayName
			];
		}

		return [$message, $messageParameters];
	}

}
