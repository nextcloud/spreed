<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat;

use DateInterval;
use OC\Memcache\ArrayCache;
use OC\Memcache\NullCache;
use OCA\Talk\CachePrefix;
use OCA\Talk\Events\BeforeChatMessageSentEvent;
use OCA\Talk\Events\BeforeSystemMessageSentEvent;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Exceptions\InvalidRoomException;
use OCA\Talk\Exceptions\MessagingNotAllowedException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\Poll;
use OCA\Talk\Model\Thread;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\PollService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\ThreadService;
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
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Security\RateLimiting\IRateLimitExceededException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Basic polling chat manager.
 *
 * sendMessage() saves a comment using the ICommentsManager, while
 * receiveMessages() tries to read comments from ICommentsManager (with a little
 * wait between reads) until comments are found or until the timeout expires.
 *
 * When a message is saved the mentioned users are notified as needed, and
 * pending notifications are removed if the messages are deleted.
 *
 * @psalm-import-type TalkChatMentionSuggestion from ResponseDefinitions
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

	/**
	 * Last read message ID of -1 is set on the attendee table as default.
	 * The real value is inserted on user request after the migration from
	 * `comments_read_markers` to `talk_attendees` with @see Version7000Date20190724121136
	 *
	 * @since 21.0.0 (But -1 was used in the database since 7.0.0)
	 */
	public const UNREAD_MIGRATION = -1;

	/**
	 * Frontend and Desktop don't get chat context with ID 0,
	 * so we collectively tested and decided that -2 should be used instead,
	 * when marking the first message in a chat as unread.
	 *
	 * @since 21.0.0
	 */
	public const UNREAD_FIRST_MESSAGE = -2;

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
		protected ThreadService $threadService,
		private Notifier $notifier,
		ICacheFactory $cacheFactory,
		protected ITimeFactory $timeFactory,
		protected AttachmentService $attachmentService,
		protected IReferenceManager $referenceManager,
		protected ILimiter $rateLimiter,
		protected IRequest $request,
		protected IL10N $l,
		protected LoggerInterface $logger,
	) {
		$this->cache = $cacheFactory->createDistributed(CachePrefix::CHAT_LAST_MESSAGE_ID);
		$this->unreadCountCache = $cacheFactory->createDistributed(CachePrefix::CHAT_UNREAD_COUNT);
	}

	/**
	 * Sends a new message to the given chat.
	 *
	 * @param bool $shouldSkipLastMessageUpdate If multiple messages will be posted
	 *                                          (e.g. when adding multiple users to a room) we can skip the last
	 *                                          message and last activity update until the last entry was created
	 *                                          and then update with those values.
	 *                                          This will replace O(n) with 1 database update.
	 * @throws MessagingNotAllowedException
	 */
	public function addSystemMessage(
		Room $chat,
		?Participant $participant,
		string $actorType,
		string $actorId,
		string $message,
		\DateTime $creationDateTime,
		bool $sendNotifications,
		?string $referenceId = null,
		?IComment $replyTo = null,
		bool $shouldSkipLastMessageUpdate = false,
		bool $silent = false,
		int $threadId = 0,
	): IComment {
		if ($chat->isFederatedConversation()) {
			$e = new MessagingNotAllowedException();
			$this->logger->error('Attempt to post system message into proxy conversation', ['exception' => $e]);
			throw $e;
		}

		$comment = $this->commentsManager->create($actorType, $actorId, 'chat', (string)$chat->getId());
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
		} elseif ($threadId !== 0) {
			$comment->setParentId((string)$threadId);
		}

		$messageDecoded = json_decode($message, true);
		$messageType = $messageDecoded['message'] ?? '';

		if ($messageType === 'object_shared' || $messageType === 'file_shared') {
			$comment->setVerb(self::VERB_OBJECT_SHARED);
		} else {
			$comment->setVerb(self::VERB_SYSTEM);
		}

		$metadata = [];
		if ($silent) {
			$metadata[Message::METADATA_SILENT] = true;
		}
		if ($chat->getMentionPermissions() === Room::MENTION_PERMISSIONS_EVERYONE || $participant?->hasModeratorPermissions()) {
			$metadata[Message::METADATA_CAN_MENTION_ALL] = true;
		}
		$comment->setMetaData($metadata);

		$this->setMessageExpiration($chat, $comment);

		$shouldFlush = $this->notificationManager->defer();
		$threadId = 0;

		$event = new BeforeSystemMessageSentEvent($chat, $comment, silent: $silent, parent: $replyTo, skipLastActivityUpdate: $shouldSkipLastMessageUpdate);
		$this->dispatcher->dispatchTyped($event);
		try {
			$this->commentsManager->save($comment);
			$threadId = (int)$comment->getTopmostParentId();

			if (!$shouldSkipLastMessageUpdate) {
				// Update last_message
				$this->roomService->setLastMessage($chat, $comment);
				$this->unreadCountCache->clear($chat->getId() . '-');

				if ($threadId !== 0) {
					$isThread = $this->threadService->updateLastMessageInfoAfterReply($threadId, (int)$comment->getId(), $chat->getId());
					if ($isThread && $actorType === Attendee::ACTOR_USERS) {
						try {
							// Add to subscribed threads list
							$participant = $this->participantService->getParticipant($chat, $actorId);
							$this->threadService->ensureIsThreadAttendee($participant->getAttendee(), $threadId);
						} catch (ParticipantNotFoundException) {
						}
					} elseif (!$isThread) {
						$threadId = 0;
					}
				}
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
						$alreadyNotifiedUsers = $this->notifier->notifyReplyToAuthor($chat, $comment, $replyTo, $silent, $threadId);
						if ($replyTo->getActorType() === Attendee::ACTOR_USERS) {
							$usersDirectlyMentioned[] = $replyTo->getActorId();
						} elseif ($replyTo->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
							$federatedUsersDirectlyMentioned[] = $replyTo->getActorId();
						}
					}
				}

				$alreadyNotifiedUsers = $this->notifier->notifyMentionedUsers($chat, $captionComment ?? $comment, $alreadyNotifiedUsers, $silent, threadId: $threadId);
				if (!empty($alreadyNotifiedUsers)) {
					$userIds = array_column($alreadyNotifiedUsers, 'id');
					$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_USERS, $userIds, (int)$comment->getId(), $usersDirectlyMentioned);
				}
				if (!empty($federatedUsersDirectlyMentioned)) {
					$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_FEDERATED_USERS, $federatedUsersDirectlyMentioned, (int)$comment->getId(), $federatedUsersDirectlyMentioned);
				}

				$this->notifier->notifyOtherParticipant($chat, $comment, [], $silent);
			}

			if (!$shouldSkipLastMessageUpdate && $sendNotifications) {
				// Update the read-marker for the author when it is a "relevant" system message,
				// e.g. sharing an item to the chat
				try {
					$participant = $this->participantService->getParticipantByActor($chat, $actorType, $actorId);
					$this->participantService->updateLastReadMessage($participant, (int)$comment->getId());
				} catch (ParticipantNotFoundException) {
					// Participant not found => No read-marker update needed
				}
			}

			$event = new SystemMessageSentEvent($chat, $comment, silent: $silent, parent: $replyTo, skipLastActivityUpdate: $shouldSkipLastMessageUpdate);
			$this->dispatcher->dispatchTyped($event);
		} catch (NotFoundException $e) {
		}
		$this->cache->remove($chat->getToken());
		if ($threadId !== 0) {
			$this->cache->remove($chat->getToken() . '/' . $threadId);
		}

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
		$comment = $this->commentsManager->create(Attendee::ACTOR_GUESTS, Attendee::ACTOR_ID_CHANGELOG, 'chat', (string)$chat->getId());

		$comment->setMessage($message, self::MAX_CHAT_LENGTH);
		$comment->setCreationDateTime($this->timeFactory->getDateTime());
		$comment->setVerb(self::VERB_MESSAGE); // Has to be 'comment', so it counts as unread message

		$threadId = 0;
		$event = new BeforeSystemMessageSentEvent($chat, $comment);
		$this->dispatcher->dispatchTyped($event);
		try {
			$this->commentsManager->save($comment);
			$threadId = (int)$comment->getTopmostParentId();

			// Update last_message
			$this->roomService->setLastMessage($chat, $comment);
			$this->unreadCountCache->clear($chat->getId() . '-');

			$event = new SystemMessageSentEvent($chat, $comment);
			$this->dispatcher->dispatchTyped($event);
		} catch (NotFoundException $e) {
		}
		$this->cache->remove($chat->getToken());
		if ($threadId !== 0) {
			$this->cache->remove($chat->getToken() . '/' . $threadId);
		}

		return $comment;
	}

	/**
	 * Post a new message to the given chat.
	 *
	 * @param Room $chat
	 * @param string $message
	 * @return IComment
	 */
	public function postSampleMessage(Room $chat, string $message, string $replyTo): IComment {
		$comment = $this->commentsManager->create(Attendee::ACTOR_GUESTS, Attendee::ACTOR_ID_SAMPLE, 'chat', (string)$chat->getId());

		if ($replyTo) {
			$comment->setParentId($replyTo);
		}
		$comment->setMessage($message, self::MAX_CHAT_LENGTH);
		$comment->setCreationDateTime($this->timeFactory->getDateTime());
		$comment->setVerb(self::VERB_MESSAGE); // Has to be 'comment', so it counts as unread message
		$metaData = [
			Message::METADATA_CAN_MENTION_ALL => true,
		];
		$comment->setMetaData($metaData);
		$threadId = 0;

		$event = new BeforeSystemMessageSentEvent($chat, $comment);
		$this->dispatcher->dispatchTyped($event);
		try {
			$this->commentsManager->save($comment);
			$threadId = (int)$comment->getTopmostParentId();

			// Update last_message
			$this->roomService->setLastMessage($chat, $comment);
			$this->unreadCountCache->clear($chat->getId() . '-');

			$event = new SystemMessageSentEvent($chat, $comment);
			$this->dispatcher->dispatchTyped($event);
		} catch (NotFoundException $e) {
		}
		$this->cache->remove($chat->getToken());
		if ($threadId !== 0) {
			$this->cache->remove($chat->getToken() . '/' . $threadId);
		}

		return $comment;
	}

	/**
	 * Sends a new message to the given chat.
	 *
	 * @throws IRateLimitExceededException Only when $rateLimitGuestMentions is true and the author is a guest participant
	 * @throws MessageTooLongException
	 * @throws MessagingNotAllowedException
	 */
	public function sendMessage(
		Room $chat,
		?Participant $participant,
		string $actorType,
		string $actorId,
		string $message,
		\DateTime $creationDateTime,
		?IComment $replyTo = null,
		string $referenceId = '',
		bool $silent = false,
		bool $rateLimitGuestMentions = true,
		int $threadId = 0,
	): IComment {
		if ($chat->isFederatedConversation()) {
			$e = new MessagingNotAllowedException();
			$this->logger->error('Attempt to post system message into proxy conversation', ['exception' => $e]);
			throw $e;
		}

		$comment = $this->commentsManager->create($actorType, $actorId, 'chat', (string)$chat->getId());
		$comment->setMessage($message, self::MAX_CHAT_LENGTH);
		$comment->setCreationDateTime($creationDateTime);
		// A verb ('comment', 'like'...) must be provided to be able to save a
		// comment
		$comment->setVerb(self::VERB_MESSAGE);

		if ($replyTo instanceof IComment) {
			$comment->setParentId($replyTo->getId());
			$threadId = (int)$replyTo->getTopmostParentId() ?: (int)$replyTo->getId();
			$threadId = $this->threadService->validateThread($chat->getId(), $threadId) ? $threadId : Thread::THREAD_NONE;
		} elseif ($threadId !== Thread::THREAD_NONE && $threadId !== Thread::THREAD_CREATE) {
			$comment->setParentId((string)$threadId);
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

		$metadata = [];
		if ($silent) {
			$metadata[Message::METADATA_SILENT] = true;
		}
		if ($chat->getMentionPermissions() === Room::MENTION_PERMISSIONS_EVERYONE || $participant?->hasModeratorPermissions()) {
			$metadata[Message::METADATA_CAN_MENTION_ALL] = true;
		}
		if ($threadId !== Thread::THREAD_NONE) {
			$metadata[Message::METADATA_THREAD_ID] = $threadId;
		}
		$comment->setMetaData($metadata);

		$event = new BeforeChatMessageSentEvent($chat, $comment, $participant, $silent, $replyTo);
		$this->dispatcher->dispatchTyped($event);

		$shouldFlush = $this->notificationManager->defer();
		try {
			$this->commentsManager->save($comment);
			$messageId = (int)$comment->getId();
			if ($threadId === Thread::THREAD_CREATE) {
				$metadata[Message::METADATA_THREAD_ID] = $messageId;
				$comment->setMetaData($metadata);
				$this->commentsManager->save($comment);
			} elseif ($threadId !== Thread::THREAD_NONE) {
				$isThread = $this->threadService->updateLastMessageInfoAfterReply($threadId, $messageId, $chat->getId());
				if (!$isThread) {
					$threadId = Thread::THREAD_NONE;
				} elseif ($participant instanceof Participant) {
					// Add to subscribed threads list
					$this->threadService->ensureIsThreadAttendee($participant->getAttendee(), $threadId);
				}
			}

			if ($participant instanceof Participant) {
				$this->participantService->updateLastReadMessage($participant, $messageId);
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
				$alreadyNotifiedUsers = $this->notifier->notifyReplyToAuthor($chat, $comment, $replyTo, $silent, $threadId);
				if ($replyTo->getActorType() === Attendee::ACTOR_USERS) {
					$usersDirectlyMentioned[] = $replyTo->getActorId();
				} elseif ($replyTo->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
					$federatedUsersDirectlyMentioned[] = $replyTo->getActorId();
				}
			}

			$alreadyNotifiedUsers = $this->notifier->notifyMentionedUsers($chat, $comment, $alreadyNotifiedUsers, $silent, $participant, threadId: $threadId);
			if (!empty($alreadyNotifiedUsers)) {
				$userIds = array_column($alreadyNotifiedUsers, 'id');
				$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_USERS, $userIds, (int)$comment->getId(), $usersDirectlyMentioned);
			}
			if (!empty($federatedUsersDirectlyMentioned)) {
				$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_FEDERATED_USERS, $federatedUsersDirectlyMentioned, (int)$comment->getId(), $federatedUsersDirectlyMentioned);
			}

			// User was not mentioned, send a normal notification
			$this->notifier->notifyOtherParticipant($chat, $comment, $alreadyNotifiedUsers, $silent);

			$event = new ChatMessageSentEvent($chat, $comment, $participant, $silent, $replyTo);
			$this->dispatcher->dispatchTyped($event);
		} catch (NotFoundException $e) {
		}
		$this->cache->remove($chat->getToken());
		if ($threadId !== Thread::THREAD_NONE) {
			$this->cache->remove($chat->getToken() . '/' . $threadId);
		}
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

		if (!$participant->hasModeratorPermissions(false)
			&& !($attendee->getActorType() === Attendee::ACTOR_USERS && $attendee->getActorId() === $share->getShareOwner())) {
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
				[(string)$room->getId()],
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
		if (isset($metaData[Message::METADATA_LAST_EDITED_BY_TYPE])) {
			unset(
				$metaData[Message::METADATA_LAST_EDITED_BY_TYPE],
				$metaData[Message::METADATA_LAST_EDITED_BY_ID],
				$metaData[Message::METADATA_LAST_EDITED_TIME],
			);
			$comment->setMetaData($metaData);
		}

		$this->commentsManager->save($comment);

		$this->attachmentService->deleteAttachmentByMessageId((int)$comment->getId());

		$this->referenceManager->invalidateCache($chat->getToken());

		$this->unreadCountCache->clear($chat->getId() . '-');

		return $this->addSystemMessage(
			$chat,
			$participant,
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
		$metaData[Message::METADATA_LAST_EDITED_BY_TYPE] = $participant->getAttendee()->getActorType();
		$metaData[Message::METADATA_LAST_EDITED_BY_ID] = $participant->getAttendee()->getActorId();
		$metaData[Message::METADATA_LAST_EDITED_TIME] = $editTime->getTimestamp();
		$comment->setMetaData($metaData);

		$wasSilent = $metaData[Message::METADATA_SILENT] ?? false;

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
					$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_USERS, $userIds, (int)$comment->getId(), $addedUsersDirectMentioned);
				}
				if (!empty($federatedUsersDirectlyMentionedAfter)) {
					$this->participantService->markUsersAsMentioned($chat, Attendee::ACTOR_FEDERATED_USERS, $federatedUsersDirectlyMentionedAfter, (int)$comment->getId(), $federatedUsersDirectlyMentionedAfter);
				}
			}
		}

		return $this->addSystemMessage(
			$chat,
			$participant,
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
		$this->commentsManager->deleteCommentsAtObject('chat', (string)$chat->getId());

		$this->shareProvider->deleteInRoom($chat->getToken());

		$this->notifier->removePendingNotificationsForRoom($chat, true);

		$this->participantService->resetChatDetails($chat);

		$this->pollService->deleteByRoomId($chat->getId());
		$this->threadService->deleteByRoom($chat);

		return $this->addSystemMessage(
			$chat,
			null,
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

		if ($comment->getObjectType() !== 'chat' || $comment->getObjectId() !== (string)$chat->getId()) {
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
		if ($chat->isFederatedConversation()) {
			throw new InvalidRoomException('Can not call ChatManager::getComment() with a federated chat.');
		}

		$comment = $this->commentsManager->get($messageId);

		if ($comment->getObjectType() !== 'chat' || $comment->getObjectId() !== (string)$chat->getId()) {
			throw new NotFoundException('Message not found in the right context');
		}

		return $comment;
	}

	/**
	 * @param Room $chat
	 * @param string $messageId
	 * @return IComment
	 * @throws NotFoundException
	 */
	public function getTopMostComment(Room $chat, string $messageId): IComment {
		if ($chat->isFederatedConversation()) {
			throw new InvalidRoomException('Can not call ChatManager::getTopMostComment() with a federated chat.');
		}

		$comment = $this->commentsManager->get($messageId);

		if ($comment->getObjectType() !== 'chat' || $comment->getObjectId() !== (string)$chat->getId()) {
			throw new NotFoundException('Message not found in the right context');
		}

		if ($comment->getTopmostParentId() !== '0') {
			$comment = $this->getComment($chat, $comment->getTopmostParentId());
		}

		return $comment;
	}

	public function getLastReadMessageFromLegacy(Room $chat, IUser $user): int {
		$marker = $this->commentsManager->getReadMark('chat', (string)$chat->getId(), $user);
		if ($marker === null) {
			return 0;
		}

		return $this->commentsManager->getLastCommentBeforeDate('chat', (string)$chat->getId(), $marker, self::VERB_MESSAGE);
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
			$unreadCount = $this->commentsManager->getNumberOfCommentsWithVerbsForObjectSinceComment('chat', (string)$chat->getId(), $lastReadMessage, [self::VERB_MESSAGE, self::VERB_OBJECT_SHARED]);
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
	 *                    creation date and message are relevant), or an empty array if the
	 *                    timeout expired.
	 */
	public function getHistory(Room $chat, int $offset, int $limit, bool $includeLastKnown, int $threadId = 0): array {
		return $this->commentsManager->getCommentsWithVerbForObjectSinceComment(
			'chat',
			(string)$chat->getId(),
			[],
			$offset,
			'desc',
			$limit,
			$includeLastKnown,
			$threadId !== 0 ? (string)$threadId : '',
		);
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
			(string)$chat->getId(),
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
	 * @param int $offset Last known message id
	 * @return IComment[] the messages found (only the id, actor type and id,
	 *                    creation date and message are relevant), or an empty array if the
	 *                    timeout expired.
	 */
	public function waitForNewMessages(Room $chat, int $offset, int $limit, int $timeout, ?IUser $user, bool $includeLastKnown, bool $markNotificationsAsRead = true, int $threadId = 0): array {
		if ($markNotificationsAsRead && $user instanceof IUser) {
			$this->notifier->markMentionNotificationsRead($chat, $user->getUID());
		}

		if ($this->cache instanceof NullCache
			|| $this->cache instanceof ArrayCache) {
			return $this->waitForNewMessagesWithDatabase($chat, $offset, $limit, $timeout, $includeLastKnown, $threadId);
		}

		return $this->waitForNewMessagesWithCache($chat, $offset, $limit, $timeout, $includeLastKnown, $threadId);
	}

	/**
	 * Check the cache until we found new messages, or the timeout was reached
	 *
	 * @return IComment[]
	 */
	protected function waitForNewMessagesWithCache(Room $chat, int $offset, int $limit, int $timeout, bool $includeLastKnown, int $threadId): array {
		$elapsedTime = 0;

		$comments = $this->checkCacheOrDatabase($chat, $offset, $limit, $includeLastKnown, $threadId);

		while (empty($comments) && $elapsedTime < $timeout) {
			$this->connection->close();
			sleep(1);
			$elapsedTime++;

			$comments = $this->checkCacheOrDatabase($chat, $offset, $limit, $includeLastKnown, $threadId);
		}

		return $comments;
	}

	/**
	 * Check the cache for the last message id or check the database for updates
	 *
	 * @return IComment[]
	 */
	protected function checkCacheOrDatabase(Room $chat, int $offset, int $limit, bool $includeLastKnown, int $threadId): array {
		$cacheKey = $chat->getToken();
		if ($threadId !== 0) {
			$cacheKey .= '/' . $threadId;
		}
		$cachedId = $this->cache->get($cacheKey);
		if ($offset === $cachedId) {
			// Cache hit, nothing new ¯\_(ツ)_/¯
			return [];
		}

		// Load data from the database
		$comments = $this->commentsManager->getCommentsWithVerbForObjectSinceComment(
			'chat',
			(string)$chat->getId(),
			[],
			$offset,
			'asc',
			$limit,
			$includeLastKnown,
			$threadId !== 0 ? (string)$threadId : '',
		);

		if (empty($comments)) {
			// We only write the cache when there were no new comments,
			// otherwise it could happen that this is not the last message,
			// but the last within $limit
			$this->cache->set($cacheKey, $offset, 30);
			return [];
		}

		return $comments;
	}

	/**
	 * Check the database for new messages until there a new messages or we exceeded the timeout
	 *
	 * @return IComment[]
	 */
	protected function waitForNewMessagesWithDatabase(Room $chat, int $offset, int $limit, int $timeout, bool $includeLastKnown, int $threadId): array {
		$elapsedTime = 0;

		$comments = $this->commentsManager->getCommentsWithVerbForObjectSinceComment(
			'chat',
			(string)$chat->getId(),
			[],
			$offset,
			'asc',
			$limit,
			$includeLastKnown,
			$threadId !== 0 ? (string)$threadId : '',
		);

		while (empty($comments) && $elapsedTime < $timeout) {
			sleep(1);
			$elapsedTime++;

			$comments = $this->commentsManager->getCommentsWithVerbForObjectSinceComment(
				'chat',
				(string)$chat->getId(),
				[],
				$offset,
				'asc',
				$limit,
				$includeLastKnown,
				$threadId !== 0 ? (string)$threadId : '',
			);
		}

		return $comments;
	}

	/**
	 * Deletes all the messages for the given chat.
	 *
	 * @param Room $chat
	 */
	public function deleteMessages(Room $chat): void {
		$this->commentsManager->deleteCommentsAtObject('chat', (string)$chat->getId());

		$this->shareProvider->deleteInRoom($chat->getToken());

		$this->notifier->removePendingNotificationsForRoom($chat);

		$this->attachmentService->deleteAttachmentsForRoom($chat);

		$this->pollService->deleteByRoomId($chat->getId());
		$this->threadService->deleteByRoom($chat);
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
	 * @return array<int, IComment> Key is the message id
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
	 * @param string[] $verbs Limit the verb of the comment
	 * @return list<IComment>
	 */
	public function searchForObjectsWithFilters(string $search, array $objectIds, array $verbs, ?\DateTimeImmutable $since, ?\DateTimeImmutable $until, ?string $actorType, ?string $actorId, int $offset, int $limit = 50): array {
		return $this->commentsManager->searchForObjectsWithFilters($search, 'chat', $objectIds, $verbs, $since, $until, $actorType, $actorId, $offset, $limit);
	}

	/**
	 * @param list<TalkChatMentionSuggestion> $results
	 * @return list<TalkChatMentionSuggestion>
	 */
	public function addConversationNotify(array $results, string $search, Room $room, Participant $participant): array {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			return $results;
		}
		if ($room->getMentionPermissions() === Room::MENTION_PERMISSIONS_MODERATORS && !$participant->hasModeratorPermissions()) {
			return $results;
		}

		$attendee = $participant->getAttendee();
		if ($attendee->getActorType() === Attendee::ACTOR_USERS) {
			$roomDisplayName = $room->getDisplayName($attendee->getActorId());
		} else {
			$roomDisplayName = $room->getDisplayName('');
		}
		if ($search === '' || $this->searchIsPartOfConversationNameOrAtAll($search, $roomDisplayName)) {
			$participantCount = $this->participantService->getNumberOfUsers($room);

			$atAllResult = [
				'id' => 'all',
				'label' => $roomDisplayName,
				'source' => 'calls',
				'mentionId' => 'all',
			];

			if ($participantCount > 1) {
				// TRANSLATORS The string will only be used with more than 1 participant, so you can keep the "All" in all plural forms
				$atAllResult['details'] = $this->l->n('All %n participant', 'All %n participants', $participantCount);
			}

			$results[] = $atAllResult;
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
