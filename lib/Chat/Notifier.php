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
use OCA\Spreed\Manager;
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

	/**
	 * @param INotificationManager $notificationManager
	 * @param IUserManager $userManager
	 * @param Manager $manager
	 */
	public function __construct(INotificationManager $notificationManager,
								IUserManager $userManager,
								Manager $manager) {
		$this->notificationManager = $notificationManager;
		$this->userManager = $userManager;
		$this->manager = $manager;
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
	 * @param IComment $comment
	 */
	public function notifyMentionedUsers(IComment $comment) {
		$mentionedUserIds = $this->getMentionedUserIds($comment);
		if (empty($mentionedUserIds)) {
			return;
		}

		foreach ($mentionedUserIds as $mentionedUserId) {
			if ($this->shouldUserBeNotified($mentionedUserId, $comment)) {
				$notification = $this->createNotification($comment, $mentionedUserId);

				$this->notificationManager->notify($notification);
			}
		}
	}

	/**
	 * Removes all the pending notifications for the room with the given ID.
	 *
	 * @param string $roomId
	 */
	public function removePendingNotificationsForRoom($roomId) {
		$notification = $this->notificationManager->createNotification();

		// @todo this should be in the Notifications\Hooks
		$notification->setApp('spreed');

		$notification->setObject('chat', $roomId);
		$this->notificationManager->markProcessed($notification);

		$notification->setObject('room', $roomId);
		$this->notificationManager->markProcessed($notification);

		$notification->setObject('call', $roomId);
		$this->notificationManager->markProcessed($notification);
	}

	/**
	 * Removes all the pending mention notifications for the room
	 *
	 * @param string $roomId
	 * @param string $userId
	 */
	public function markMentionNotificationsRead($roomId, $userId) {

		if ($userId === null || $userId === '') {
			return;
		}

		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp('spreed')
			->setObject('chat', $roomId)
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
	 * @param IComment $comment
	 * @param string $mentionedUserId
	 * @return INotification
	 */
	private function createNotification(IComment $comment, $mentionedUserId) {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('spreed')
			->setObject('chat', $comment->getObjectId())
			->setUser($mentionedUserId)
			->setSubject('mention', [
				'userType' => $comment->getActorType(),
				'userId' => $comment->getActorId(),
				])
			->setDateTime($comment->getCreationDateTime());

		$notificationMessage = $this->getNotificationMessage($comment, $mentionedUserId);
		if (count($notificationMessage) === 1) {
			$notification->setMessage($notificationMessage[0]);
		} else {
			$notification->setMessage($notificationMessage[0], $notificationMessage[1]);
		}

		return $notification;
	}

	/**
	 * Returns the message for a notification from the message of the comment.
	 *
	 * The message is returned as an array; the first element is the message
	 * itself, and the second element is another array that contains the
	 * parameters for the message. If no parameters are needed then the returned
	 * array has a single element.
	 *
	 * The message of a comment can be much longer than the maximum allowed
	 * length for the message of a notification so, if needed, the comment
	 * message is trimmed around the first mention to the user. In that case
	 * the "ellipsisStart" and/or "ellipsisEnd" (depending on the case) are
	 * returned as the parameters.
	 *
	 * @param IComment $comment
	 * @param string $mentionedUserId
	 * @return array the first element is a message suitable to be stored in a
	 *         notification, and the second are the parameters, if any.
	 */
	private function getNotificationMessage(IComment $comment, $mentionedUserId) {
		$maximumLength = 64;

		$message = $comment->getMessage();

		$messageLength = strlen($message);
		if ($messageLength <= $maximumLength) {
			return [$message];
		}

		$mention = '@' . $mentionedUserId;
		$mentionLength = strlen($mention);
		// Only the first mention is taken into account
		$mentionMiddleIndex = strpos($message, $mention) + $mentionLength / 2;

		if ($mentionMiddleIndex <= $maximumLength / 2) {
			return [substr($message, 0, $maximumLength), ['ellipsisEnd']];
		}

		if ($mentionMiddleIndex >= ($messageLength - $maximumLength / 2)) {
			return [substr($message, -$maximumLength), ['ellipsisStart']];
		}

		return [substr($message, $mentionMiddleIndex - ($maximumLength / 2), $maximumLength), ['ellipsisStart', 'ellipsisEnd']];
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
			$room->getParticipant($userId);
		} catch (RoomNotFoundException $e) {
			return false;
		} catch (ParticipantNotFoundException $e) {
			return false;
		}

		return true;
	}
}
