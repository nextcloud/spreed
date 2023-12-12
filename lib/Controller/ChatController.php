<?php

declare(strict_types=1);
/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @author Kate Döen <kate.doeen@nextcloud.com>
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

namespace OCA\Talk\Controller;

use OCA\Talk\Chat\AutoComplete\SearchPlugin;
use OCA\Talk\Chat\AutoComplete\Sorter;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\GuestManager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Middleware\Attribute\RequireLoggedInParticipant;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Middleware\Attribute\RequirePermission;
use OCA\Talk\Middleware\Attribute\RequireReadWriteConversation;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ReminderService;
use OCA\Talk\Service\SessionService;
use OCA\Talk\Share\Helper\FilesMetadataCache;
use OCA\Talk\Share\RoomShareProvider;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Comments\IComment;
use OCP\Comments\MessageTooLongException;
use OCP\Comments\NotFoundException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\RichObjectStrings\InvalidObjectExeption;
use OCP\RichObjectStrings\IValidator;
use OCP\Security\ITrustedDomainHelper;
use OCP\Security\RateLimiting\IRateLimitExceededException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use OCP\User\Events\UserLiveStatusEvent;
use OCP\UserStatus\IManager as IUserStatusManager;
use OCP\UserStatus\IUserStatus;

/**
 * @psalm-import-type TalkChatMentionSuggestion from ResponseDefinitions
 * @psalm-import-type TalkChatMessage from ResponseDefinitions
 * @psalm-import-type TalkChatMessageWithParent from ResponseDefinitions
 * @psalm-import-type TalkChatReminder from ResponseDefinitions
 */
class ChatController extends AEnvironmentAwareController {
	/** @var string[] */
	protected array $guestNames;

	public function __construct(
		string $appName,
		private ?string $userId,
		IRequest $request,
		private IUserManager $userManager,
		private IAppManager $appManager,
		private ChatManager $chatManager,
		private ReactionManager $reactionManager,
		private ParticipantService $participantService,
		private SessionService $sessionService,
		protected AttachmentService $attachmentService,
		protected avatarService $avatarService,
		protected ReminderService $reminderService,
		private GuestManager $guestManager,
		private MessageParser $messageParser,
		protected RoomShareProvider $shareProvider,
		protected FilesMetadataCache $filesMetadataCache,
		private IManager $autoCompleteManager,
		private IUserStatusManager $statusManager,
		protected MatterbridgeManager $matterbridgeManager,
		private SearchPlugin $searchPlugin,
		private ISearchResult $searchResult,
		protected ITimeFactory $timeFactory,
		protected IEventDispatcher $eventDispatcher,
		protected IValidator $richObjectValidator,
		protected ITrustedDomainHelper $trustedDomainHelper,
		private IL10N $l,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return list{0: Attendee::ACTOR_*, 1: string}
	 */
	protected function getActorInfo(string $actorDisplayName = ''): array {
		$remoteCloudId = $this->getRemoteAccessCloudId();
		if ($remoteCloudId !== null) {
			return [Attendee::ACTOR_FEDERATED_USERS, $remoteCloudId];
		}

		if ($this->userId === null) {
			if ($actorDisplayName) {
				$this->guestManager->updateName($this->room, $this->participant, $actorDisplayName);
			}
			return [Attendee::ACTOR_GUESTS, $this->participant->getAttendee()->getActorId()];
		}

		if ($this->userId === MatterbridgeManager::BRIDGE_BOT_USERID && $actorDisplayName) {
			return [Attendee::ACTOR_BRIDGED, str_replace(['/', '"'], '', $actorDisplayName)];
		}

		return [Attendee::ACTOR_USERS, $this->userId];
	}

	/**
	 * @return DataResponse<Http::STATUS_CREATED, ?TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>
	 */
	protected function parseCommentToResponse(IComment $comment, Message $parentMessage = null): DataResponse {
		$chatMessage = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
		$this->messageParser->parseMessage($chatMessage);

		if (!$chatMessage->getVisibility()) {
			$headers = [];
			if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
				$headers = ['X-Chat-Last-Common-Read' => (string) $this->chatManager->getLastCommonReadMessage($this->room)];
			}
			return new DataResponse(null, Http::STATUS_CREATED, $headers);
		}

		$data = $chatMessage->toArray($this->getResponseFormat());
		if ($parentMessage instanceof Message) {
			$data['parent'] = $parentMessage->toArray($this->getResponseFormat());
		}

		$headers = [];
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$headers = ['X-Chat-Last-Common-Read' => (string) $this->chatManager->getLastCommonReadMessage($this->room)];
		}
		return new DataResponse($data, Http::STATUS_CREATED, $headers);
	}

	/**
	 * Sends a new chat message to the given room
	 *
	 * The author and timestamp are automatically set to the current user/guest
	 * and time.
	 *
	 * @param string $message the message to send
	 * @param string $actorDisplayName for guests
	 * @param string $referenceId for the message to be able to later identify it again
	 * @param int $replyTo Parent id which this message is a reply to
	 * @psalm-param non-negative-int $replyTo
	 * @param bool $silent If sent silent the chat message will not create any notifications
	 * @return DataResponse<Http::STATUS_CREATED, ?TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_REQUEST_ENTITY_TOO_LARGE|Http::STATUS_TOO_MANY_REQUESTS, array<empty>, array{}>
	 *
	 * 201: Message sent successfully
	 * 400: Sending message is not possible
	 * 404: Actor not found
	 * 413: Message too long
	 * 429: Mention rate limit exceeded (guests only)
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	public function sendMessage(string $message, string $actorDisplayName = '', string $referenceId = '', int $replyTo = 0, bool $silent = false): DataResponse {
		if (trim($message) === '') {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		[$actorType, $actorId] = $this->getActorInfo($actorDisplayName);
		if (!$actorId) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$parent = $parentMessage = null;
		if ($replyTo !== 0) {
			try {
				$parent = $this->chatManager->getParentComment($this->room, (string) $replyTo);
			} catch (NotFoundException $e) {
				// Someone is trying to reply cross-rooms or to a non-existing message
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}

			$parentMessage = $this->messageParser->createMessage($this->room, $this->participant, $parent, $this->l);
			$this->messageParser->parseMessage($parentMessage);
			if (!$parentMessage->isReplyable()) {
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}
		}

		$this->participantService->ensureOneToOneRoomIsFilled($this->room);
		$creationDateTime = $this->timeFactory->getDateTime('now', new \DateTimeZone('UTC'));

		try {
			$comment = $this->chatManager->sendMessage($this->room, $this->participant, $actorType, $actorId, $message, $creationDateTime, $parent, $referenceId, $silent);
		} catch (MessageTooLongException) {
			return new DataResponse([], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (IRateLimitExceededException) {
			return new DataResponse([], Http::STATUS_TOO_MANY_REQUESTS);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return $this->parseCommentToResponse($comment, $parentMessage);
	}

	/**
	 * Sends a rich-object to the given room
	 *
	 * The author and timestamp are automatically set to the current user/guest
	 * and time.
	 *
	 * @param string $objectType Type of the object
	 * @param string $objectId ID of the object
	 * @param string $metaData Additional metadata
	 * @param string $actorDisplayName Guest name
	 * @param string $referenceId Reference ID
	 * @return DataResponse<Http::STATUS_CREATED, ?TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_REQUEST_ENTITY_TOO_LARGE, array<empty>, array{}>
	 *
	 * 201: Object shared successfully
	 * 400: Sharing object is not possible
	 * 404: Actor not found
	 * 413: Message too long
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	public function shareObjectToChat(string $objectType, string $objectId, string $metaData = '', string $actorDisplayName = '', string $referenceId = ''): DataResponse {
		[$actorType, $actorId] = $this->getActorInfo($actorDisplayName);
		if (!$actorId) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$data = $metaData !== '' ? json_decode($metaData, true) : [];
		if (!is_array($data)) {
			$data = [];
		}
		$data['type'] = $objectType;
		$data['id'] = $objectId;
		$data['icon-url'] = $this->avatarService->getAvatarUrl($this->room);

		if (isset($data['link']) && !$this->trustedDomainHelper->isTrustedUrl($data['link'])) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->richObjectValidator->validate('{object}', ['object' => $data]);
		} catch (InvalidObjectExeption $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($data['type'] === 'geo-location'
			&& !preg_match(ChatManager::GEO_LOCATION_VALIDATOR, $data['id'])) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->participantService->ensureOneToOneRoomIsFilled($this->room);
		$creationDateTime = $this->timeFactory->getDateTime('now', new \DateTimeZone('UTC'));

		$message = json_encode([
			'message' => 'object_shared',
			'parameters' => [
				'objectType' => $objectType,
				'objectId' => $objectId,
				'metaData' => $data,
			],
		]);

		try {
			$comment = $this->chatManager->addSystemMessage($this->room, $actorType, $actorId, $message, $creationDateTime, true, $referenceId);
		} catch (MessageTooLongException $e) {
			return new DataResponse([], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return $this->parseCommentToResponse($comment);
	}

	/*
	 * Gather share IDs from the comments and preload share definitions
	 * and files metadata to avoid separate database query for each
	 * individual share/node later on.
	 *
	 * @param IComment[] $comments
	 */
	protected function preloadShares(array $comments): void {
		// Scan messages for share IDs
		$shareIds = [];
		foreach ($comments as $comment) {
			$verb = $comment->getVerb();
			if ($verb === 'object_shared') {
				$message = $comment->getMessage();
				$data = json_decode($message, true);
				if (isset($data['parameters']['share'])) {
					$shareIds[] = $data['parameters']['share'];
				}
			}
		}
		if (!empty($shareIds)) {
			// Retrieved Share objects will be cached by
			// the RoomShareProvider and returned from the cache to
			// the Parser\SystemMessage without additional database queries.
			$shares = $this->shareProvider->getSharesByIds($shareIds);

			// Preload files metadata as well
			$fileIds = array_filter(array_map(static fn (IShare $share) => $share->getNodeId(), $shares));
			$this->filesMetadataCache->preloadMetadata($fileIds);
		}
	}

	/**
	 * Receives chat messages from the given room
	 *
	 * - Receiving the history ($lookIntoFuture=0):
	 *   The next $limit messages after $lastKnownMessageId will be returned.
	 *   The new $lastKnownMessageId for the follow up query is available as
	 *   `X-Chat-Last-Given` header.
	 *
	 * - Looking into the future ($lookIntoFuture=1):
	 *   If there are currently no messages the response will not be sent
	 *   immediately. Instead, HTTP connection will be kept open waiting for new
	 *   messages to arrive and, when they do, then the response will be sent. The
	 *   connection will not be kept open indefinitely, though; the number of
	 *   seconds to wait for new messages to arrive can be set using the timeout
	 *   parameter; the default timeout is 30 seconds, maximum timeout is 60
	 *   seconds. If the timeout ends a successful but empty response will be
	 *   sent.
	 *   If messages have been returned (status=200) the new $lastKnownMessageId
	 *   for the follow up query is available as `X-Chat-Last-Given` header.
	 *
	 * The limit specifies the maximum number of messages that will be returned,
	 * although the actual number of returned messages could be lower if some
	 * messages are not visible to the participant. Note that if none of the
	 * messages are visible to the participant the returned number of messages
	 * will be 0, yet the status will still be 200. Also note that
	 * `X-Chat-Last-Given` may reference a message not visible and thus not
	 * returned, but it should be used nevertheless as the $lastKnownMessageId
	 * for the follow-up query.
	 *
	 * @param 0|1 $lookIntoFuture Polling for new messages (1) or getting the history of the chat (0)
	 * @param int $limit Number of chat messages to receive (100 by default, 200 at most)
	 * @param int $lastKnownMessageId The last known message (serves as offset)
	 * @psalm-param non-negative-int $lastKnownMessageId
	 * @param int $lastCommonReadId The last known common read message
	 *                              (so the response is 200 instead of 304 when
	 *                              it changes even when there are no messages)
	 * @psalm-param non-negative-int $lastCommonReadId
	 * @param 0|1|2|3|4|5|6|7|8|9|10|11|12|13|14|15|16|17|18|19|20|21|22|23|24|25|26|27|28|29|30 $timeout Number of seconds to wait for new messages (30 by default, 30 at most)
	 * @psalm-param int<0, 30> $timeout
	 * @param 0|1 $setReadMarker Automatically set the last read marker when 1,
	 *                           if your client does this itself via chat/{token}/read set to 0
	 * @param 0|1 $includeLastKnown Include the $lastKnownMessageId in the messages when 1 (default 0)
	 * @param 0|1 $noStatusUpdate When the user status should not be automatically set to online set to 1 (default 0)
	 * @param 0|1 $markNotificationsAsRead Set to 0 when notifications should not be marked as read (default 1)
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_MODIFIED, TalkChatMessageWithParent[], array{X-Chat-Last-Common-Read?: numeric-string, X-Chat-Last-Given?: string}>
	 *
	 * 200: Messages returned
	 * 304: No messages
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	public function receiveMessages(int $lookIntoFuture,
		int $limit = 100,
		int $lastKnownMessageId = 0,
		int $lastCommonReadId = 0,
		int $timeout = 30,
		int $setReadMarker = 1,
		int $includeLastKnown = 0,
		int $noStatusUpdate = 0,
		int $markNotificationsAsRead = 1): DataResponse {
		$limit = min(200, $limit);
		$timeout = min(30, $timeout);

		$session = $this->participant->getSession();
		if ($noStatusUpdate === 0 && $session instanceof Session) {
			// The mobile apps dont do internal signaling unless in a call
			$isMobileApp = $this->request->isUserAgent([
				IRequest::USER_AGENT_TALK_ANDROID,
				IRequest::USER_AGENT_TALK_IOS,
			]);
			if ($isMobileApp && $session->getInCall() === Participant::FLAG_DISCONNECTED) {
				$this->sessionService->updateLastPing($session, $this->timeFactory->getTime());

				if ($lookIntoFuture) {
					$attendee = $this->participant->getAttendee();
					if ($attendee->getActorType() === Attendee::ACTOR_USERS) {
						// Bump the user status again
						$event = new UserLiveStatusEvent(
							$this->userManager->get($attendee->getActorId()),
							IUserStatus::ONLINE,
							$this->timeFactory->getTime()
						);
						$this->eventDispatcher->dispatchTyped($event);
					}
				}
			}
		}

		/**
		 * Automatic last read message marking for old clients
		 * This is pretty dumb and does not give the best and native feeling
		 * you are used to from other chat apps. The clients should manually
		 * set the read marker depending on the view port of the set of messages.
		 *
		 * We are only setting it automatically here for old clients and the
		 * web UI, until it can be fixed in Vue. To not use too much broken data,
		 * we only update the read marker to the last known id, when it is higher
		 * then the current read marker.
		 */

		$attendee = $this->participant->getAttendee();
		if ($lookIntoFuture && $setReadMarker === 1 &&
			$lastKnownMessageId > $attendee->getLastReadMessage()) {
			$this->participantService->updateLastReadMessage($this->participant, $lastKnownMessageId);
		}

		$currentUser = $this->userManager->get($this->userId);
		if ($lookIntoFuture) {
			$comments = $this->chatManager->waitForNewMessages($this->room, $lastKnownMessageId, $limit, $timeout, $currentUser, (bool)$includeLastKnown, (bool)$markNotificationsAsRead);
		} else {
			$comments = $this->chatManager->getHistory($this->room, $lastKnownMessageId, $limit, (bool)$includeLastKnown);
		}

		return $this->prepareCommentsAsDataResponse($comments, $lastCommonReadId);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_MODIFIED, TalkChatMessageWithParent[], array{X-Chat-Last-Common-Read?: numeric-string, X-Chat-Last-Given?: string}>
	 */
	protected function prepareCommentsAsDataResponse(array $comments, int $lastCommonReadId = 0): DataResponse {
		if (empty($comments)) {
			if ($lastCommonReadId && $this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
				$newLastCommonRead = $this->chatManager->getLastCommonReadMessage($this->room);
				if ($lastCommonReadId !== $newLastCommonRead) {
					// Set the status code to 200 so the header is sent to the client.
					// As per "section 10.3.5 of RFC 2616" entity headers shall be
					// stripped out on 304: https://stackoverflow.com/a/17822709
					/** @var array{X-Chat-Last-Common-Read?: numeric-string, X-Chat-Last-Given?: string} $headers */
					$headers = ['X-Chat-Last-Common-Read' => (string) $newLastCommonRead];
					return new DataResponse([], Http::STATUS_OK, $headers);
				}
			}
			return new DataResponse([], Http::STATUS_NOT_MODIFIED);
		}

		$this->preloadShares($comments);

		$i = 0;
		$now = $this->timeFactory->getDateTime();
		$messages = $commentIdToIndex = $parentIds = [];
		foreach ($comments as $comment) {
			$id = (int) $comment->getId();
			$message = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
			$this->messageParser->parseMessage($message);

			$expireDate = $message->getComment()->getExpireDate();
			if ($expireDate instanceof \DateTime && $expireDate < $now) {
				$commentIdToIndex[$id] = null;
				continue;
			}

			if (!$message->getVisibility()) {
				$commentIdToIndex[$id] = null;
				continue;
			}

			if ($comment->getParentId() !== '0') {
				$parentIds[$id] = $comment->getParentId();
			}

			$messages[] = $message->toArray($this->getResponseFormat());
			$commentIdToIndex[$id] = $i;
			$i++;
		}

		/**
		 * Set the parent for reply-messages
		 */
		$loadedParents = [];
		foreach ($parentIds as $commentId => $parentId) {
			$commentKey = $commentIdToIndex[$commentId];

			// Parent is already parsed in the message list
			if (isset($commentIdToIndex[$parentId])) {
				$parentKey = $commentIdToIndex[$parentId];
				$messages[$commentKey]['parent'] = $messages[$parentKey];

				// We don't show nested parents…
				unset($messages[$commentKey]['parent']['parent']);
				continue;
			}

			// Parent was already loaded manually for another comment
			if (!empty($loadedParents[$parentId])) {
				$messages[$commentKey]['parent'] = $loadedParents[$parentId];
				continue;
			}

			// Parent was not skipped due to visibility, so we need to manually grab it.
			if (!isset($commentIdToIndex[$parentId])) {
				try {
					$comment = $this->chatManager->getParentComment($this->room, $parentId);
					$message = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
					$this->messageParser->parseMessage($message);

					if ($message->getVisibility()) {
						$loadedParents[$parentId] = $message->toArray($this->getResponseFormat());
						$messages[$commentKey]['parent'] = $loadedParents[$parentId];
						continue;
					}

					$expireDate = $message->getComment()->getExpireDate();
					if ($expireDate instanceof \DateTime && $expireDate < $now) {
						$commentIdToIndex[$id] = null;
						continue;
					}

					$loadedParents[$parentId] = [
						'id' => (int) $parentId,
						'deleted' => true,
					];
				} catch (NotFoundException $e) {
				}
			}

			// Message is not visible to the user
			$messages[$commentKey]['parent'] = [
				'id' => (int) $parentId,
				'deleted' => true,
			];
		}

		$messages = $this->loadSelfReactions($messages, $commentIdToIndex);

		$headers = [];
		$newLastKnown = end($comments);
		if ($newLastKnown instanceof IComment) {
			$headers = ['X-Chat-Last-Given' => (string) $newLastKnown->getId()];
			if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
				/**
				 * This falsely set the read marker on new messages, although you
				 * navigated away to a different chat already. So we removed this
				 * and instead update the read marker before your next waiting.
				 * So when you are still there, it will just have a wrong read
				 * marker for the time until your next request starts, while it will
				 * not update the value, when you actually left the chat already.
				 * if ($setReadMarker === 1 && $lookIntoFuture) {
				 * $this->participantService->updateLastReadMessage($this->participant, (int) $newLastKnown->getId());
				 * }
				 */
				$headers['X-Chat-Last-Common-Read'] = (string)$this->chatManager->getLastCommonReadMessage($this->room);
			}
		}

		return new DataResponse($messages, Http::STATUS_OK, $headers);
	}

	/**
	 * Get the context of a message
	 *
	 * @param int $messageId The focused message which should be in the "middle" of the returned context
	 * @psalm-param non-negative-int $messageId
	 * @param 1|2|3|4|5|6|7|8|9|10|11|12|13|14|15|16|17|18|19|20|21|22|23|24|25|26|27|28|29|30|31|32|33|34|35|36|37|38|39|40|41|42|43|44|45|46|47|48|49|50|51|52|53|54|55|56|57|58|59|60|61|62|63|64|65|66|67|68|69|70|71|72|73|74|75|76|77|78|79|80|81|82|83|84|85|86|87|88|89|90|91|92|93|94|95|96|97|98|99|100 $limit Number of chat messages to receive in both directions (50 by default, 100 at most, might return 201 messages)
	 * @psalm-param int<1, 100> $limit
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_MODIFIED, TalkChatMessageWithParent[], array{X-Chat-Last-Common-Read?: numeric-string, X-Chat-Last-Given?: string}>
	 *
	 * 200: Message context returned
	 * 304: No messages
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	public function getMessageContext(
		int $messageId,
		int $limit = 50): DataResponse {
		$limit = min(100, $limit);

		$currentUser = $this->userManager->get($this->userId);
		$commentsHistory = $this->chatManager->getHistory($this->room, $messageId, $limit, true);
		$commentsHistory = array_reverse($commentsHistory);
		$commentsFuture = $this->chatManager->waitForNewMessages($this->room, $messageId, $limit, 0, $currentUser, false);

		return $this->prepareCommentsAsDataResponse(array_merge($commentsHistory, $commentsFuture));
	}

	protected function loadSelfReactions(array $messages, array $commentIdToIndex): array {
		// Get message ids with reactions
		$messageIdsWithReactions = array_map(
			static fn (array $message) => $message['id'],
			array_filter($messages, static fn (array $message) => !empty($message['reactions']))
		);

		// Get parents with reactions
		$parentsWithReactions = array_map(
			static fn (array $message) => ['parent' => $message['parent']['id'], 'message' => $message['id']],
			array_filter($messages, static fn (array $message) => !empty($message['parent']['reactions']))
		);

		// Create a map, so we can translate the parent's $messageId to the correct child entries
		$parentMap = $parentIdsWithReactions = [];
		foreach ($parentsWithReactions as $entry) {
			$parentMap[(int) $entry['parent']] ??= [];
			$parentMap[(int) $entry['parent']][] = (int) $entry['message'];
			$parentIdsWithReactions[] = (int) $entry['parent'];
		}

		// Unique list for the query
		$idsWithReactions = array_unique(array_merge($messageIdsWithReactions, $parentIdsWithReactions));
		$reactionsById = $this->reactionManager->getReactionsByActorForMessages($this->participant, $idsWithReactions);

		// Inject the reactions self into the $messages array
		foreach ($reactionsById as $messageId => $reactions) {
			if (isset($commentIdToIndex[$messageId]) && isset($messages[$commentIdToIndex[$messageId]])) {
				$messages[$commentIdToIndex[$messageId]]['reactionsSelf'] = $reactions;
			}

			// Add the self part also to potential parent elements
			if (isset($parentMap[$messageId])) {
				foreach ($parentMap[$messageId] as $mid) {
					if (isset($messages[$commentIdToIndex[$mid]])) {
						$messages[$commentIdToIndex[$mid]]['parent']['reactionsSelf'] = $reactions;
					}
				}
			}
		}

		return $messages;
	}

	/**
	 * Delete a chat message
	 *
	 * @param int $messageId ID of the message
	 * @psalm-param non-negative-int $messageId
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_ACCEPTED, TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_METHOD_NOT_ALLOWED, array<empty>, array{}>
	 *
	 * 200: Message deleted successfully
	 * 202: Message deleted successfully, but Matterbridge is configured, so the information can be replicated elsewhere
	 * 400: Deleting message is not possible
	 * 403: Missing permissions to delete message
	 * 404: Message not found
	 * 405: Deleting this message type is not allowed
	 */
	#[NoAdminRequired]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	public function deleteMessage(int $messageId): DataResponse {
		try {
			$message = $this->chatManager->getComment($this->room, (string) $messageId);
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$attendee = $this->participant->getAttendee();
		$isOwnMessage = $message->getActorType() === $attendee->getActorType()
			&& $message->getActorId() === $attendee->getActorId();

		// Special case for if the message is a bridged message, then the message is the bridge bot's message.
		$isOwnMessage = $isOwnMessage || ($message->getActorType() === Attendee::ACTOR_BRIDGED && $attendee->getActorId() === MatterbridgeManager::BRIDGE_BOT_USERID);
		if (!$isOwnMessage
			&& (!$this->participant->hasModeratorPermissions(false)
				|| $this->room->getType() === Room::TYPE_ONE_TO_ONE
				|| $this->room->getType() === Room::TYPE_ONE_TO_ONE_FORMER)) {
			// Actor is not a moderator or not the owner of the message
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($message->getVerb() !== ChatManager::VERB_MESSAGE && $message->getVerb() !== ChatManager::VERB_OBJECT_SHARED) {
			// System message (since the message is not parsed, it has type "system")
			return new DataResponse([], Http::STATUS_METHOD_NOT_ALLOWED);
		}

		$maxDeleteAge = $this->timeFactory->getDateTime();
		$maxDeleteAge->sub(new \DateInterval('PT6H'));
		if ($message->getCreationDateTime() < $maxDeleteAge) {
			// Message is too old
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$systemMessageComment = $this->chatManager->deleteMessage(
				$this->room,
				$message,
				$this->participant,
				$this->timeFactory->getDateTime()
			);
		} catch (ShareNotFound $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$systemMessage = $this->messageParser->createMessage($this->room, $this->participant, $systemMessageComment, $this->l);
		$this->messageParser->parseMessage($systemMessage);

		$comment = $this->chatManager->getComment($this->room, (string) $messageId);
		$message = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
		$this->messageParser->parseMessage($message);

		$data = $systemMessage->toArray($this->getResponseFormat());
		$data['parent'] = $message->toArray($this->getResponseFormat());

		$bridge = $this->matterbridgeManager->getBridgeOfRoom($this->room);

		$headers = [];
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$headers = ['X-Chat-Last-Common-Read' => (string) $this->chatManager->getLastCommonReadMessage($this->room)];
		}
		return new DataResponse($data, $bridge['enabled'] ? Http::STATUS_ACCEPTED : Http::STATUS_OK, $headers);
	}

	/**
	 * Edit a chat message
	 *
	 * @param int $messageId ID of the message
	 * @psalm-param non-negative-int $messageId
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_ACCEPTED, TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_METHOD_NOT_ALLOWED, array<empty>, array{}>
	 *
	 * 200: Message edited successfully
	 * 202: Message edited successfully, but Matterbridge is configured, so the information can be replicated elsewhere
	 * 400: Editing message is not possible
	 * 403: Missing permissions to edit message
	 * 404: Message not found
	 * 405: Editing this message type is not allowed
	 */
	#[NoAdminRequired]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	public function editMessage(int $messageId): DataResponse {
	}

	/**
	 * Set a reminder for a chat message
	 *
	 * @param int $messageId ID of the message
	 * @psalm-param non-negative-int $messageId
	 * @param int $timestamp Timestamp of the reminder
	 * @psalm-param non-negative-int $timestamp
	 * @return DataResponse<Http::STATUS_CREATED, TalkChatReminder, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 201: Reminder created successfully
	 * 404: Message not found
	 */
	#[NoAdminRequired]
	#[RequireModeratorOrNoLobby]
	#[RequireLoggedInParticipant]
	#[UserRateLimit(limit: 60, period: 3600)]
	public function setReminder(int $messageId, int $timestamp): DataResponse {
		try {
			$this->chatManager->getComment($this->room, (string) $messageId);
		} catch (NotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$reminder = $this->reminderService->setReminder(
			$this->participant->getAttendee()->getActorId(),
			$this->room->getToken(),
			$messageId,
			$timestamp
		);

		return new DataResponse($reminder->jsonSerialize(), Http::STATUS_CREATED);
	}

	/**
	 * Get the reminder for a chat message
	 *
	 * @param int $messageId ID of the message
	 * @psalm-param non-negative-int $messageId
	 * @return DataResponse<Http::STATUS_OK, TalkChatReminder, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Reminder returned
	 * 404: No reminder found
	 * 404: Message not found
	 */
	#[NoAdminRequired]
	#[RequireModeratorOrNoLobby]
	#[RequireLoggedInParticipant]
	public function getReminder(int $messageId): DataResponse {
		try {
			$this->chatManager->getComment($this->room, (string) $messageId);
		} catch (NotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		try {
			$reminder = $this->reminderService->getReminder(
				$this->participant->getAttendee()->getActorId(),
				$messageId,
			);
			return new DataResponse($reminder->jsonSerialize(), Http::STATUS_OK);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete a chat reminder
	 *
	 * @param int $messageId ID of the message
	 * @psalm-param non-negative-int $messageId
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Reminder deleted successfully
	 * 404: Message not found
	 */
	#[NoAdminRequired]
	#[RequireModeratorOrNoLobby]
	#[RequireLoggedInParticipant]
	public function deleteReminder(int $messageId): DataResponse {
		try {
			$this->chatManager->getComment($this->room, (string) $messageId);
		} catch (NotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$this->reminderService->deleteReminder(
			$this->participant->getAttendee()->getActorId(),
			$this->room->getToken(),
			$messageId,
		);

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * Clear the chat history
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_ACCEPTED, TalkChatMessage, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_FORBIDDEN, array<empty>, array{}>
	 *
	 * 200: History cleared successfully
	 * 202: History cleared successfully, but Matterbridge is configured, so the information can be replicated elsewhere
	 * 403: Missing permissions to clear history
	 */
	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	#[RequireReadWriteConversation]
	public function clearHistory(): DataResponse {
		$attendee = $this->participant->getAttendee();
		if (!$this->participant->hasModeratorPermissions(false)
				|| $this->room->getType() === Room::TYPE_ONE_TO_ONE
				|| $this->room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			// Actor is not a moderator or not the owner of the message
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$systemMessageComment = $this->chatManager->clearHistory(
			$this->room,
			$attendee->getActorType(),
			$attendee->getActorId()
		);

		$systemMessage = $this->messageParser->createMessage($this->room, $this->participant, $systemMessageComment, $this->l);
		$this->messageParser->parseMessage($systemMessage);


		$data = $systemMessage->toArray($this->getResponseFormat());

		$bridge = $this->matterbridgeManager->getBridgeOfRoom($this->room);

		$headers = [];
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$headers = ['X-Chat-Last-Common-Read' => (string) $this->chatManager->getLastCommonReadMessage($this->room)];
		}
		return new DataResponse($data, $bridge['enabled'] ? Http::STATUS_ACCEPTED : Http::STATUS_OK, $headers);
	}

	/**
	 * Set the read marker to a specific message
	 *
	 * @param int $lastReadMessage ID if the last read message
	 * @psalm-param non-negative-int $lastReadMessage
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{X-Chat-Last-Common-Read?: numeric-string}>
	 *
	 * 200: Read marker set successfully
	 */
	#[NoAdminRequired]
	#[RequireParticipant]
	public function setReadMarker(int $lastReadMessage): DataResponse {
		$this->participantService->updateLastReadMessage($this->participant, $lastReadMessage);
		$headers = [];
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$headers = ['X-Chat-Last-Common-Read' => (string) $this->chatManager->getLastCommonReadMessage($this->room)];
		}
		return new DataResponse([], Http::STATUS_OK, $headers);
	}

	/**
	 * Mark a chat as unread
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{X-Chat-Last-Common-Read?: numeric-string}>
	 *
	 * 200: Read marker set successfully
	 */
	#[NoAdminRequired]
	#[RequireParticipant]
	public function markUnread(): DataResponse {
		$message = $this->room->getLastMessage();
		$unreadId = 0;

		if ($message instanceof IComment) {
			try {
				$previousMessage = $this->chatManager->getPreviousMessageWithVerb(
					$this->room,
					(int)$message->getId(),
					[ChatManager::VERB_MESSAGE],
					$message->getVerb() === ChatManager::VERB_MESSAGE
				);
				$unreadId = (int) $previousMessage->getId();
			} catch (NotFoundException $e) {
				// No chat message found, only system messages.
				// Marking unread from beginning
			}
		}

		return $this->setReadMarker($unreadId);
	}

	/**
	 * Get objects that are shared in the room overview
	 *
	 * @param 1|2|3|4|5|6|7|8|9|10|11|12|13|14|15|16|17|18|19|20 $limit Maximum number of objects
	 * @psalm-param int<1, 20> $limit
	 * @return DataResponse<Http::STATUS_OK, array<string, TalkChatMessage[]>, array{}>
	 *
	 * 200: List of shared objects messages of each type returned
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	public function getObjectsSharedInRoomOverview(int $limit = 7): DataResponse {
		$limit = min(20, $limit);

		$objectTypes = [
			Attachment::TYPE_AUDIO,
			Attachment::TYPE_DECK_CARD,
			Attachment::TYPE_FILE,
			Attachment::TYPE_LOCATION,
			Attachment::TYPE_MEDIA,
			Attachment::TYPE_OTHER,
			Attachment::TYPE_POLL,
			Attachment::TYPE_RECORDING,
			Attachment::TYPE_VOICE,
		];

		$messageIdsByType = [];
		// Get all attachments
		foreach ($objectTypes as $objectType) {
			$attachments = $this->attachmentService->getAttachmentsByType($this->room, $objectType, 0, $limit);
			$messageIdsByType[$objectType] = array_map(static fn (Attachment $attachment): int => $attachment->getMessageId(), $attachments);
		}

		$messages = $this->getMessagesForRoom(array_merge(...array_values($messageIdsByType)));

		$messagesByType = [];
		// Convert list of $messages to array grouped by type
		foreach ($objectTypes as $objectType) {
			$messagesByType[$objectType] = [];

			foreach ($messageIdsByType[$objectType] as $messageId) {
				if (isset($messages[$messageId])) {
					$messagesByType[$objectType][] = $messages[$messageId];
				}
			}
		}

		return new DataResponse($messagesByType, Http::STATUS_OK);
	}

	/**
	 * Get objects that are shared in the room
	 *
	 * @param string $objectType Type of the objects
	 * @param int $lastKnownMessageId ID of the last known message
	 * @psalm-param non-negative-int $lastKnownMessageId
	 * @param 1|2|3|4|5|6|7|8|9|10|11|12|13|14|15|16|17|18|19|20|21|22|23|24|25|26|27|28|29|30|31|32|33|34|35|36|37|38|39|40|41|42|43|44|45|46|47|48|49|50|51|52|53|54|55|56|57|58|59|60|61|62|63|64|65|66|67|68|69|70|71|72|73|74|75|76|77|78|79|80|81|82|83|84|85|86|87|88|89|90|91|92|93|94|95|96|97|98|99|100|101|102|103|104|105|106|107|108|109|110|111|112|113|114|115|116|117|118|119|120|121|122|123|124|125|126|127|128|129|130|131|132|133|134|135|136|137|138|139|140|141|142|143|144|145|146|147|148|149|150|151|152|153|154|155|156|157|158|159|160|161|162|163|164|165|166|167|168|169|170|171|172|173|174|175|176|177|178|179|180|181|182|183|184|185|186|187|188|189|190|191|192|193|194|195|196|197|198|199|200 $limit Maximum number of objects
	 * @psalm-param int<1, 200> $limit
	 * @return DataResponse<Http::STATUS_OK, TalkChatMessage[], array{X-Chat-Last-Given?: string}>
	 *
	 * 200: List of shared objects messages returned
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	public function getObjectsSharedInRoom(string $objectType, int $lastKnownMessageId = 0, int $limit = 100): DataResponse {
		$offset = max(0, $lastKnownMessageId);
		$limit = min(200, $limit);

		$attachments = $this->attachmentService->getAttachmentsByType($this->room, $objectType, $offset, $limit);
		$messageIds = array_map(static fn (Attachment $attachment): int => $attachment->getMessageId(), $attachments);

		$messages = $this->getMessagesForRoom($messageIds);

		if (!empty($messages)) {
			$newLastKnown = min(array_keys($messages));
			return new DataResponse($messages, Http::STATUS_OK, ['X-Chat-Last-Given' => $newLastKnown]);
		}

		return new DataResponse($messages, Http::STATUS_OK);
	}

	/**
	 * @return TalkChatMessage[]
	 */
	protected function getMessagesForRoom(array $messageIds): array {
		$comments = $this->chatManager->getMessagesForRoomById($this->room, $messageIds);
		$this->preloadShares($comments);

		$messages = [];
		$comments = $this->chatManager->filterCommentsWithNonExistingFiles($comments);
		foreach ($comments as $comment) {
			$message = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);

			$this->messageParser->parseMessage($message);

			$now = $this->timeFactory->getDateTime();
			$expireDate = $message->getComment()->getExpireDate();
			if ($expireDate instanceof \DateTime && $expireDate < $now) {
				continue;
			}

			if (!$message->getVisibility()) {
				continue;
			}

			$messages[(int) $comment->getId()] = $message->toArray($this->getResponseFormat());
		}

		return $messages;
	}

	/**
	 * Search for mentions
	 *
	 * @param string $search Text to search for
	 * @param int $limit Maximum number of results
	 * @param bool $includeStatus Include the user statuses
	 * @return DataResponse<Http::STATUS_OK, TalkChatMentionSuggestion[], array{}>
	 *
	 * 200: List of mention suggestions returned
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	public function mentions(string $search, int $limit = 20, bool $includeStatus = false): DataResponse {
		$this->searchPlugin->setContext([
			'itemType' => 'chat',
			'itemId' => $this->room->getId(),
			'room' => $this->room,
		]);
		$this->searchPlugin->search($search, $limit, 0, $this->searchResult);

		$results = $this->searchResult->asArray();
		$exactMatches = $results['exact'];
		unset($results['exact']);
		$results = array_merge_recursive($exactMatches, $results);

		$this->autoCompleteManager->registerSorter(Sorter::class);
		$this->autoCompleteManager->runSorters(['talk_chat_participants'], $results, [
			'itemType' => 'chat',
			'itemId' => (string) $this->room->getId(),
			'search' => $search,
		]);

		$statuses = [];
		if ($this->userId !== null
			&& $includeStatus
			&& $this->appManager->isEnabledForUser('user_status')) {
			$userIds = array_filter(array_map(static function (array $userResult) {
				return $userResult['value']['shareWith'];
			}, $results['users']));

			$statuses = $this->statusManager->getUserStatuses($userIds);
		}

		$results = $this->prepareResultArray($results, $statuses);

		$results = $this->chatManager->addConversationNotify($results, $search, $this->room, $this->participant);

		return new DataResponse($results);
	}


	/**
	 * @param array $results
	 * @param IUserStatus[] $statuses
	 * @return TalkChatMentionSuggestion[]
	 */
	protected function prepareResultArray(array $results, array $statuses): array {
		$output = [];
		foreach ($results as $type => $subResult) {
			foreach ($subResult as $result) {
				$data = [
					'id' => $result['value']['shareWith'],
					'label' => $result['label'],
					'source' => $type,
				];

				if ($type === Attendee::ACTOR_USERS && isset($statuses[$data['id']])) {
					$data['status'] = $statuses[$data['id']]->getStatus();
					$data['statusIcon'] = $statuses[$data['id']]->getIcon();
					$data['statusMessage'] = $statuses[$data['id']]->getMessage();
					$data['statusClearAt'] = $statuses[$data['id']]->getClearAt();
				}

				$output[] = $data;
			}
		}
		return $output;
	}
}
