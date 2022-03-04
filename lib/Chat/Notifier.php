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

namespace OCA\Talk\Chat;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Files\Util;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;

/**
 * Helper class for notifications related to user mentions in chat messages.
 *
 * This class uses the NotificationManager to create and remove the
 * notifications as needed; OCA\Talk\Notification\Notifier is the one that
 * prepares the notifications for display.
 */
class Notifier {

	/** @var INotificationManager */
	private $notificationManager;
	/** @var IUserManager */
	private $userManager;
	/** @var ParticipantService */
	private $participantService;
	/** @var Manager */
	private $manager;
	/** @var IConfig */
	private $config;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var Util */
	private $util;

	public function __construct(INotificationManager $notificationManager,
								IUserManager $userManager,
								ParticipantService $participantService,
								Manager $manager,
								IConfig $config,
								ITimeFactory $timeFactory,
								Util $util) {
		$this->notificationManager = $notificationManager;
		$this->userManager = $userManager;
		$this->participantService = $participantService;
		$this->manager = $manager;
		$this->config = $config;
		$this->timeFactory = $timeFactory;
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
	 * @param array[] $alreadyNotifiedUsers
	 * @psalm-param array<int, array{id: string, type: string}> $alreadyNotifiedUsers
	 * @return string[] Users that were mentioned
	 * @psalm-return array<int, array{id: string, type: string, ?attendee: Attendee}>
	 */
	public function notifyMentionedUsers(Room $chat, IComment $comment, array $alreadyNotifiedUsers): array {
		$usersToNotify = $this->getUsersToNotify($chat, $comment, $alreadyNotifiedUsers);

		if (!$usersToNotify) {
			return $alreadyNotifiedUsers;
		}
		$notification = $this->createNotification($chat, $comment, 'mention');
		$shouldFlush = $this->notificationManager->defer();

		foreach ($usersToNotify as $mentionedUser) {
			if ($this->shouldMentionedUserBeNotified($mentionedUser['id'], $comment, $chat, $mentionedUser['attendee'] ?? null)) {
				$notification->setUser($mentionedUser['id']);
				$this->notificationManager->notify($notification);
				$alreadyNotifiedUsers[] = $mentionedUser;
			}
		}

		if ($shouldFlush) {
			$this->notificationManager->flush();
		}

		return $alreadyNotifiedUsers;
	}

	/**
	 * @param Room $chat
	 * @param IComment $comment
	 * @param array $alreadyNotifiedUsers
	 * @psalm-param array<int, array{id: string, type: string}> $alreadyNotifiedUsers
	 * @return array
	 * @psalm-return array<int, array{id: string, type: string, ?attendee: Attendee}>
	 */
	private function getUsersToNotify(Room $chat, IComment $comment, array $alreadyNotifiedUsers): array {
		$usersToNotify = $this->getMentionedUsers($comment);
		$usersToNotify = $this->removeAlreadyNotifiedUsers($usersToNotify, $alreadyNotifiedUsers);
		$usersToNotify = $this->addMentionAllToList($chat, $usersToNotify);

		return $usersToNotify;
	}

	/**
	 * @param array $usersToNotify
	 * @psalm-param array<int, array{id: string, type: string}> $usersToNotify
	 * @param array $alreadyNotifiedUsers
	 * @psalm-param array<int, array{id: string, type: string}> $alreadyNotifiedUsers
	 * @return array
	 * @psalm-return array<int, array{id: string, type: string}>
	 */
	private function removeAlreadyNotifiedUsers(array $usersToNotify, array $alreadyNotifiedUsers): array {
		return array_filter($usersToNotify, static function (array $userToNotify) use ($alreadyNotifiedUsers): bool {
			foreach ($alreadyNotifiedUsers as $alreadyNotified) {
				if ($alreadyNotified === $userToNotify) {
					return false;
				}
			}
			return true;
		});
	}

	/**
	 * @param Room $chat
	 * @param array $list
	 * @psalm-param array<int, array{id: string, type: string}> $list
	 * @return array
	 * @psalm-return array<int, array{id: string, type: string, ?attendee: Attendee}>
	 */
	private function addMentionAllToList(Room $chat, array $list): array {
		$usersToNotify = array_filter($list, static function (array $user): bool {
			return $user['id'] !== 'all';
		});

		if (count($list) === count($usersToNotify)) {
			return $usersToNotify;
		}

		$attendees = $this->participantService->getActorsByType($chat, Attendee::ACTOR_USERS);
		foreach ($attendees as $attendee) {
			$alreadyAddedToNotify = array_filter($list, static function ($user) use ($attendee): bool {
				return $user['id'] === $attendee->getActorId();
			});
			if (!empty($alreadyAddedToNotify)) {
				continue;
			}

			$usersToNotify[] = [
				'id' => $attendee->getActorId(),
				'type' => $attendee->getActorType(),
				'attendee' => $attendee,
			];
		}

		return $usersToNotify;
	}


	/**
	 * Notifies the author that wrote the comment which was replied to
	 *
	 * The comment must be a chat message comment. That is, its "objectId" must
	 * be the room ID.
	 *
	 * The author of the message is notified only if he is still able to participate in the room
	 *
	 * @param Room $chat
	 * @param IComment $comment
	 * @param IComment $replyTo
	 * @param string $subject
	 * @return array[] Actor that was replied to
	 * @psalm-return array<int, array{id: string, type: string}>
	 */
	public function notifyReplyToAuthor(Room $chat, IComment $comment, IComment $replyTo, string $subject = 'reply'): array {
		if ($replyTo->getActorType() !== Attendee::ACTOR_USERS) {
			// No reply notification when the replyTo-author was not a user
			return [];
		}

		if (!$this->shouldMentionedUserBeNotified($replyTo->getActorId(), $comment, $chat)) {
			return [];
		}

		$notification = $this->createNotification($chat, $comment, $subject);
		$notification->setUser($replyTo->getActorId());
		$this->notificationManager->notify($notification);

		return [
			[
				'id' => $replyTo->getActorId(),
				'type' => $replyTo->getActorType(),
			],
		];
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
	 * @param array[] $alreadyNotifiedUsers
	 * @psalm-param array<int, array{id: string, type: string, ?attendee: Attendee}> $alreadyNotifiedUsers
	 */
	public function notifyOtherParticipant(Room $chat, IComment $comment, array $alreadyNotifiedUsers): void {
		$participants = $this->participantService->getParticipantsByNotificationLevel($chat, Participant::NOTIFY_ALWAYS);

		$notification = $this->createNotification($chat, $comment, 'chat');
		foreach ($participants as $participant) {
			if (!$this->shouldParticipantBeNotified($participant, $comment, $alreadyNotifiedUsers)) {
				continue;
			}

			$notification->setUser($participant->getAttendee()->getActorId());
			$this->notificationManager->notify($notification);
		}

		// Also notify default participants in one2one chats or when the admin default is "always"
		if ($this->getDefaultGroupNotification() === Participant::NOTIFY_ALWAYS || $chat->getType() === Room::TYPE_ONE_TO_ONE) {
			$participants = $this->participantService->getParticipantsByNotificationLevel($chat, Participant::NOTIFY_DEFAULT);
			foreach ($participants as $participant) {
				if (!$this->shouldParticipantBeNotified($participant, $comment, $alreadyNotifiedUsers)) {
					continue;
				}

				$notification->setUser($participant->getAttendee()->getActorId());
				$this->notificationManager->notify($notification);
			}
		}
	}

	public function notifyReacted(Room $chat, IComment $comment, IComment $reaction): void {
		if ($comment->getActorType() !== Attendee::ACTOR_USERS) {
			return;
		}

		if ($comment->getActorType() === $reaction->getActorType() && $comment->getActorId() === $reaction->getActorId()) {
			return;
		}

		$participant = $chat->getParticipant($comment->getActorId(), false);
		$notificationLevel = $participant->getAttendee()->getNotificationLevel();
		if ($notificationLevel === Participant::NOTIFY_DEFAULT) {
			if ($chat->getType() === Room::TYPE_ONE_TO_ONE) {
				$notificationLevel = Participant::NOTIFY_ALWAYS;
			} else {
				$notificationLevel = $this->getDefaultGroupNotification();
			}
		}

		if ($notificationLevel === Participant::NOTIFY_ALWAYS) {
			$notification = $this->createNotification($chat, $comment, 'reaction', [
				'reaction' => $reaction->getMessage(),
			]);
			$notification->setUser($comment->getActorId());
			$this->notificationManager->notify($notification);
		}
	}

	/**
	 * Removes all the pending notifications for the room with the given ID.
	 *
	 * @param Room $chat
	 */
	public function removePendingNotificationsForRoom(Room $chat, bool $chatOnly = false): void {
		$notification = $this->notificationManager->createNotification();
		$shouldFlush = $this->notificationManager->defer();

		// @todo this should be in the Notifications\Hooks
		$notification->setApp('spreed');

		$notification->setObject('chat', $chat->getToken());
		$this->notificationManager->markProcessed($notification);

		if (!$chatOnly) {
			$notification->setObject('room', $chat->getToken());
			$this->notificationManager->markProcessed($notification);

			$notification->setObject('call', $chat->getToken());
			$this->notificationManager->markProcessed($notification);
		}

		if ($shouldFlush) {
			$this->notificationManager->flush();
		}
	}

	/**
	 * Removes all the pending mention notifications for the room
	 *
	 * @param Room $chat
	 * @param string $userId
	 */
	public function markMentionNotificationsRead(Room $chat, ?string $userId): void {
		if ($userId === null || $userId === '') {
			return;
		}

		$shouldFlush = $this->notificationManager->defer();
		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp('spreed')
			->setObject('chat', $chat->getToken())
			->setUser($userId);

		$this->notificationManager->markProcessed($notification);
		if ($shouldFlush) {
			$this->notificationManager->flush();
		}
	}

	/**
	 * Returns the IDs of the users mentioned in the given comment.
	 *
	 * @param IComment $comment
	 * @return string[] the mentioned user IDs
	 */
	public function getMentionedUserIds(IComment $comment): array {
		$mentionedUsers = $this->getMentionedUsers($comment);
		return array_map(static function ($mentionedUser) {
			return $mentionedUser['id'];
		}, $mentionedUsers);
	}

	/**
	 * @param IComment $comment
	 * @return array[]
	 * @psalm-return array<int, array{type: string, id: string}>
	 */
	private function getMentionedUsers(IComment $comment): array {
		$mentions = $comment->getMentions();

		if (empty($mentions)) {
			return [];
		}

		$mentionedUsers = [];
		foreach ($mentions as $mention) {
			if ($mention['type'] === 'user') {
				$mentionedUsers[] = [
					'id' => $mention['id'],
					'type' => 'users'
				];
			}
		}
		return $mentionedUsers;
	}

	/**
	 * Creates a notification for the given chat message comment and mentioned
	 * user ID.
	 *
	 * @param Room $chat
	 * @param IComment $comment
	 * @param string $subject
	 * @param array $subjectData
	 * @return INotification
	 */
	private function createNotification(Room $chat, IComment $comment, string $subject, array $subjectData = []): INotification {
		$subjectData['userType'] = $comment->getActorType();
		$subjectData['userId'] = $comment->getActorId();

		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('spreed')
			->setObject('chat', $chat->getToken())
			->setSubject($subject, $subjectData)
			->setMessage($comment->getVerb(), [
				'commentId' => $comment->getId(),
			])
			->setDateTime($comment->getCreationDateTime());

		return $notification;
	}

	protected function getDefaultGroupNotification(): int {
		return (int) $this->config->getAppValue('spreed', 'default_group_notification', Participant::NOTIFY_MENTION);
	}

	/**
	 * Determines whether a user should be notified about the mention:
	 *
	 * 1. The user did not mention themself
	 * 2. The user must exist
	 * 3. The user must be a participant of the room
	 * 4. The user must not be active in the room
	 *
	 * @param string $userId
	 * @param IComment $comment
	 * @param Room $room
	 * @param Attendee|null $attendee
	 * @return bool
	 */
	protected function shouldMentionedUserBeNotified(string $userId, IComment $comment, Room $room, ?Attendee $attendee = null): bool {
		if ($comment->getActorType() === Attendee::ACTOR_USERS && $userId === $comment->getActorId()) {
			// Do not notify the user if they mentioned themselves
			return false;
		}

		try {
			if (!$attendee instanceof Attendee) {
				if (!$this->userManager->userExists($userId)) {
					return false;
				}

				$participant = $room->getParticipant($userId, false);
				$attendee = $participant->getAttendee();
			}

			$notificationLevel = $attendee->getNotificationLevel();
			if ($notificationLevel === Participant::NOTIFY_DEFAULT) {
				if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
					$notificationLevel = Participant::NOTIFY_ALWAYS;
				} else {
					$notificationLevel = $this->getDefaultGroupNotification();
				}
			}
			return $notificationLevel !== Participant::NOTIFY_NEVER;
		} catch (ParticipantNotFoundException $e) {
			if ($room->getObjectType() === 'file' && $this->util->canUserAccessFile($room->getObjectId(), $userId)) {
				// Users are added on mentions in file-rooms,
				// so they can see the room in their room list and
				// the notification can be parsed and links to an existing room,
				// where they are a participant of.
				$user = $this->userManager->get($userId);
				$this->participantService->addUsers($room, [[
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $userId,
					'displayName' => $user ? $user->getDisplayName() : $userId,
				]]);
				return true;
			}
			return false;
		}
	}

	/**
	 * Determines whether a participant should be notified about the message:
	 *
	 * 1. The participant is not a guest
	 * 2. The participant is not the writing user
	 * 3. The participant was not mentioned already
	 * 4. The participant must not be active in the room
	 *
	 * @param Participant $participant
	 * @param IComment $comment
	 * @param array $alreadyNotifiedUsers
	 * @psalm-param array<int, array{type: string, id: string}> $alreadyNotifiedUsers
	 * @return bool
	 */
	protected function shouldParticipantBeNotified(Participant $participant, IComment $comment, array $alreadyNotifiedUsers): bool {
		if ($participant->getAttendee()->getActorType() !== Attendee::ACTOR_USERS) {
			return false;
		}

		$userId = $participant->getAttendee()->getActorId();
		if ($comment->getActorType() === Attendee::ACTOR_USERS && $userId === $comment->getActorId()) {
			// Do not notify the author
			return false;
		}

		$actorType = $participant->getAttendee()->getActorType();
		foreach ($alreadyNotifiedUsers as $user) {
			if ($user['id'] === $userId && $user['type'] === $actorType) {
				return false;
			}
		}

		if ($participant->getSession() instanceof Session) {
			// User is online
			return $participant->getSession()->getLastPing() < $this->timeFactory->getTime() - Session::SESSION_TIMEOUT;
		}

		return true;
	}
}
