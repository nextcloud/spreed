<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Files\Util;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
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
	public function __construct(
		private INotificationManager $notificationManager,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private ParticipantService $participantService,
		private IConfig $config,
		private ITimeFactory $timeFactory,
		private Util $util,
	) {
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
	 * @psalm-param array<int, array{id: string, type: string, reason: string, sourceId?: string, attendee?: Attendee}> $alreadyNotifiedUsers
	 * @param bool $silent
	 * @param Participant|null $participant
	 * @return string[] Users that were mentioned
	 * @psalm-return array<int, array{id: string, type: string, reason: string, sourceId?: string, attendee?: Attendee}>
	 */
	public function notifyMentionedUsers(Room $chat, IComment $comment, array $alreadyNotifiedUsers, bool $silent, ?Participant $participant = null): array {
		$usersToNotify = $this->getUsersToNotify($chat, $comment, $alreadyNotifiedUsers, $participant);

		if (!$usersToNotify) {
			return $alreadyNotifiedUsers;
		}

		$shouldFlush = false;
		if (!$silent) {
			$notification = $this->createNotification($chat, $comment, 'mention');
			$parameters = $notification->getSubjectParameters();
			$shouldFlush = $this->notificationManager->defer();
		}

		foreach ($usersToNotify as $mentionedUser) {
			if ($this->shouldMentionedUserBeNotified($mentionedUser['id'], $comment, $chat, $mentionedUser['attendee'] ?? null, $mentionedUser['reason'])) {
				if (!$silent) {
					$notification->setUser($mentionedUser['id']);
					if (isset($mentionedUser['reason'])) {
						$notification->setSubject('mention_' . $mentionedUser['reason'], array_merge($parameters, [
							'sourceId' => $mentionedUser['sourceId'] ?? null,
						]));
					} else {
						$notification->setSubject('mention', $parameters);
					}
					$this->notificationManager->notify($notification);
				}
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
	 * @psalm-param array<int, array{id: string, type: string, reason: string, sourceId?: string, attendee?: Attendee}> $alreadyNotifiedUsers
	 * @param Participant|null $participant
	 * @return array
	 * @psalm-return array<int, array{id: string, type: string, reason: string, sourceId?: string, attendee?: Attendee}>
	 */
	public function getUsersToNotify(Room $chat, IComment $comment, array $alreadyNotifiedUsers, ?Participant $participant = null): array {
		$usersToNotify = $this->getMentionedUsers($comment);
		$usersToNotify = $this->getMentionedGroupMembers($chat, $comment, $usersToNotify);
		$usersToNotify = $this->addMentionAllToList($chat, $usersToNotify, $participant);
		$usersToNotify = $this->removeAlreadyNotifiedUsers($usersToNotify, $alreadyNotifiedUsers);

		return $usersToNotify;
	}

	/**
	 * @param array $usersToNotify
	 * @psalm-param array<int, array{id: string, type: string, reason: string, sourceId?: string, attendee?: Attendee}> $usersToNotify
	 * @param array $alreadyNotifiedUsers
	 * @psalm-param array<int, array{id: string, type: string, reason: string, sourceId?: string, attendee?: Attendee}> $alreadyNotifiedUsers
	 * @return array
	 * @psalm-return array<int, array{id: string, type: string, reason: string, sourceId?: string, attendee?: Attendee}>
	 */
	private function removeAlreadyNotifiedUsers(array $usersToNotify, array $alreadyNotifiedUsers): array {
		return array_filter($usersToNotify, static function (array $userToNotify) use ($alreadyNotifiedUsers): bool {
			foreach ($alreadyNotifiedUsers as $alreadyNotified) {
				if ($alreadyNotified['id'] === $userToNotify['id'] && $alreadyNotified['type'] === $userToNotify['type']) {
					return false;
				}
			}
			return true;
		});
	}

	/**
	 * @param Room $chat
	 * @param array $list
	 * @psalm-param array<int, array{id: string, type: string, reason: string, sourceId?: string}> $list
	 * @param Participant|null $participant
	 * @return array
	 * @psalm-return array<int, array{id: string, type: string, reason: string, sourceId?: string, attendee?: Attendee}>
	 */
	private function addMentionAllToList(Room $chat, array $list, ?Participant $participant = null): array {
		$usersToNotify = array_filter($list, static function (array $entry): bool {
			return $entry['type'] !== Attendee::ACTOR_USERS || $entry['id'] !== 'all';
		});

		if (count($list) === count($usersToNotify)) {
			return $usersToNotify;
		}
		if ($chat->getMentionPermissions() === Room::MENTION_PERMISSIONS_MODERATORS && (!$participant instanceof Participant || !$participant->hasModeratorPermissions())) {
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
				'reason' => 'all',
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
	 * @param bool $silent
	 * @return array[] Actor that was replied to
	 * @psalm-return array<int, array{id: string, type: string, reason: string}>
	 */
	public function notifyReplyToAuthor(Room $chat, IComment $comment, IComment $replyTo, bool $silent): array {
		if ($replyTo->getActorType() !== Attendee::ACTOR_USERS && $replyTo->getActorType() !== Attendee::ACTOR_FEDERATED_USERS) {
			// No reply notification when the replyTo-author was not a user or federated user
			return [];
		}

		if ($replyTo->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
			return [
				[
					'id' => $replyTo->getActorId(),
					'type' => $replyTo->getActorType(),
					'reason' => 'reply',
				],
			];
		}

		if (!$this->shouldMentionedUserBeNotified($replyTo->getActorId(), $comment, $chat, null, 'reply')) {
			return [];
		}

		if (!$silent) {
			$notification = $this->createNotification($chat, $comment, 'reply');
			$notification->setUser($replyTo->getActorId());
			$this->notificationManager->notify($notification);
		}

		return [
			[
				'id' => $replyTo->getActorId(),
				'type' => $replyTo->getActorType(),
				'reason' => 'reply',
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
	 * @param bool $silent
	 * @psalm-param array<int, array{id: string, type: string, reason: string, sourceId?: string, attendee?: Attendee}> $alreadyNotifiedUsers
	 */
	public function notifyOtherParticipant(Room $chat, IComment $comment, array $alreadyNotifiedUsers, bool $silent): void {
		if ($silent) {
			return;
		}

		$participants = $this->participantService->getParticipantsByNotificationLevel($chat, Participant::NOTIFY_ALWAYS);

		$notification = $this->createNotification($chat, $comment, 'chat');
		foreach ($participants as $participant) {
			if (!$this->shouldParticipantBeNotified($participant, $comment, $alreadyNotifiedUsers)) {
				continue;
			}

			$notification->setUser($participant->getAttendee()->getActorId());
			$this->notificationManager->notify($notification);
		}

		// Also notify default participants in one-to-one chats or when the admin default is "always"
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

		try {
			$participant = $this->participantService->getParticipant($chat, $comment->getActorId(), false);
		} catch (ParticipantNotFoundException $e) {
			return;
		}

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
			], $reaction);
			$notification->setUser($comment->getActorId());
			$this->notificationManager->notify($notification);
		}
	}

	/**
	 * Removes all the pending notifications for the room with the given ID.
	 */
	public function removePendingNotificationsForRoom(Room $chat, bool $chatOnly = false): void {
		$notification = $this->notificationManager->createNotification();
		$shouldFlush = $this->notificationManager->defer();

		// @todo this should be in the Notifications\Hooks
		$notification->setApp('spreed');

		$objectTypes = [
			'chat',
			'reminder',
		];
		if (!$chatOnly) {
			$objectTypes = [
				'call',
				'chat',
				'room',
				'recording',
				'recording_information',
				'remote_talk_share',
			];
		}
		foreach ($objectTypes as $type) {
			$notification->setObject($type, $chat->getToken());
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
	 * @param ?string $userId
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
	 * Remove all mention notifications of users that got their mention removed
	 *
	 * @param list<string> $userIds
	 */
	public function removeMentionNotificationAfterEdit(Room $chat, IComment $comment, array $userIds): void {
		$shouldFlush = $this->notificationManager->defer();
		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp('spreed')
			->setObject('chat', $chat->getToken())
			// FIXME message_parameters are not handled by notification app, so this removes all notifications :(
			->setMessage('comment', [
				'commentId' => $comment->getId(),
			]);

		foreach (['mention_all', 'mention_direct'] as $subject) {
			$notification->setSubject($subject);
			foreach ($userIds as $userId) {
				$notification->setUser($userId);
				$this->notificationManager->markProcessed($notification);
			}
		}

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
	 * Returns the cloud IDs of the federated users mentioned in the given comment.
	 *
	 * @param IComment $comment
	 * @return string[] the mentioned cloud IDs
	 */
	public function getMentionedCloudIds(IComment $comment): array {
		$mentionedFederatedUsers = $this->getMentionedFederatedUsers($comment);
		return array_map(static function ($mentionedUser) {
			return $mentionedUser['id'];
		}, $mentionedFederatedUsers);
	}

	/**
	 * @param IComment $comment
	 * @return array[]
	 * @psalm-return array<int, array{type: string, id: string, reason: string}>
	 */
	private function getMentionedUsers(IComment $comment): array {
		$mentions = $comment->getMentions();

		if (empty($mentions)) {
			return [];
		}

		$mentionedUsers = [];
		foreach ($mentions as $mention) {
			if ($mention['type'] !== 'user') {
				continue;
			}

			$mentionedUsers[] = [
				'id' => $mention['id'],
				'type' => Attendee::ACTOR_USERS,
				'reason' => 'direct',
			];
		}
		return $mentionedUsers;
	}

	/**
	 * @param IComment $comment
	 * @return array[]
	 * @psalm-return array<int, array{type: string, id: string, reason: string}>
	 */
	private function getMentionedFederatedUsers(IComment $comment): array {
		$mentions = $comment->getMentions();

		if (empty($mentions)) {
			return [];
		}

		$mentionedUsers = [];
		foreach ($mentions as $mention) {
			if ($mention['type'] !== 'federated_user') {
				continue;
			}

			$mentionedUsers[] = [
				'id' => $mention['id'],
				'type' => Attendee::ACTOR_FEDERATED_USERS,
				'reason' => 'direct',
			];
		}
		return $mentionedUsers;
	}

	/**
	 * @param Room $chat
	 * @param IComment $comment
	 * @param array $list
	 * @psalm-param array<int, array{id: string, type: string, reason: string}> $list
	 * @return array[]
	 * @psalm-return array<int, array{type: string, id: string, reason: string, sourceId?: string}>
	 */
	private function getMentionedGroupMembers(Room $chat, IComment $comment, array $list): array {
		$mentions = $comment->getMentions();

		if (empty($mentions)) {
			return [];
		}

		$alreadyMentionedUserIds = array_filter(
			array_map(static fn (array $entry) => $entry['type'] === Attendee::ACTOR_USERS ? $entry['id'] : null, $list),
			static fn ($userId) => $userId !== null
		);
		$alreadyMentionedUserIds = array_flip($alreadyMentionedUserIds);

		foreach ($mentions as $mention) {
			if ($mention['type'] !== 'group') {
				continue;
			}

			$group = $this->groupManager->get($mention['id']);
			if (!$group instanceof IGroup) {
				continue;
			}

			try {
				$this->participantService->getParticipantByActor($chat, Attendee::ACTOR_GROUPS, $group->getGID());
			} catch (ParticipantNotFoundException $e) {
				continue;
			}

			$members = $group->getUsers();
			foreach ($members as $member) {
				if (isset($alreadyMentionedUserIds[$member->getUID()])) {
					continue;
				}

				$list[] = [
					'id' => $member->getUID(),
					'type' => Attendee::ACTOR_USERS,
					'reason' => 'group',
					'sourceId' => $group->getGID(),
				];
				$alreadyMentionedUserIds[$member->getUID()] = true;
			}
		}

		return $list;
	}

	/**
	 * Creates a notification for the given chat message comment and mentioned
	 * user ID.
	 */
	private function createNotification(Room $chat, IComment $comment, string $subject, array $subjectData = [], ?IComment $reaction = null): INotification {
		$subjectData['userType'] = $reaction ? $reaction->getActorType() : $comment->getActorType();
		$subjectData['userId'] = $reaction ? $reaction->getActorId() : $comment->getActorId();

		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('spreed')
			->setObject('chat', $chat->getToken())
			->setSubject($subject, $subjectData)
			->setMessage($comment->getVerb(), [
				'commentId' => $comment->getId(),
			])
			->setDateTime($reaction ? $reaction->getCreationDateTime() : $comment->getCreationDateTime());

		return $notification;
	}

	protected function getDefaultGroupNotification(): int {
		return (int)$this->config->getAppValue('spreed', 'default_group_notification', (string)Participant::NOTIFY_MENTION);
	}

	/**
	 * Determines whether a user should be notified about the mention:
	 *
	 * 1. The user did not mention themself
	 * 2. The user must exist
	 * 3. The user must be a participant of the room
	 * 4. The user must not be active in the room
	 */
	protected function shouldMentionedUserBeNotified(string $userId, IComment $comment, Room $room, ?Attendee $attendee, string $reason): bool {
		if ($comment->getActorType() === Attendee::ACTOR_USERS && $userId === $comment->getActorId()) {
			// Do not notify the user if they mentioned themselves
			return false;
		}

		try {
			if (!$attendee instanceof Attendee) {
				if (!$this->userManager->userExists($userId)) {
					return false;
				}

				$participant = $this->participantService->getParticipant($room, $userId, false);
				$attendee = $participant->getAttendee();
			} else {
				$participant = new Participant($room, $attendee, null);
			}

			if ($reason === 'all' && $attendee->isArchived()) {
				return false;
			}

			if ($room->getLobbyState() !== Webinary::LOBBY_NONE &&
				!($participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE)) {
				return false;
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
				$userDisplayName = $this->userManager->getDisplayName($userId);
				$this->participantService->addUsers($room, [[
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $userId,
					'displayName' => $userDisplayName ?? $userId,
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
	 * @psalm-param array<int, array{type: string, id: string, reason: string, sourceId?: string, attendee?: Attendee}> $alreadyNotifiedUsers
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
