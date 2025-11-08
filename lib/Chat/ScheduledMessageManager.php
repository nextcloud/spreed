<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat;

use DateInterval;
use OC\Comments\Comment;
use OC\Memcache\ArrayCache;
use OC\Memcache\NullCache;
use OCA\Talk\BackgroundJob\UnpinMessage;
use OCA\Talk\CachePrefix;
use OCA\Talk\Events\BeforeChatMessageSentEvent;
use OCA\Talk\Events\BeforeSystemMessageSentEvent;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Exceptions\InvalidRoomException;
use OCA\Talk\Exceptions\MessagingNotAllowedException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\Poll;
use OCA\Talk\Model\ScheduledMessage;
use OCA\Talk\Model\ScheduledMessageMapper;
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
use OCP\BackgroundJob\IJobList;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Comments\IComment;
use OCP\Comments\MessageTooLongException;
use OCP\Comments\NotFoundException;
use OCP\DB\Exception;
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
class ScheduledMessageManager {
	public const MAX_CHAT_LENGTH = 32000;

	public function __construct(
		private IEventDispatcher $dispatcher,
		private ScheduledMessageMapper $scheduledMessageMapper,
		private INotificationManager $notificationManager,
		private IManager $shareManager,
		private RoomShareProvider $shareProvider,
		private ParticipantService $participantService,
		private RoomService $roomService,
		private PollService $pollService,
		protected ThreadService $threadService,
		private Notifier $notifier,
		protected ITimeFactory $timeFactory,
		protected AttachmentService $attachmentService,
		protected IReferenceManager $referenceManager,
		protected ILimiter $rateLimiter,
		protected IRequest $request,
		protected IJobList $jobList,
		protected IL10N $l,
		protected LoggerInterface $logger,
	) {
	}

	public function scheduleMessage(
		Room $chat,
		?Participant $participant,
		string $actorType,
		string $actorId,
		string $message,
		string $messageType,
		string $messageParameters = null,
		?IComment $replyTo,
		int $threadId,
		\DateTime $sendAt,
		\DateTime $creationDateTime,
		bool $silent = false,
	): ScheduledMessage {
		if ($chat->isFederatedConversation()) {
			$e = new MessagingNotAllowedException();
			$this->logger->error('Attempt to post scheduled message into proxy conversation', ['exception' => $e]);
			throw $e;
		}

		$scheduledMessage = new ScheduledMessage();
		$scheduledMessage->setRoomToken($chat->getToken());
		$scheduledMessage->setCreatedAt($creationDateTime);
		$scheduledMessage->setActorId($actorId);
		$scheduledMessage->setActorType($actorType);
		$scheduledMessage->setSendAt($sendAt);
		$scheduledMessage->setMessage($message, self::MAX_CHAT_LENGTH);
		$scheduledMessage->setMessageType($messageType);
		$scheduledMessage->setMessageParameters($messageParameters);
		$scheduledMessage->setThreadId($threadId);

		if ($replyTo instanceof IComment) {
			$scheduledMessage->setParentId((int)$replyTo->getId());
			$threadId = (int)$replyTo->getTopmostParentId() ?: (int)$replyTo->getId();
			$threadId = $this->threadService->validateThread($chat->getId(), $threadId) ? $threadId : Thread::THREAD_NONE;
			$scheduledMessage->setThreadId($threadId);
		} elseif ($threadId !== Thread::THREAD_NONE && $threadId !== Thread::THREAD_CREATE) {
			$scheduledMessage->setParentId($threadId);
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
		$scheduledMessage->setMetaData(json_encode($metadata, JSON_THROW_ON_ERROR));

		try {
			$this->scheduledMessageMapper->insert($scheduledMessage);
		} catch (Exception $e) {
		}

		return $scheduledMessage;
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

		if ($comment->getVerb() === ScheduledMessageManager::VERB_OBJECT_SHARED) {
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
}
