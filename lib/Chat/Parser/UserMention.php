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

namespace OCA\Spreed\Chat\Parser;

use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Helper class to get a rich message from a plain text message.
 */
class UserMention {

	/** @var ICommentsManager */
	private $commentsManager;

	/** @var IUserManager */
	private $userManager;

	public function __construct(ICommentsManager $commentsManager, IUserManager $userManager) {
		$this->commentsManager = $commentsManager;
		$this->userManager = $userManager;
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
	 * @return array first element, the rich message; second element, the
	 *         parameters of the rich message (or an empty array if there are no
	 *         parameters).
	 */
	public function parseMessage(IComment $comment): array {
		$message = $comment->getMessage();
		$messageParameters = [];

		$mentionTypeCount = [];

		$mentions = $comment->getMentions();
		foreach ($mentions as $mention) {
			if ($mention['type'] === 'user') {
				$user = $this->userManager->get($mention['id']);
				if (!$user instanceof IUser) {
					continue;
				}
			}

			if (!array_key_exists($mention['type'], $mentionTypeCount)) {
				$mentionTypeCount[$mention['type']] = 0;
			}
			$mentionTypeCount[$mention['type']]++;

			// To keep a limited character set in parameter IDs ([a-zA-Z0-9-])
			// the mention parameter ID does not include the mention ID (which
			// could contain characters like '@' for user IDs) but a one-based
			// index of the mentions of that type.
			$mentionParameterId = 'mention-' . $mention['type'] . $mentionTypeCount[$mention['type']];

			$placeholder = strpos($mention['id'], ' ') !== false ? ('@"' . $mention['id'] . '"') : ('@' .  $mention['id']);
			$message = str_replace($placeholder, '{' . $mentionParameterId . '}', $message);

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
