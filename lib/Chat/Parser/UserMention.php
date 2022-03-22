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

namespace OCA\Talk\Chat\Parser;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Room;
use OCP\Comments\ICommentsManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Helper class to get a rich message from a plain text message.
 */
class UserMention {

	/**
	 * Do NOT inject OCA\Talk\Chat\CommentsManager here
	 * otherwise the display name resolvers are lost
	 * and mentions are not replaced anymore.
	 *
	 * @var ICommentsManager
	 */
	private $commentsManager;
	/** @var IUserManager */
	private $userManager;
	/** @var GuestManager */
	private $guestManager;
	/** @var IL10N */
	private $l;

	public function __construct(ICommentsManager $commentsManager,
								IUserManager $userManager,
								GuestManager $guestManager,
								IL10N $l) {
		$this->commentsManager = $commentsManager;
		$this->userManager = $userManager;
		$this->guestManager = $guestManager;
		$this->l = $l;
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
	 * @param Message $chatMessage
	 */
	public function parseMessage(Message $chatMessage): void {
		$comment = $chatMessage->getComment();
		$message = $chatMessage->getMessage();
		$messageParameters = $chatMessage->getMessageParameters();

		$mentionTypeCount = [];

		$mentions = $comment->getMentions();
		// TODO This can be removed once getMentions() returns sorted results (Nextcloud 21+)
		usort($mentions, static function (array $m1, array $m2) {
			return mb_strlen($m2['id']) <=> mb_strlen($m1['id']);
		});

		foreach ($mentions as $mention) {
			if ($mention['type'] === 'user' && $mention['id'] === 'all') {
				$mention['type'] = 'call';
			}

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

			$message = str_replace('@"' . $mention['id'] . '"', '{' . $mentionParameterId . '}', $message);
			if (strpos($mention['id'], ' ') === false && strpos($mention['id'], 'guest/') !== 0) {
				$message = str_replace('@' . $mention['id'], '{' . $mentionParameterId . '}', $message);
			}

			if ($mention['type'] === 'call') {
				$userId = '';
				if ($chatMessage->getParticipant()->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
					$userId = $chatMessage->getParticipant()->getAttendee()->getActorId();
				}

				$messageParameters[$mentionParameterId] = [
					'type' => $mention['type'],
					'id' => $chatMessage->getRoom()->getToken(),
					'name' => $chatMessage->getRoom()->getDisplayName($userId),
					'call-type' => $this->getRoomType($chatMessage->getRoom()),
				];
			} elseif ($mention['type'] === 'guest') {
				try {
					$participant = $chatMessage->getRoom()->getParticipantByActor(Attendee::ACTOR_GUESTS, substr($mention['id'], strlen('guest/')));
					$displayName = $participant->getAttendee()->getDisplayName() ?: $this->l->t('Guest');
				} catch (ParticipantNotFoundException $e) {
					$displayName = $this->l->t('Guest');
				}

				$messageParameters[$mentionParameterId] = [
					'type' => $mention['type'],
					'id' => $mention['id'],
					'name' => $displayName,
				];
			} else {
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
					'name' => $displayName,
				];
			}
		}

		if (strpos($message, '//') === 0) {
			$message = substr($message, 1);
		}

		$chatMessage->setMessage($message, $messageParameters);
	}

	/**
	 * @param Room $room
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	protected function getRoomType(Room $room): string {
		switch ($room->getType()) {
			case Room::TYPE_ONE_TO_ONE:
				return 'one2one';
			case Room::TYPE_GROUP:
				return 'group';
			case Room::TYPE_PUBLIC:
				return 'public';
			default:
				throw new \InvalidArgumentException('Unknown room type');
		}
	}
}
