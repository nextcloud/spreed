<?php

declare(strict_types=1);
/**
 *
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @author Joas Schilling <coding@schilljs.com>
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

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\MessageParseEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Federation\ICloudIdManager;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;

/**
 * Helper class to get a rich message from a plain text message.
 * @template-implements IEventListener<Event>
 */
class UserMention implements IEventListener {

	public function __construct(
		protected ICommentsManager $commentsManager,
		protected IUserManager $userManager,
		protected IGroupManager $groupManager,
		protected GuestManager $guestManager,
		protected AvatarService $avatarService,
		protected ICloudIdManager $cloudIdManager,
		protected ParticipantService $participantService,
		protected IL10N $l,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof MessageParseEvent) {
			return;
		}

		$message = $event->getMessage();
		if ($message->getMessageType() !== ChatManager::VERB_MESSAGE) {
			return;
		}

		$this->parseMessage($message);
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
	protected function parseMessage(Message $chatMessage): void {
		$comment = $chatMessage->getComment();
		$message = $chatMessage->getMessage();
		$messageParameters = $chatMessage->getMessageParameters();

		$mentionTypeCount = [];

		// Set the current message as comment content, so that the message finds
		// mentions which are now part of the message, but were not on the original
		// comment, e.g. mentions at the beginning of captions
		$originalCommentMessage = $comment->getMessage();
		$comment->setMessage($message, ChatManager::MAX_CHAT_LENGTH + 10000);
		$mentions = $comment->getMentions();
		$comment->setMessage($originalCommentMessage, ChatManager::MAX_CHAT_LENGTH);

		// TODO This can be removed once getMentions() returns sorted results (Nextcloud 21+)
		usort($mentions, static function (array $m1, array $m2) {
			return mb_strlen($m2['id']) <=> mb_strlen($m1['id']);
		});

		foreach ($mentions as $mention) {
			if ($mention['type'] === 'user' && $mention['id'] === 'all') {
				$mention['type'] = 'call';
			}

			if ($mention['type'] === 'user') {
				$userDisplayName = $this->userManager->getDisplayName($mention['id']);
				if ($userDisplayName === null) {
					continue;
				}
			}

			if (!array_key_exists($mention['type'], $mentionTypeCount)) {
				$mentionTypeCount[$mention['type']] = 0;
			}
			$mentionTypeCount[$mention['type']]++;

			$search = $mention['id'];
			if (
				$mention['type'] === 'group' ||
				// $mention['type'] === 'federated_group' ||
				// $mention['type'] === 'team' ||
				// $mention['type'] === 'federated_team' ||
				$mention['type'] === 'federated_user') {
				$search = $mention['type'] . '/' . $mention['id'];
			}

			// To keep a limited character set in parameter IDs ([a-zA-Z0-9-])
			// the mention parameter ID does not include the mention ID (which
			// could contain characters like '@' for user IDs) but a one-based
			// index of the mentions of that type.
			$mentionParameterId = 'mention-' . str_replace('_', '-', $mention['type']) . $mentionTypeCount[$mention['type']];

			$message = str_replace('@"' . $search . '"', '{' . $mentionParameterId . '}', $message);
			if (!str_contains($search, ' ')
				&& !str_starts_with($search, 'guest/')
				&& !str_starts_with($search, 'group/')
				// && !str_starts_with($search, 'federated_group/')
				// && !str_starts_with($search, 'team/')
				// && !str_starts_with($search, 'federated_team/')
				&& !str_starts_with($search, 'federated_user/')) {
				$message = str_replace('@' . $search, '{' . $mentionParameterId . '}', $message);
			}

			if ($mention['type'] === 'call') {
				$userId = '';
				if ($chatMessage->getParticipant()?->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
					$userId = $chatMessage->getParticipant()->getAttendee()->getActorId();
				}

				$messageParameters[$mentionParameterId] = [
					'type' => $mention['type'],
					'id' => $chatMessage->getRoom()->getToken(),
					'name' => $chatMessage->getRoom()->getDisplayName($userId, true),
					'call-type' => $this->getRoomType($chatMessage->getRoom()),
					'icon-url' => $this->avatarService->getAvatarUrl($chatMessage->getRoom()),
				];
			} elseif ($mention['type'] === 'guest') {
				try {
					$participant = $this->participantService->getParticipantByActor($chatMessage->getRoom(), Attendee::ACTOR_GUESTS, substr($mention['id'], strlen('guest/')));
					$displayName = $participant->getAttendee()->getDisplayName() ?: $this->l->t('Guest');
				} catch (ParticipantNotFoundException $e) {
					$displayName = $this->l->t('Guest');
				}

				$messageParameters[$mentionParameterId] = [
					'type' => $mention['type'],
					'id' => $mention['id'],
					'name' => $displayName,
				];
			} elseif ($mention['type'] === 'federated_user') {
				try {
					$cloudId = $this->cloudIdManager->resolveCloudId($mention['id']);
				} catch (\Throwable) {
					continue;
				}

				try {
					$participant = $this->participantService->getParticipantByActor($chatMessage->getRoom(), Attendee::ACTOR_FEDERATED_USERS, $mention['id']);
					$displayName = $participant->getAttendee()->getDisplayName() ?: $cloudId->getDisplayId();
				} catch (ParticipantNotFoundException) {
					$displayName = $mention['id'];
				}

				$messageParameters[$mentionParameterId] = [
					'type' => 'user',
					'id' => $cloudId->getUser(),
					'name' => $displayName,
					'server' => $cloudId->getRemote()
				];
			} elseif ($mention['type'] === 'group') {
				$group = $this->groupManager->get($mention['id']);
				if ($group instanceof IGroup) {
					$displayName = $group->getDisplayName();
				} else {
					$displayName = $mention['id'];
				}

				$messageParameters[$mentionParameterId] = [
					'type' => 'user-group',
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

		if (str_starts_with($message, '//')) {
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
			case Room::TYPE_ONE_TO_ONE_FORMER:
			case Room::TYPE_NOTE_TO_SELF:
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
