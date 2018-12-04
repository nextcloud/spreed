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

use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Files\Util;
use OCA\Spreed\Manager;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCP\Comments\IComment;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\IUserManager;

/**
 * Helper class for notifications related to user mentions in chat messages.
 *
 * This class uses the NotificationManager to create and remove the
 * notifications as needed; OCA\Spreed\Notification\Notifier is the one that
 * prepares the notifications for display.
 */
class Notifier {

	/** @var INotificationManager */
	private $notificationManager;

	/** @var IUserManager */
	private $userManager;

	/** @var Manager */
	private $manager;

	/** @var Util */
	private $util;

	public function __construct(INotificationManager $notificationManager,
								IUserManager $userManager,
								Manager $manager,
								Util $util) {
		$this->notificationManager = $notificationManager;
		$this->userManager = $userManager;
		$this->manager = $manager;
		$this->util = $util;
	}

	/**
	 * Notifies the user mentioned in the comment.
	 *
	 * The comment must be a chat message comment. That is, its "objectId" must
	 * be the room ID.
	 *
	 * Not every user mentioned in the message is notified, but only those that
	 * are able to participate in the room.
	 *
	 * @param Room $chat
	 * @param IComment $comment
	 * @return string[] Users that were mentioned
	 */
	public function notifyMentionedUsers(Room $chat, IComment $comment): array {
		$mentionedUserIds = $this->getMentionedUserIds($comment);
		if (empty($mentionedUserIds)) {
			return [];
		}

		$notification = $this->createNotification($chat, $comment, 'mention');
		foreach ($mentionedUserIds as $mentionedUserId) {
			if ($this->shouldUserBeNotified($mentionedUserId, $comment)) {
				$notification->setUser($mentionedUserId);
				$this->notificationManager->notify($notification);
			}
		}

		return $mentionedUserIds;
	}

	/**
	 * Notifies the user mentioned in the comment.
	 *
	 * The comment must be a chat message comment. That is, its "objectId" must
	 * be the room ID.
	 *
	 * Not every user mentioned in the message is notified, but only those that
	 * are able to participate in the room.
	 *
	 * @param Room $chat
	 * @param IComment $comment
	 * @param string[] $mentionedUsers
	 */
	public function notifyOtherParticipant(Room $chat, IComment $comment, array $mentionedUsers) {
		$participants = $chat->getParticipantsByNotificationLevel(Participant::NOTIFY_ALWAYS);

		$notification = $this->createNotification($chat, $comment, 'chat');
		foreach ($participants as $participant) {
			if ($participant->isGuest()) {
				continue;
			}

			if ($participant->getUser() === $comment->getActorId()) {
				// Do not notify the author
				continue;
			}

			if (\in_array($participant->getUser(), $mentionedUsers, true)) {
				continue;
			}

			if ($participant->getSessionId() !== '0') {
				// User is online
				continue;
			}

			$notification->setUser($participant->getUser());
			$this->notificationManager->notify($notification);
		}

		// Also notify default participants in one2one chats
		if ($chat->getType() === Room::ONE_TO_ONE_CALL) {
			$participants = $chat->getParticipantsByNotificationLevel(Participant::NOTIFY_DEFAULT);
			foreach ($participants as $participant) {
				if ($participant->isGuest()) {
					continue;
				}

				if ($participant->getUser() === $comment->getActorId()) {
					// Do not notify the author
					continue;
				}

				if (\in_array($participant->getUser(), $mentionedUsers, true)) {
					continue;
				}

				if ($participant->getSessionId() !== '0') {
					// User is online
					continue;
				}

				$notification->setUser($participant->getUser());
				$this->notificationManager->notify($notification);
			}
		}
	}

	/**
	 * Removes all the pending notifications for the room with the given ID.
	 *
	 * @param Room $chat
	 */
	public function removePendingNotificationsForRoom(Room $chat) {
		$notification = $this->notificationManager->createNotification();

		// @todo this should be in the Notifications\Hooks
		$notification->setApp('spreed');

		$notification->setObject('chat', $chat->getToken());
		$this->notificationManager->markProcessed($notification);

		$notification->setObject('room', $chat->getToken());
		$this->notificationManager->markProcessed($notification);

		$notification->setObject('call', $chat->getToken());
		$this->notificationManager->markProcessed($notification);
	}

	/**
	 * Removes all the pending mention notifications for the room
	 *
	 * @param Room $chat
	 * @param string $userId
	 */
	public function markMentionNotificationsRead(Room $chat, $userId) {

		if ($userId === null || $userId === '') {
			return;
		}

		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp('spreed')
			->setObject('chat', $chat->getToken())
			->setUser($userId);

		$this->notificationManager->markProcessed($notification);
	}

	/**
	 * Returns the IDs of the users mentioned in the given comment.
	 *
	 * @param IComment $comment
	 * @return string[] the mentioned user IDs
	 */
	private function getMentionedUserIds(IComment $comment) {
		$mentions = $comment->getMentions();

		if (empty($mentions)) {
			return [];
		}

		$userIds = [];
		foreach ($mentions as $mention) {
			if ($mention['type'] === 'user') {
				$userIds[] = $mention['id'];
			}
		}

		return $userIds;
	}

	/**
	 * Creates a notification for the given chat message comment and mentioned
	 * user ID.
	 *
	 * @param Room $chat
	 * @param IComment $comment
	 * @param string $subject
	 * @return INotification
	 */
	private function createNotification(Room $chat, IComment $comment, string $subject): INotification {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('spreed')
			->setObject('chat', $chat->getToken())
			->setSubject($subject, [
				'userType' => $comment->getActorType(),
				'userId' => $comment->getActorId(),
			])
			->setMessage($comment->getVerb(), [
				'commentId' => $comment->getId(),
			])
			->setDateTime($comment->getCreationDateTime());

		return $notification;
	}

	/**
	 * Determinates whether a user should be notified about the mention:
	 *
	 * 1. The user did not mention themself
	 * 2. The user must exist
	 * 3. The user must be a participant of the room
	 * 4. The user must not be active in the room
	 *
	 * @param string $userId
	 * @param IComment $comment
	 * @return bool
	 */
	private function shouldUserBeNotified($userId, IComment $comment) {
		if ($userId === $comment->getActorId()) {
			// Do not notify the user if they mentioned themself
			return false;
		}

		if (!$this->userManager->userExists($userId)) {
			return false;
		}

		try {
			$room = $this->manager->getRoomById($comment->getObjectId());
		} catch (RoomNotFoundException $e) {
			return false;
		}

		try {
			$participant = $room->getParticipant($userId);
			return $participant->getNotificationLevel() !== Participant::NOTIFY_NEVER;
		} catch (ParticipantNotFoundException $e) {
			if ($room->getObjectType() === 'file') {
				return $this->util->canUserAccessFile($room->getObjectId(), $userId);
			}
			return false;
		}
	}
}
