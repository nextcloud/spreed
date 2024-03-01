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

use DateInterval;
use OC\Memcache\ArrayCache;
use OC\Memcache\NullCache;
use OCA\Talk\Events\BeforeChatMessageSentEvent;
use OCA\Talk\Events\BeforeSystemMessageSentEvent;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Poll;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\PollService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Share\RoomShareProvider;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Comments\IComment;
use OCP\Comments\MessageTooLongException;
use OCP\Comments\NotFoundException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Security\RateLimiting\IRateLimitExceededException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

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
	public const MAX_CHAT_LENGTH = 32000;

	public const RATE_LIMIT_GUEST_MENTIONS_LIMIT = 50;
	public const RATE_LIMIT_GUEST_MENTIONS_PERIOD = 24 * 60 * 60;

	public const GEO_LOCATION_VALIDATOR = '/^geo:-?\d{1,2}(\.\d+)?,-?\d{1,3}(\.\d+)?(,-?\d+(\.\d+)?)?(;crs=wgs84)?(;u=\d+(\.\d+)?)?$/i';
	public const VERB_MESSAGE = 'comment';
	public const VERB_SYSTEM = 'system';
	public const VERB_OBJECT_SHARED = 'object_shared';
	public const VERB_COMMAND = 'command';
	public const VERB_MESSAGE_DELETED = 'comment_deleted';
	public const VERB_REACTION = 'reaction';
	public const VERB_REACTION_DELETED = 'reaction_deleted';
	public const VERB_VOICE_MESSAGE = 'voice-message';
	public const VERB_RECORD_AUDIO = 'record-audio';
	public const VERB_RECORD_VIDEO = 'record-video';

	protected ICache $cache;
	protected ICache $unreadCountCache;

	public function __construct(
		private CommentsManager $commentsManager,
		private IEventDispatcher $dispatcher,
		private IDBConnection $connection,
		private INotificationManager $notificationManager,
		private IManager $shareManager,
		private RoomShareProvider $shareProvider,
		private ParticipantService $participantService,
		private RoomService $roomService,
		private PollService $pollService,
		private Notifier $notifier,
		ICacheFactory $cacheFactory,
		protected ITimeFactory $timeFactory,
		protected AttachmentService $attachmentService,
		protected IReferenceManager $referenceManager,
		protected ILimiter $rateLimiter,
		protected IRequest $request,
	) {
		$this->cache = $cacheFactory->createDistributed('talk/lastmsgid');
		$this->unreadCountCache = $cacheFactory->createDistributed('talk/unreadcount');
	}

	/**
	 * Sends a new message to the given chat.
	 *
	 * @param bool $shouldSkipLastMessageUpdate If multiple messages will be posted
	 *             (e.g. when adding multiple users to a room) we can skip the last
	 *             message and last activity update until the last entry was created
	 *             and then update with those values.
	 *             This will replace O(n) with 1 database update.
	 */
	public function addSystemMessage(
		Room $chat,
		string $actorType,
		string $actorId,
		string $message,
		\DateTime $creationDateTime,
		bool $sendNotifications,
		?string $referenceId = null,
		?IComment $replyTo = null,
		bool $shouldSkipLastMessageUpdate = false,
		bool $silent = false,
	): IComment {
		$comment = $this->commentsManager->create($actorType, $actorId, 'chat', (string) $chat->getId());
		$comment->setMessage($message, self::MAX_CHAT_LENGTH);
		$comment->setCreationDateTime($creationDateTime);
		if ($referenceId !== null) {
			$referenceId = trim(substr($referenceId, 0, 64));
			if ($referenceId !== '') {
				$comment->setReferenceId($referenceId);
			}
		}
		if ($replyTo !== null) {
			$comment->setParentId($replyTo->getId());
		}

		$messageDecoded = json_decode($message, true);
		$messageType = $messageDecoded['message'] ?? '';

		if ($messageType === 'object_shared' || $messageType === 'file_shared') {
			$comment->setVerb(self::VERB_OBJECT_SHARED);
		} else {
			$comment->setVerb(self::VERB_SYSTEM);
		}

		if ($silent) {
			$comment->setMetaData([
				'silent' => true,
			]);
		}

		$this->setMessageExpiration($chat, $comment);

		$shouldFlush = $this->notificationManager->defer();

		$event = new BeforeSystemMessageSentEvent($chat, $comment, silent: $silent, skipLastActivityUpdate: $shouldSkipLastMessageUpdate);
		$this->dispatcher->dispatchTyped($event);
		try {
			$this->commentsManager->save($comment);

			if (!$shouldSkipLastMessageUpdate) {
				// Update last_message
				$this->roomService->setLastMessage($chat, $comment);
				$this->unreadCountCache->clear($chat->getId() . '-');
			}

			if ($sendNotifications) {
				/** @var ?IComment $captionComment */
				$captionComment = null;
				$alreadyNotifiedUsers = $usersDirectlyMentioned = $federatedUsersDirectlyMentioned = [];
				if ($messageType === 'file_shared') {
					if (isset($messageDecoded['parameters']['metaData']['caption'])) {
						$captionComment = clone $comment;
						$captionComment->setMessage($messageDecoded['parameters']['metaData']['caption'], self::MAX_CHAT_LENGTH);
						$usersDirectlyMentioned = $this->notifier->getMentionedUserIds($captionComment);
						$federatedUsersDirectlyMentioned = $this->notifier->getMentionedCloudIds($captionComment);
					}
					if ($replyTo instanceof IComment) {
						$alreadyNotifiedUsers = $this->notifier->notifyReplyToAuthor($chat, $comment, $replyTo, $silent);
						if ($replyTo->getActorType() === Attendee::ACTOR_USERS) {
							$usersDirectlyMentioned[] = $replyTo->getActorId();
						} elseif ($replyTo->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
							$federatedUsersDirectlyMentioned[] = $replyTo->getActorId();
						}
					}
				}

				$alreadyNotifiedUsers = $this->notifier->notifyMentionedUsers($chat, $captionComment ?? $comment, $alreadyNotifiedUsers, $silent);
				if (!empty($alreadyNotifiedUsers)) {
					$userIds = array_column($alreadyNotifiedUsers, 'id');
					$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_USERS, $userIds, (int) $comment->getId(), $usersDirectlyMentioned);
				}
				if (!empty($federatedUsersDirectlyMentioned)) {
					$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_FEDERATED_USERS, $federatedUsersDirectlyMentioned, (int) $comment->getId(), $federatedUsersDirectlyMentioned);
				}

				$this->notifier->notifyOtherParticipant($chat, $comment, [], $silent);
			}

			if (!$shouldSkipLastMessageUpdate && $sendNotifications) {
				// Update the read-marker for the author when it is a "relevant" system message,
				// e.g. sharing an item to the chat
				try {
					$participant = $this->participantService->getParticipantByActor($chat, $actorType, $actorId);
					$this->participantService->updateLastReadMessage($participant, (int) $comment->getId());
				} catch (ParticipantNotFoundException) {
					// Participant not found => No read-marker update needed
				}
			}

			$event = new SystemMessageSentEvent($chat, $comment, silent: $silent, skipLastActivityUpdate: $shouldSkipLastMessageUpdate);
			$this->dispatcher->dispatchTyped($event);
		} catch (NotFoundException $e) {
		}
		$this->cache->remove($chat->getToken());

		if ($shouldFlush) {
			$this->notificationManager->flush();
		}

		if ($messageType === 'object_shared' || $messageType === 'file_shared') {
			$this->attachmentService->createAttachmentEntry($chat, $comment, $messageType, $messageDecoded['parameters'] ?? []);
		}

		return $comment;
	}

	/**
	 * Sends a new message to the given chat.
	 *
	 * @param Room $chat
	 * @param string $message
	 * @return IComment
	 */
	public function addChangelogMessage(Room $chat, string $message): IComment {
		$comment = $this->commentsManager->create(Attendee::ACTOR_GUESTS, Attendee::ACTOR_ID_CHANGELOG, 'chat', (string) $chat->getId());

		$comment->setMessage($message, self::MAX_CHAT_LENGTH);
		$comment->setCreationDateTime($this->timeFactory->getDateTime());
		$comment->setVerb(self::VERB_MESSAGE); // Has to be 'comment', so it counts as unread message

		$event = new BeforeSystemMessageSentEvent($chat, $comment);
		$this->dispatcher->dispatchTyped($event);
		try {
			$this->commentsManager->save($comment);

			// Update last_message
			$this->roomService->setLastMessage($chat, $comment);
			$this->unreadCountCache->clear($chat->getId() . '-');

			$event = new SystemMessageSentEvent($chat, $comment);
			$this->dispatcher->dispatchTyped($event);
		} catch (NotFoundException $e) {
		}
		$this->cache->remove($chat->getToken());

		return $comment;
	}

	/**
	 * Sends a new message to the given chat.
	 *
	 * @throws IRateLimitExceededException Only when $rateLimitGuestMentions is true and the author is a guest participant
	 * @throws MessageTooLongException
	 */
	public function sendMessage(Room $chat, ?Participant $participant, string $actorType, string $actorId, string $message, \DateTime $creationDateTime, ?IComment $replyTo = null, string $referenceId = '', bool $silent = false, bool $rateLimitGuestMentions = true): IComment {
		$comment = $this->commentsManager->create($actorType, $actorId, 'chat', (string) $chat->getId());
		$comment->setMessage($message, self::MAX_CHAT_LENGTH);
		$comment->setCreationDateTime($creationDateTime);
		// A verb ('comment', 'like'...) must be provided to be able to save a
		// comment
		$comment->setVerb(self::VERB_MESSAGE);

		if ($replyTo instanceof IComment) {
			$comment->setParentId($replyTo->getId());
		}

		$referenceId = trim(substr($referenceId, 0, 64));
		if ($referenceId !== '') {
			$comment->setReferenceId($referenceId);
		}
		$this->setMessageExpiration($chat, $comment);

		if ($rateLimitGuestMentions && $participant instanceof Participant && $participant->isGuest()) {
			$mentions = $comment->getMentions();
			if (!empty($mentions)) {
				$this->rateLimiter->registerAnonRequest(
					'talk-mentions',
					self::RATE_LIMIT_GUEST_MENTIONS_LIMIT,
					self::RATE_LIMIT_GUEST_MENTIONS_PERIOD,
					$this->request->getRemoteAddress(),
				);
			}
		}

		if ($silent) {
			$comment->setMetaData([
				'silent' => true,
			]);
		}

		$event = new BeforeChatMessageSentEvent($chat, $comment, $participant, $silent);
		$this->dispatcher->dispatchTyped($event);

		$shouldFlush = $this->notificationManager->defer();
		try {
			$this->commentsManager->save($comment);

			if ($participant instanceof Participant) {
				$this->participantService->updateLastReadMessage($participant, (int) $comment->getId());
			}

			// Update last_message
			if ($comment->getActorType() !== Attendee::ACTOR_BOTS
				|| $comment->getActorId() === Attendee::ACTOR_ID_CHANGELOG
				|| str_starts_with($comment->getActorId(), Attendee::ACTOR_BOT_PREFIX)) {
				$this->roomService->setLastMessage($chat, $comment);
				$this->unreadCountCache->clear($chat->getId() . '-');
			} else {
				$this->roomService->setLastActivity($chat, $comment->getCreationDateTime());
			}

			$alreadyNotifiedUsers = [];
			$usersDirectlyMentioned = $this->notifier->getMentionedUserIds($comment);
			$federatedUsersDirectlyMentioned = $this->notifier->getMentionedCloudIds($comment);
			if ($replyTo instanceof IComment) {
				$alreadyNotifiedUsers = $this->notifier->notifyReplyToAuthor($chat, $comment, $replyTo, $silent);
				if ($replyTo->getActorType() === Attendee::ACTOR_USERS) {
					$usersDirectlyMentioned[] = $replyTo->getActorId();
				} elseif ($replyTo->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
					$federatedUsersDirectlyMentioned[] = $replyTo->getActorId();
				}
			}

			$alreadyNotifiedUsers = $this->notifier->notifyMentionedUsers($chat, $comment, $alreadyNotifiedUsers, $silent);
			if (!empty($alreadyNotifiedUsers)) {
				$userIds = array_column($alreadyNotifiedUsers, 'id');
				$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_USERS, $userIds, (int) $comment->getId(), $usersDirectlyMentioned);
			}
			if (!empty($federatedUsersDirectlyMentioned)) {
				$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_FEDERATED_USERS, $federatedUsersDirectlyMentioned, (int) $comment->getId(), $federatedUsersDirectlyMentioned);
			}

			// User was not mentioned, send a normal notification
			$this->notifier->notifyOtherParticipant($chat, $comment, $alreadyNotifiedUsers, $silent);

			$event = new ChatMessageSentEvent($chat, $comment, $participant, $silent);
			$this->dispatcher->dispatchTyped($event);
		} catch (NotFoundException $e) {
		}
		$this->cache->remove($chat->getToken());
		if ($shouldFlush) {
			$this->notificationManager->flush();
		}

		return $comment;
	}

	private function setMessageExpiration(Room $room, IComment $comment): void {
		$messageExpiration = $room->getMessageExpiration();
		if (!$messageExpiration) {
			return;
		}

		$dateTime = $this->timeFactory->getDateTime();
		$dateTime->add(DateInterval::createFromDateString($messageExpiration . ' seconds'));
		$comment->setExpireDate($dateTime);
	}

	/**
	 * @param Room $room
	 * @param Participant $participant
	 * @param array $messageData
	 * @throws ShareNotFound
	 */
	public function unshareFileOnMessageDelete(Room $room, Participant $participant, array $messageData): void {
		if (!isset($messageData['message'], $messageData['parameters']['share']) || $messageData['message'] !== 'file_shared') {
			// Not a file share
			return;
		}

		$share = $this->shareManager->getShareById('ocRoomShare:' . $messageData['parameters']['share']);

		if ($share->getShareType() !== IShare::TYPE_ROOM || $share->getSharedWith() !== $room->getToken()) {
			// Share does not match the correct room
			throw new ShareNotFound();
		}

		$attendee = $participant->getAttendee();

		if (!$participant->hasModeratorPermissions(false) &&
			!($attendee->getActorType() === Attendee::ACTOR_USERS && $attendee->getActorId() === $share->getShareOwner())) {
			// Only moderators or the share owner can delete the share
			return;
		}

		$this->shareManager->deleteShare($share);
	}

	/**
	 * @throws ShareNotFound
	 */
	public function removePollOnMessageDelete(Room $room, Participant $participant, array $messageData, \DateTime $deletionTime): void {
		if (!isset($messageData['message'], $messageData['parameters']['objectType'], $messageData['parameters']['objectId'])
			|| $messageData['message'] !== 'object_shared'
			|| $messageData['parameters']['objectType'] !== 'talk-poll') {
			// Not a poll share
			return;
		}

		try {
			$poll = $this->pollService->getPoll($room->getId(), (int)$messageData['parameters']['objectId']);
		} catch (DoesNotExistException $e) {
			return;
		}

		if ($poll->getStatus() === Poll::STATUS_CLOSED) {
			$closingMessages = $this->commentsManager->searchForObjects(
				json_encode([
					'message' => 'poll_closed',
					'parameters' => [
						'poll' => [
							'type' => 'talk-poll',
							'id' => $poll->getId(),
							'name' => $poll->getQuestion(),
						],
					],
				], JSON_THROW_ON_ERROR),
				'chat',
				[(string) $room->getId()],
				'system',
				0
			);
			foreach ($closingMessages as $closingMessage) {
				$this->deleteMessage($room, $closingMessage, $participant, $deletionTime);
			}
		}

		if (!$participant->hasModeratorPermissions(false)) {
			$attendee = $participant->getAttendee();
			if (!($attendee->getActorType() === $poll->getActorType()
				&& $attendee->getActorId() === $poll->getActorId())) {
				// Only moderators or the poll creator can delete it
				return;
			}
		}

		$this->pollService->deleteByPollId($poll->getId());
	}

	/**
	 * @param Room $chat
	 * @param IComment $comment
	 * @param Participant $participant
	 * @param \DateTime $deletionTime
	 * @return IComment
	 * @throws ShareNotFound
	 */
	public function deleteMessage(Room $chat, IComment $comment, Participant $participant, \DateTime $deletionTime): IComment {
		if ($comment->getVerb() === self::VERB_OBJECT_SHARED) {
			$messageData = json_decode($comment->getMessage(), true);
			$this->unshareFileOnMessageDelete($chat, $participant, $messageData);
			$this->removePollOnMessageDelete($chat, $participant, $messageData, $deletionTime);
		}

		$comment->setMessage(
			json_encode([
				'deleted_by_type' => $participant->getAttendee()->getActorType(),
				'deleted_by_id' => $participant->getAttendee()->getActorId(),
				'deleted_on' => $deletionTime->getTimestamp(),
			])
		);
		$comment->setVerb(self::VERB_MESSAGE_DELETED);

		$metaData = $comment->getMetaData() ?? [];
		if (isset($metaData['last_edited_by_type'])) {
			unset(
				$metaData['last_edited_by_type'],
				$metaData['last_edited_by_id'],
				$metaData['last_edited_time']
			);
			$comment->setMetaData($metaData);
		}

		$this->commentsManager->save($comment);

		$this->attachmentService->deleteAttachmentByMessageId((int) $comment->getId());

		$this->referenceManager->invalidateCache($chat->getToken());

		return $this->addSystemMessage(
			$chat,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId(),
			json_encode(['message' => 'message_deleted', 'parameters' => ['message' => $comment->getId()]]),
			$this->timeFactory->getDateTime(),
			false,
			null,
			$comment,
			true
		);
	}

	/**
	 * @param Room $chat
	 * @param IComment $comment
	 * @param Participant $participant
	 * @param \DateTime $editTime
	 * @param string $message
	 * @return IComment
	 * @throws MessageTooLongException
	 * @throws \InvalidArgumentException When the message is empty or the shared object is not a file share with caption
	 */
	public function editMessage(Room $chat, IComment $comment, Participant $participant, \DateTime $editTime, string $message): IComment {
		if (trim($message) === '') {
			throw new \InvalidArgumentException('message');
		}

		if ($comment->getVerb() === ChatManager::VERB_OBJECT_SHARED) {
			$messageData = json_decode($comment->getMessage(), true);
			if (!isset($messageData['message']) || $messageData['message'] !== 'file_shared') {
				// Not a file share
				throw new \InvalidArgumentException('object_share');
			}

			$messageData['parameters'] ??= [];
			$messageData['parameters']['metaData'] ??= [];
			$messageData['parameters']['metaData']['caption'] = $message;
			$message = json_encode($messageData);
		}

		$metaData = $comment->getMetaData() ?? [];
		$metaData['last_edited_by_type'] = $participant->getAttendee()->getActorType();
		$metaData['last_edited_by_id'] = $participant->getAttendee()->getActorId();
		$metaData['last_edited_time'] = $editTime->getTimestamp();
		$comment->setMetaData($metaData);

		$wasSilent = $metaData['silent'] ?? false;

		if (!$wasSilent) {
			$mentionsBefore = $comment->getMentions();
			$usersDirectlyMentionedBefore = $this->notifier->getMentionedUserIds($comment);
			$usersToNotifyBefore = $this->notifier->getUsersToNotify($chat, $comment, []);
		}
		$comment->setMessage($message, self::MAX_CHAT_LENGTH);
		if (!$wasSilent) {
			$mentionsAfter = $comment->getMentions();
		}

		$this->commentsManager->save($comment);
		$this->referenceManager->invalidateCache($chat->getToken());

		if (!$wasSilent) {
			$removedMentions = empty($mentionsAfter) ? $mentionsBefore : array_udiff($mentionsBefore, $mentionsAfter, [$this, 'compareMention']);
			$addedMentions = empty($mentionsBefore) ? $mentionsAfter : array_udiff($mentionsAfter, $mentionsBefore, [$this, 'compareMention']);

			if (!empty($removedMentions)) {
				$usersToNotifyAfter = $this->notifier->getUsersToNotify($chat, $comment, []);
				$removedUsersMentioned = array_udiff($usersToNotifyBefore, $usersToNotifyAfter, [$this, 'compareMention']);
				$userIds = array_column($removedUsersMentioned, 'id');
				$this->notifier->removeMentionNotificationAfterEdit($chat, $comment, $userIds);
			}

			if (!empty($addedMentions)) {
				$usersDirectlyMentionedAfter = $this->notifier->getMentionedUserIds($comment);
				$federatedUsersDirectlyMentionedAfter = $this->notifier->getMentionedCloudIds($comment);
				$addedUsersDirectMentioned = array_diff($usersDirectlyMentionedAfter, $usersDirectlyMentionedBefore);

				$alreadyNotifiedUsers = $this->notifier->notifyMentionedUsers($chat, $comment, $usersToNotifyBefore, silent: false);
				if (!empty($alreadyNotifiedUsers)) {
					$userIds = array_column($alreadyNotifiedUsers, 'id');
					$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_USERS, $userIds, (int) $comment->getId(), $addedUsersDirectMentioned);
				}
				if (!empty($federatedUsersDirectlyMentionedAfter)) {
					$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_FEDERATED_USERS, $federatedUsersDirectlyMentionedAfter, (int) $comment->getId(), $federatedUsersDirectlyMentionedAfter);
				}
			}
		}

		return $this->addSystemMessage(
			$chat,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId(),
			json_encode(['message' => 'message_edited', 'parameters' => ['message' => $comment->getId()]]),
			$this->timeFactory->getDateTime(),
			false,
			null,
			$comment,
			true
		);
	}

	protected static function compareMention(array $mention1, array $mention2): int {
		if ($mention1['type'] === $mention2['type']) {
			return $mention1['id'] <=> $mention2['id'];
		}
		return $mention1['type'] <=> $mention2['type'];
	}

	public function clearHistory(Room $chat, string $actorType, string $actorId): IComment {
		$this->commentsManager->deleteCommentsAtObject('chat', (string) $chat->getId());

		$this->shareProvider->deleteInRoom($chat->getToken());

		$this->notifier->removePendingNotificationsForRoom($chat, true);

		$this->participantService->resetChatDetails($chat);

		$this->pollService->deleteByRoomId($chat->getId());

		return $this->addSystemMessage(
			$chat,
			$actorType,
			$actorId,
			json_encode(['message' => 'history_cleared', 'parameters' => []]),
			$this->timeFactory->getDateTime(),
			false
		);
	}

	/**
	 * @param Room $chat
	 * @param string $parentId
	 * @return IComment
	 * @throws NotFoundException
	 */
	public function getParentComment(Room $chat, string $parentId): IComment {
		$comment = $this->commentsManager->get($parentId);

		if ($comment->getObjectType() !== 'chat' || $comment->getObjectId() !== (string) $chat->getId()) {
			throw new NotFoundException('Parent not found in the right context');
		}

		return $comment;
	}

	/**
	 * @param Room $chat
	 * @param string $messageId
	 * @return IComment
	 * @throws NotFoundException
	 */
	public function getComment(Room $chat, string $messageId): IComment {
		$comment = $this->commentsManager->get($messageId);

		if ($comment->getObjectType() !== 'chat' || $comment->getObjectId() !== (string) $chat->getId()) {
			throw new NotFoundException('Message not found in the right context');
		}

		return $comment;
	}

	public function getLastReadMessageFromLegacy(Room $chat, IUser $user): int {
		$marker = $this->commentsManager->getReadMark('chat', (string) $chat->getId(), $user);
		if ($marker === null) {
			return 0;
		}

		return $this->commentsManager->getLastCommentBeforeDate('chat', (string) $chat->getId(), $marker, self::VERB_MESSAGE);
	}

	public function getUnreadCount(Room $chat, int $lastReadMessage): int {
		/**
		 * for a given message id $lastReadMessage we cache the number of messages
		 * that exist past that message, which happen to also be the number of
		 * unread messages, because this is expensive to query per room and user repeatedly
		 */
		$key = $chat->getId() . '-' . $lastReadMessage;
		$unreadCount = $this->unreadCountCache->get($key);
		if ($unreadCount === null) {
			$unreadCount = $this->commentsManager->getNumberOfCommentsWithVerbsForObjectSinceComment('chat', (string) $chat->getId(), $lastReadMessage, [self::VERB_MESSAGE, 'object_shared']);
			$this->unreadCountCache->set($key, $unreadCount, 1800);
		}
		return $unreadCount;
	}

	/**
	 * Returns the ID of the last chat message, that was read by everyone
	 * sharing their read status.
	 *
	 * @param Room $chat
	 * @return int
	 */
	public function getLastCommonReadMessage(Room $chat): int {
		return $this->participantService->getLastCommonReadChatMessage($chat);
	}

	/**
	 * Receive the history of a chat
	 *
	 * @param Room $chat
	 * @param int $offset Last known message id
	 * @param int $limit
	 * @param bool $includeLastKnown
	 * @return IComment[] the messages found (only the id, actor type and id,
	 *         creation date and message are relevant), or an empty array if the
	 *         timeout expired.
	 */
	public function getHistory(Room $chat, int $offset, int $limit, bool $includeLastKnown): array {
		return $this->commentsManager->getForObjectSince('chat', (string) $chat->getId(), $offset, 'desc', $limit, $includeLastKnown);
	}

	/**
	 * @param Room $chat
	 * @param int $offset Last known message id
	 * @param array $verbs
	 * @param bool $offsetIsVerbMatch
	 * @return IComment
	 * @throws NotFoundException
	 */
	public function getPreviousMessageWithVerb(Room $chat, int $offset, array $verbs, bool $offsetIsVerbMatch): IComment {
		$messages = $this->commentsManager->getCommentsWithVerbForObjectSinceComment(
			'chat',
			(string) $chat->getId(),
			$verbs,
			$offset,
			'desc',
			!$offsetIsVerbMatch ? 2 : 1
		);

		if (empty($messages)) {
			throw new NotFoundException('No comment with verb found');
		}

		return array_pop($messages);
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
	 * @param Room $chat
	 * @param int $offset Last known message id
	 * @param int $limit
	 * @param int $timeout
	 * @param IUser|null $user
	 * @param bool $includeLastKnown
	 * @param bool $markNotificationsAsRead (defaults to true)
	 * @return IComment[] the messages found (only the id, actor type and id,
	 *         creation date and message are relevant), or an empty array if the
	 *         timeout expired.
	 */
	public function waitForNewMessages(Room $chat, int $offset, int $limit, int $timeout, ?IUser $user, bool $includeLastKnown, bool $markNotificationsAsRead = true): array {
		if ($markNotificationsAsRead && $user instanceof IUser) {
			$this->notifier->markMentionNotificationsRead($chat, $user->getUID());
		}

		if ($this->cache instanceof NullCache
			|| $this->cache instanceof ArrayCache) {
			return $this->waitForNewMessagesWithDatabase($chat, $offset, $limit, $timeout, $includeLastKnown);
		}

		return $this->waitForNewMessagesWithCache($chat, $offset, $limit, $timeout, $includeLastKnown);
	}

	/**
	 * Check the cache until we found new messages, or the timeout was reached
	 *
	 * @param Room $chat
	 * @param int $offset
	 * @param int $limit
	 * @param int $timeout
	 * @param bool $includeLastKnown
	 * @return IComment[]
	 */
	protected function waitForNewMessagesWithCache(Room $chat, int $offset, int $limit, int $timeout, bool $includeLastKnown): array {
		$elapsedTime = 0;

		$comments = $this->checkCacheOrDatabase($chat, $offset, $limit, $includeLastKnown);

		while (empty($comments) && $elapsedTime < $timeout) {
			$this->connection->close();
			sleep(1);
			$elapsedTime++;

			$comments = $this->checkCacheOrDatabase($chat, $offset, $limit, $includeLastKnown);
		}

		return $comments;
	}

	/**
	 * Check the cache for the last message id or check the database for updates
	 *
	 * @param Room $chat
	 * @param int $offset
	 * @param int $limit
	 * @param bool $includeLastKnown
	 * @return IComment[]
	 */
	protected function checkCacheOrDatabase(Room $chat, int $offset, int $limit, bool $includeLastKnown): array {
		$cachedId = $this->cache->get($chat->getToken());
		if ($offset === $cachedId) {
			// Cache hit, nothing new ¯\_(ツ)_/¯
			return [];
		}

		// Load data from the database
		$comments = $this->commentsManager->getForObjectSince('chat', (string) $chat->getId(), $offset, 'asc', $limit, $includeLastKnown);

		if (empty($comments)) {
			// We only write the cache when there were no new comments,
			// otherwise it could happen that this is not the last message,
			// but the last within $limit
			$this->cache->set($chat->getToken(), $offset, 30);
			return [];
		}

		return $comments;
	}

	/**
	 * Check the database for new messages until there a new messages or we exceeded the timeout
	 *
	 * @param Room $chat
	 * @param int $offset
	 * @param int $limit
	 * @param int $timeout
	 * @param bool $includeLastKnown
	 * @return array
	 */
	protected function waitForNewMessagesWithDatabase(Room $chat, int $offset, int $limit, int $timeout, bool $includeLastKnown): array {
		$elapsedTime = 0;

		$comments = $this->commentsManager->getForObjectSince('chat', (string) $chat->getId(), $offset, 'asc', $limit, $includeLastKnown);

		while (empty($comments) && $elapsedTime < $timeout) {
			sleep(1);
			$elapsedTime++;

			$comments = $this->commentsManager->getForObjectSince('chat', (string) $chat->getId(), $offset, 'asc', $limit, $includeLastKnown);
		}

		return $comments;
	}

	/**
	 * Deletes all the messages for the given chat.
	 *
	 * @param Room $chat
	 */
	public function deleteMessages(Room $chat): void {
		$this->commentsManager->deleteCommentsAtObject('chat', (string) $chat->getId());

		$this->shareProvider->deleteInRoom($chat->getToken());

		$this->notifier->removePendingNotificationsForRoom($chat);

		$this->attachmentService->deleteAttachmentsForRoom($chat);

		$this->pollService->deleteByRoomId($chat->getId());
	}

	/**
	 * Get messages for the given chat by ID
	 *
	 * @param Room $chat
	 * @param int[] $commentIds
	 * @return IComment[]
	 */
	public function getMessagesForRoomById(Room $chat, array $commentIds): array {
		$comments = $this->commentsManager->getCommentsById(array_map('strval', $commentIds));

		$comments = array_filter($comments, static function (IComment $comment) use ($chat) {
			return $comment->getObjectType() === 'chat'
				&& (int)$comment->getObjectId() === $chat->getId();
		});

		return $comments;
	}

	/**
	 * Get messages by ID
	 *
	 * @param int[] $commentIds
	 * @return IComment[] Key is the message id
	 */
	public function getMessagesById(array $commentIds): array {
		return $this->commentsManager->getCommentsById(array_map('strval', $commentIds));
	}

	/**
	 * Search for comments with a given content
	 *
	 * @param string $search content to search for
	 * @param array $objectIds Limit the search by object ids
	 * @param string $verb Limit the verb of the comment
	 * @param int $offset
	 * @param int $limit
	 * @return IComment[]
	 */
	public function searchForObjects(string $search, array $objectIds, string $verb = '', int $offset = 0, int $limit = 50): array {
		return $this->commentsManager->searchForObjects($search, 'chat', $objectIds, $verb, $offset, $limit);
	}

	/**
	 * Search for comments on one or more objects with a given content
	 *
	 * @param string $search content to search for
	 * @param string[] $objectIds Limit the search by object ids
	 * @param string $verb Limit the verb of the comment
	 * @return list<IComment>
	 */
	public function searchForObjectsWithFilters(string $search, array $objectIds, string $verb, ?\DateTimeImmutable $since, ?\DateTimeImmutable $until, ?string $actorType, ?string $actorId, int $offset, int $limit = 50): array {
		return $this->commentsManager->searchForObjectsWithFilters($search, 'chat', $objectIds, $verb, $since, $until, $actorType, $actorId, $offset, $limit);
	}

	public function addConversationNotify(array $results, string $search, Room $room, Participant $participant): array {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			return $results;
		}
		$attendee = $participant->getAttendee();
		if ($attendee->getActorType() === Attendee::ACTOR_USERS) {
			$roomDisplayName = $room->getDisplayName($attendee->getActorId());
		} else {
			$roomDisplayName = $room->getDisplayName('');
		}
		if ($search === '' || $this->searchIsPartOfConversationNameOrAtAll($search, $roomDisplayName)) {
			array_unshift($results, [
				'id' => 'all',
				'label' => $roomDisplayName,
				'source' => 'calls',
				'mentionId' => 'all',
			]);
		}
		return $results;
	}

	private function searchIsPartOfConversationNameOrAtAll(string $search, string $roomDisplayName): bool {
		if (stripos($roomDisplayName, $search) !== false) {
			return true;
		}
		/**
		 * @psalm-suppress InvalidLiteralArgument
		 */
		if (str_starts_with('all', $search)) {
			return true;
		}
		/**
		 * @psalm-suppress InvalidLiteralArgument
		 */
		if (str_starts_with('here', $search)) {
			return true;
		}
		return false;
	}

	public function deleteExpiredMessages(): void {
		$this->commentsManager->deleteCommentsExpiredAtObject('chat', '');
	}

	public function fileOfMessageExists(string $message): bool {
		$parameters = $this->getParametersFromMessage($message);
		try {
			$this->shareProvider->getShareById($parameters['share']);
		} catch (ShareNotFound $e) {
			return false;
		}
		return true;
	}

	public function isSharedFile(string $message): bool {
		$parameters = $this->getParametersFromMessage($message);
		return !empty($parameters['share']);
	}

	protected function getParametersFromMessage(string $message): array {
		$data = json_decode($message, true);
		if (!\is_array($data) || !array_key_exists('parameters', $data) || !is_array($data['parameters'])) {
			return [];
		}
		return $data['parameters'];
	}

	/**
	 * When receive a list of comments, filter the comments,
	 * removing all that have shares of file that no more exists
	 *
	 * @param IComment[] $comments
	 * @return IComment[]
	 */
	public function filterCommentsWithNonExistingFiles(array $comments): array {
		return array_filter($comments, function (IComment $comment) {
			if ($this->isSharedFile($comment->getMessage())) {
				if (!$this->fileOfMessageExists($comment->getMessage())) {
					return false;
				}
			}
			return true;
		});
	}
}
