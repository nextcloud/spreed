<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Chat\AutoComplete\SearchPlugin;
use OCA\Talk\Chat\AutoComplete\Sorter;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Chat\Notifier;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\ChatSummaryException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Federation\Authenticator;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Middleware\Attribute\FederationSupported;
use OCA\Talk\Middleware\Attribute\RequireAuthenticatedParticipant;
use OCA\Talk\Middleware\Attribute\RequireLoggedInParticipant;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Middleware\Attribute\RequirePermission;
use OCA\Talk\Middleware\Attribute\RequireReadWriteConversation;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\Reminder;
use OCA\Talk\Model\Session;
use OCA\Talk\Model\Thread;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\BotService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ProxyCacheMessageService;
use OCA\Talk\Service\ReminderService;
use OCA\Talk\Service\RoomFormatter;
use OCA\Talk\Service\SessionService;
use OCA\Talk\Service\ThreadService;
use OCA\Talk\Share\Helper\Preloader;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Services\IAppConfig;
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
use OCP\RichObjectStrings\IRichTextFormatter;
use OCP\RichObjectStrings\IValidator;
use OCP\Security\ITrustedDomainHelper;
use OCP\Security\RateLimiting\IRateLimitExceededException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\TaskProcessing\Exception\Exception;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use OCP\User\Events\UserLiveStatusEvent;
use OCP\UserStatus\IManager as IUserStatusManager;
use OCP\UserStatus\IUserStatus;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkChatMentionSuggestion from ResponseDefinitions
 * @psalm-import-type TalkChatMessage from ResponseDefinitions
 * @psalm-import-type TalkChatMessageWithParent from ResponseDefinitions
 * @psalm-import-type TalkChatReminder from ResponseDefinitions
 * @psalm-import-type TalkChatReminderUpcoming from ResponseDefinitions
 * @psalm-import-type TalkRichObjectParameter from ResponseDefinitions
 * @psalm-import-type TalkRoom from ResponseDefinitions
 */
class ChatController extends AEnvironmentAwareOCSController {
	/** @var string[] */
	protected array $guestNames;

	public function __construct(
		string $appName,
		private ?string $userId,
		IRequest $request,
		private IUserManager $userManager,
		private IAppManager $appManager,
		private ChatManager $chatManager,
		protected Manager $manager,
		private RoomFormatter $roomFormatter,
		private ReactionManager $reactionManager,
		private ParticipantService $participantService,
		private SessionService $sessionService,
		protected AttachmentService $attachmentService,
		protected AvatarService $avatarService,
		protected ReminderService $reminderService,
		protected ThreadService $threadService,
		private GuestManager $guestManager,
		private MessageParser $messageParser,
		protected Preloader $sharePreloader,
		private IManager $autoCompleteManager,
		private IUserStatusManager $statusManager,
		protected MatterbridgeManager $matterbridgeManager,
		protected BotService $botService,
		private SearchPlugin $searchPlugin,
		private ISearchResult $searchResult,
		protected ITimeFactory $timeFactory,
		protected IEventDispatcher $eventDispatcher,
		protected IValidator $richObjectValidator,
		protected ITrustedDomainHelper $trustedDomainHelper,
		private IL10N $l,
		protected Authenticator $federationAuthenticator,
		protected ProxyCacheMessageService $pcmService,
		protected Notifier $notifier,
		protected IRichTextFormatter $richTextFormatter,
		protected ITaskProcessingManager $taskProcessingManager,
		protected IAppConfig $appConfig,
		protected LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return list{0: Attendee::ACTOR_*, 1: string}
	 */
	protected function getActorInfo(string $actorDisplayName = ''): array {
		$remoteCloudId = $this->federationAuthenticator->getCloudId();
		if ($remoteCloudId !== '') {
			if ($actorDisplayName) {
				$this->participantService->updateDisplayNameForActor(Attendee::ACTOR_FEDERATED_USERS, $remoteCloudId, $actorDisplayName);
			}
			return [Attendee::ACTOR_FEDERATED_USERS, $remoteCloudId];
		}

		if ($this->userId === null) {
			if ($actorDisplayName) {
				$this->guestManager->updateName($this->room, $this->participant, $actorDisplayName);
			}
			/** @var Attendee::ACTOR_GUESTS|Attendee::ACTOR_EMAILS $actorType */
			$actorType = $this->participant->getAttendee()->getActorType();
			return [$actorType, $this->participant->getAttendee()->getActorId()];
		}

		if ($this->userId === MatterbridgeManager::BRIDGE_BOT_USERID && $actorDisplayName) {
			return [Attendee::ACTOR_BRIDGED, str_replace(['/', '"'], '', $actorDisplayName)];
		}

		return [Attendee::ACTOR_USERS, $this->userId];
	}

	/**
	 * @return DataResponse<Http::STATUS_CREATED, ?TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>
	 */
	protected function parseCommentToResponse(IComment $comment, ?Message $parentMessage = null): DataResponse {
		$chatMessage = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
		$this->messageParser->parseMessage($chatMessage);

		if (!$chatMessage->getVisibility()) {
			$headers = [];
			if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
				$headers = ['X-Chat-Last-Common-Read' => (string)$this->chatManager->getLastCommonReadMessage($this->room)];
			}
			return new DataResponse(null, Http::STATUS_CREATED, $headers);
		}

		try {
			$threadId = (int)$comment->getTopmostParentId() ?: (int)$comment->getId();
			$thread = $this->threadService->findByThreadId($this->room->getId(), $threadId);
		} catch (DoesNotExistException) {
			$thread = null;
		}
		$data = $chatMessage->toArray($this->getResponseFormat(), $thread);
		if ($parentMessage instanceof Message) {
			$data['parent'] = $parentMessage->toArray($this->getResponseFormat(), $thread);
		}

		$headers = [];
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$headers = ['X-Chat-Last-Common-Read' => (string)$this->chatManager->getLastCommonReadMessage($this->room)];
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
	 * @param string $threadTitle Only supported when not replying, when given will create a thread (requires `threads` capability)
	 * @param int $threadId Thread id which this message is a reply to without quoting a specific message (ignored when $replyTo is given, also requires `threads` capability)
	 * @return DataResponse<Http::STATUS_CREATED, ?TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_REQUEST_ENTITY_TOO_LARGE|Http::STATUS_TOO_MANY_REQUESTS, array{error: string}, array{}>
	 *
	 * 201: Message sent successfully
	 * 400: Sending message is not possible
	 * 404: Actor not found
	 * 413: Message too long
	 * 429: Mention rate limit exceeded (guests only)
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/chat/{token}', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function sendMessage(string $message, string $actorDisplayName = '', string $referenceId = '', int $replyTo = 0, bool $silent = false, string $threadTitle = '', int $threadId = 0): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController::class);
			return $proxy->sendMessage($this->room, $this->participant, $message, $referenceId, $replyTo, $silent, $threadTitle, $threadId);
		}

		if (trim($message) === '') {
			return new DataResponse(['error' => 'message'], Http::STATUS_BAD_REQUEST);
		}

		[$actorType, $actorId] = $this->getActorInfo($actorDisplayName);
		if (!$actorId) {
			return new DataResponse(['error' => 'actor'], Http::STATUS_NOT_FOUND);
		}

		$parent = $parentMessage = null;
		if ($replyTo !== 0) {
			try {
				$parent = $this->chatManager->getParentComment($this->room, (string)$replyTo);
			} catch (NotFoundException $e) {
				// Someone is trying to reply cross-rooms or to a non-existing message
				return new DataResponse(['error' => 'reply-to'], Http::STATUS_BAD_REQUEST);
			}

			$parentMessage = $this->messageParser->createMessage($this->room, $this->participant, $parent, $this->l);
			$this->messageParser->parseMessage($parentMessage);
			if (!$parentMessage->isReplyable()) {
				return new DataResponse(['error' => 'reply-to'], Http::STATUS_BAD_REQUEST);
			}
		} elseif ($threadId !== 0) {
			if (!$this->threadService->validateThread($this->room->getId(), $threadId)) {
				return new DataResponse(['error' => 'reply-to'], Http::STATUS_BAD_REQUEST);
			}
		}

		$this->participantService->ensureOneToOneRoomIsFilled($this->room);
		$creationDateTime = $this->timeFactory->getDateTime('now', new \DateTimeZone('UTC'));

		try {
			$createThread = $replyTo === 0 && $threadId === Thread::THREAD_NONE && $threadTitle !== '';
			$threadId = $createThread ? Thread::THREAD_CREATE : $threadId;
			$comment = $this->chatManager->sendMessage($this->room, $this->participant, $actorType, $actorId, $message, $creationDateTime, $parent, $referenceId, $silent, threadId: $threadId);
			if ($createThread) {
				$thread = $this->threadService->createThread($this->room, (int)$comment->getId(), $threadTitle);
				// Add to subscribed threads list
				$this->threadService->setNotificationLevel($this->participant->getAttendee(), $thread->getId(), Participant::NOTIFY_DEFAULT);

				$this->chatManager->addSystemMessage(
					$this->room,
					$this->participant,
					$this->participant->getAttendee()->getActorType(),
					$this->participant->getAttendee()->getActorId(),
					json_encode(['message' => 'thread_created', 'parameters' => ['thread' => (int)$comment->getId(), 'title' => $thread->getName()]]),
					$this->timeFactory->getDateTime(),
					false,
					null,
					$comment,
					true,
					true
				);
			}
		} catch (MessageTooLongException) {
			return new DataResponse(['error' => 'message'], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (IRateLimitExceededException) {
			return new DataResponse(['error' => 'mentions'], Http::STATUS_TOO_MANY_REQUESTS);
		} catch (\Exception $e) {
			$this->logger->warning($e->getMessage());
			return new DataResponse(['error' => 'message'], Http::STATUS_BAD_REQUEST);
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
	 * @param string $metaData Additional metadata, sample value: `{\"type\":\"geo-location\",\"id\":\"geo:52.5450511,13.3741463\",\"name\":\"Nextcloud Berlin Office\",\"latitude\":\"52.5450511\",\"longitude\":\"13.3741463\"}`
	 * @param string $actorDisplayName Guest name
	 * @param string $referenceId Reference ID
	 * @param int $threadId Thread id which this message is a reply to without quoting a specific message (also requires `threads` capability)
	 * @return DataResponse<Http::STATUS_CREATED, ?TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_REQUEST_ENTITY_TOO_LARGE, array{error: string}, array{}>
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
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/chat/{token}/share', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function shareObjectToChat(string $objectType, string $objectId, string $metaData = '', string $actorDisplayName = '', string $referenceId = '', int $threadId = 0): DataResponse {
		[$actorType, $actorId] = $this->getActorInfo($actorDisplayName);
		if (!$actorId) {
			return new DataResponse(['error' => 'actor'], Http::STATUS_NOT_FOUND);
		}

		/** @var TalkRichObjectParameter $data */
		$data = $metaData !== '' ? json_decode($metaData, true) : [];
		if (!is_array($data)) {
			/** @var TalkRichObjectParameter $data */
			$data = [];
		}
		$data['type'] = $objectType;
		$data['id'] = $objectId;
		$data['icon-url'] = $this->avatarService->getAvatarUrl($this->room);

		if (isset($data['link']) && !$this->trustedDomainHelper->isTrustedUrl($data['link'])) {
			return new DataResponse(['error' => 'link'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->richObjectValidator->validate('{object}', ['object' => $data]);
		} catch (InvalidObjectExeption $e) {
			return new DataResponse(['error' => 'object'], Http::STATUS_BAD_REQUEST);
		}

		if ($data['type'] === 'geo-location'
			&& !preg_match(ChatManager::GEO_LOCATION_VALIDATOR, $data['id'])) {
			return new DataResponse(['error' => 'object'], Http::STATUS_BAD_REQUEST);
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

		if ($threadId !== 0) {
			try {
				$this->threadService->findByThreadId($this->room->getId(), $threadId);
			} catch (DoesNotExistException) {
				// Someone tried to cheat, ignore
				$threadId = 0;
			}
		}

		try {
			$comment = $this->chatManager->addSystemMessage($this->room, $this->participant, $actorType, $actorId, $message, $creationDateTime, true, $referenceId, threadId: $threadId);
		} catch (MessageTooLongException $e) {
			return new DataResponse(['error' => 'message'], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (\Exception $e) {
			return new DataResponse(['error' => 'message'], Http::STATUS_BAD_REQUEST);
		}

		return $this->parseCommentToResponse($comment);
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
	 * @param int<0, 30> $timeout Number of seconds to wait for new messages (30 by default, 30 at most)
	 * @param 0|1 $setReadMarker Automatically set the last read marker when 1,
	 *                           if your client does this itself via chat/{token}/read set to 0
	 * @param 0|1 $includeLastKnown Include the $lastKnownMessageId in the messages when 1 (default 0)
	 * @param 0|1 $noStatusUpdate When the user status should not be automatically set to online set to 1 (default 0)
	 * @param 0|1 $markNotificationsAsRead Set to 0 when notifications should not be marked as read (default 1)
	 * @param int $threadId Limit the chat message list to a given thread
	 * @psalm-param non-negative-int $threadId
	 * @return DataResponse<Http::STATUS_OK, list<TalkChatMessageWithParent>, array{'X-Chat-Last-Common-Read'?: numeric-string, X-Chat-Last-Given?: numeric-string}>|DataResponse<Http::STATUS_NOT_MODIFIED, null, array{}>
	 *
	 * 200: Messages returned
	 * 304: No messages
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/{token}', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function receiveMessages(
		int $lookIntoFuture,
		int $limit = 100,
		int $lastKnownMessageId = 0,
		int $lastCommonReadId = 0,
		int $timeout = 30,
		int $setReadMarker = 1,
		int $includeLastKnown = 0,
		int $noStatusUpdate = 0,
		int $markNotificationsAsRead = 1,
		int $threadId = 0,
	): DataResponse {
		$limit = min(200, $limit);
		$timeout = min(30, $timeout);

		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController::class);
			return $proxy->receiveMessages(
				$this->room,
				$this->participant,
				$lookIntoFuture,
				$limit,
				$lastKnownMessageId,
				$lastCommonReadId,
				$timeout,
				$setReadMarker,
				$includeLastKnown,
				$noStatusUpdate,
				$markNotificationsAsRead,
				// FIXME support threads in federation $threadId,
			);
		}

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
		if ($lookIntoFuture && $setReadMarker === 1
			&& $lastKnownMessageId > $attendee->getLastReadMessage()) {
			$this->participantService->updateLastReadMessage($this->participant, $lastKnownMessageId);
		}

		$currentUser = $this->userManager->get($this->userId);
		if ($lookIntoFuture) {
			$comments = $this->chatManager->waitForNewMessages($this->room, $lastKnownMessageId, $limit, $timeout, $currentUser, (bool)$includeLastKnown, (bool)$markNotificationsAsRead, $threadId);
		} else {
			$comments = $this->chatManager->getHistory($this->room, $lastKnownMessageId, $limit, (bool)$includeLastKnown, $threadId);
		}

		return $this->prepareCommentsAsDataResponse($comments, $lastCommonReadId);
	}

	/**
	 * Summarize the next bunch of chat messages from a given offset
	 *
	 * Required capability: `chat-summary-api`
	 *
	 * @param positive-int $fromMessageId Offset from where on the summary should be generated
	 * @return DataResponse<Http::STATUS_CREATED, array{taskId: int, nextOffset?: int}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'ai-no-provider'|'ai-error'}, array{}>|DataResponse<Http::STATUS_NO_CONTENT, null, array{}>
	 * @throws \InvalidArgumentException
	 *
	 * 201: Summary was scheduled, use the returned taskId to get the status information and output from the TaskProcessing API: [OCS TaskProcessing API](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-taskprocessing-api.html#fetch-a-task-by-id). If the response data contains nextOffset, not all messages could be handled in a single request. After receiving the response a second summary should be requested with the provided nextOffset.
	 * 204: No messages found to summarize
	 * 400: No AI provider available or summarizing failed
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/chat/{token}/summarize', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function summarizeChat(
		int $fromMessageId,
	): DataResponse {
		$fromMessageId = max(0, $fromMessageId);

		$supportedTaskTypeIds = $this->taskProcessingManager->getAvailableTaskTypeIds();
		if (!in_array(TextToTextSummary::ID, $supportedTaskTypeIds, true)) {
			return new DataResponse([
				'error' => ChatSummaryException::REASON_AI_ERROR,
			], Http::STATUS_BAD_REQUEST);
		}

		// if ($this->room->isFederatedConversation()) {
		// /** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController $proxy */
		// $proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController::class);
		// return $proxy->summarizeChat(
		// $this->room,
		// $this->participant,
		// $fromMessageId,
		// );
		// }

		$currentUser = $this->userManager->get($this->userId);
		$batchSize = $this->appConfig->getAppValueInt('ai_unread_summary_batch_size', 500);
		$comments = $this->chatManager->waitForNewMessages($this->room, $fromMessageId, $batchSize, 0, $currentUser, true, false);
		$this->sharePreloader->preloadShares($comments);

		$messages = [];
		$nextOffset = 0;
		foreach ($comments as $comment) {
			$nextOffset = (int)$comment->getId();
			$message = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
			$this->messageParser->parseMessage($message, true);

			if (!$message->getVisibility()) {
				continue;
			}

			if ($message->getMessageType() === ChatManager::VERB_SYSTEM
				&& !in_array($message->getMessageRaw(), [
					'call_ended',
					'call_ended_everyone',
					'file_shared',
					'object_shared',
				], true)) {
				// Ignore system messages apart from calls, shared objects and files
				continue;
			}

			$parsedMessage = $this->richTextFormatter->richToParsed(
				$message->getMessage(),
				$message->getMessageParameters(),
			);

			$displayName = $message->getActorDisplayName();
			if (in_array($message->getActorType(), [
				Attendee::ACTOR_GUESTS,
				Attendee::ACTOR_EMAILS,
			], true)) {
				if ($displayName === '') {
					$displayName = $this->l->t('Guest');
				} else {
					$displayName = $this->l->t('%s (guest)', $displayName);
				}
			}

			if ($comment->getParentId() !== '0') {
				// FIXME should add something?
			}

			$messages[] = $displayName . ': ' . $parsedMessage;
		}

		if (empty($messages)) {
			return new DataResponse(null, Http::STATUS_NO_CONTENT);
		}

		$task = new Task(
			TextToTextSummary::ID,
			['input' => implode("\n\n", $messages)],
			Application::APP_ID,
			$this->userId,
			'summary/' . $this->room->getToken(),
		);

		try {
			$this->taskProcessingManager->scheduleTask($task);
		} catch (Exception $e) {
			$this->logger->error('An error occurred while trying to summarize unread messages', ['exception' => $e]);
			return new DataResponse([
				'error' => ChatSummaryException::REASON_AI_ERROR,
			], Http::STATUS_BAD_REQUEST);
		}

		$taskId = $task->getId();
		if ($taskId === null) {
			return new DataResponse([
				'error' => ChatSummaryException::REASON_AI_ERROR,
			], Http::STATUS_BAD_REQUEST);
		}

		$data = [
			'taskId' => $taskId,
		];

		if ($nextOffset !== $this->room->getLastMessageId()) {
			$data['nextOffset'] = $nextOffset;
		}

		return new DataResponse($data, Http::STATUS_CREATED);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, list<TalkChatMessageWithParent>, array{'X-Chat-Last-Common-Read'?: numeric-string, X-Chat-Last-Given?: numeric-string}>|DataResponse<Http::STATUS_NOT_MODIFIED, null, array{}>
	 */
	protected function prepareCommentsAsDataResponse(array $comments, int $lastCommonReadId = 0): DataResponse {
		if (empty($comments)) {
			if ($lastCommonReadId && $this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
				$newLastCommonRead = $this->chatManager->getLastCommonReadMessage($this->room);
				if ($lastCommonReadId !== $newLastCommonRead) {
					// Set the status code to 200 so the header is sent to the client.
					// As per "section 10.3.5 of RFC 2616" entity headers shall be
					// stripped out on 304: https://stackoverflow.com/a/17822709
					/** @var array{X-Chat-Last-Common-Read?: numeric-string, X-Chat-Last-Given?: numeric-string} $headers */
					$headers = ['X-Chat-Last-Common-Read' => (string)$newLastCommonRead];
					return new DataResponse([], Http::STATUS_OK, $headers);
				}
			}
			return new DataResponse(null, Http::STATUS_NOT_MODIFIED);
		}

		$this->sharePreloader->preloadShares($comments);
		$potentialThreadIds = array_map(static fn (IComment $comment) => (int)$comment->getTopmostParentId() ?: (int)$comment->getId(), $comments);
		$threads = $this->threadService->findByThreadIds($this->room->getId(), $potentialThreadIds);

		$i = 0;
		$now = $this->timeFactory->getDateTime();
		$messages = $commentIdToIndex = $parentIds = [];
		foreach ($comments as $comment) {
			$id = (int)$comment->getId();
			$message = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
			$this->messageParser->parseMessage($message);

			$expireDate = $message->getExpirationDateTime();
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

			$threadId = (int)$comment->getTopmostParentId() ?: $id;
			$messages[] = $message->toArray($this->getResponseFormat(), $threads[$threadId] ?? null);
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
						$threadId = (int)$comment->getTopmostParentId() ?: $parentId;
						$loadedParents[$parentId] = $message->toArray($this->getResponseFormat(), $threads[$threadId] ?? null);
						$messages[$commentKey]['parent'] = $loadedParents[$parentId];
						continue;
					}

					$expireDate = $message->getComment()->getExpireDate();
					if ($expireDate instanceof \DateTime && $expireDate < $now) {
						$commentIdToIndex[$id] = null;
						continue;
					}

					$loadedParents[$parentId] = [
						'id' => (int)$parentId,
						'deleted' => true,
					];
				} catch (NotFoundException $e) {
				}
			}

			// Message is not visible to the user
			$messages[$commentKey]['parent'] = [
				'id' => (int)$parentId,
				'deleted' => true,
			];
		}

		$messages = $this->loadSelfReactions($messages, $commentIdToIndex);

		$headers = [];
		$newLastKnown = end($comments);
		if ($newLastKnown instanceof IComment) {
			$headers = ['X-Chat-Last-Given' => (string)(int)$newLastKnown->getId()];
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
	 * @param int<1, 100> $limit Number of chat messages to receive in both directions (50 by default, 100 at most, might return 201 messages)
	 * @param int $threadId Limit the chat message list to a given thread
	 * @psalm-param non-negative-int $threadId
	 * @return DataResponse<Http::STATUS_OK, list<TalkChatMessageWithParent>, array{'X-Chat-Last-Common-Read'?: numeric-string, X-Chat-Last-Given?: numeric-string}>|DataResponse<Http::STATUS_NOT_MODIFIED, null, array{}>
	 *
	 * 200: Message context returned
	 * 304: No messages
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/{token}/{messageId}/context', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
		'messageId' => '[0-9]+',
	])]
	public function getMessageContext(
		int $messageId,
		int $limit = 50,
		int $threadId = 0,
	): DataResponse {
		$limit = min(100, $limit);

		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController::class);
			return $proxy->getMessageContext(
				$this->room,
				$this->participant,
				$messageId,
				$limit,
				// FIXME support threads in federation $threadId,
			);
		}

		$currentUser = $this->userManager->get($this->userId);
		if ($messageId === 0) {
			// Guest in a fully expired chat, no history, just loading the chat from beginning for now
			$commentsHistory = $commentsFuture = [];
		} else {
			$commentsHistory = $this->chatManager->getHistory($this->room, $messageId, $limit, true, $threadId);
			$commentsHistory = array_reverse($commentsHistory);
			$commentsFuture = $this->chatManager->waitForNewMessages($this->room, $messageId, $limit, 0, $currentUser, false, threadId: $threadId);
		}

		return $this->prepareCommentsAsDataResponse(array_merge($commentsHistory, $commentsFuture));
	}

	/**
	 * @psalm-template T as list
	 * @psalm-param T $messages
	 * @param array $commentIdToIndex
	 * @psalm-return T
	 */
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
			$parentMap[(int)$entry['parent']] ??= [];
			$parentMap[(int)$entry['parent']][] = (int)$entry['message'];
			$parentIdsWithReactions[] = (int)$entry['parent'];
		}

		// Unique list for the query
		$idsWithReactions = array_unique(array_merge($messageIdsWithReactions, $parentIdsWithReactions));
		$reactionsById = $this->reactionManager->getReactionsByActorForMessages($this->participant, $idsWithReactions);

		// Inject the reactions self into the $messages array
		foreach ($reactionsById as $messageId => $reactions) {
			if (isset($commentIdToIndex[$messageId], $messages[$commentIdToIndex[$messageId]])) {
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

		/** @psalm-var T $messages */
		return $messages;
	}

	/**
	 * Delete a chat message
	 *
	 * @param int $messageId ID of the message
	 * @psalm-param non-negative-int $messageId
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_ACCEPTED, TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_METHOD_NOT_ALLOWED, array{error: string}, array{}>
	 *
	 * 200: Message deleted successfully
	 * 202: Message deleted successfully, but a bot or Matterbridge is configured, so the information can be replicated elsewhere
	 * 400: Deleting message is not possible
	 * 403: Missing permissions to delete message
	 * 404: Message not found
	 * 405: Deleting this message type is not allowed
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireAuthenticatedParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/chat/{token}/{messageId}', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
		'messageId' => '[0-9]+',
	])]
	public function deleteMessage(int $messageId): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController::class);
			return $proxy->deleteMessage(
				$this->room,
				$this->participant,
				$messageId,
			);
		}

		try {
			$message = $this->chatManager->getComment($this->room, (string)$messageId);
		} catch (NotFoundException) {
			return new DataResponse(['error' => 'message'], Http::STATUS_NOT_FOUND);
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
			return new DataResponse(['error' => 'permission'], Http::STATUS_FORBIDDEN);
		}

		if ($message->getVerb() !== ChatManager::VERB_MESSAGE && $message->getVerb() !== ChatManager::VERB_OBJECT_SHARED) {
			// System message (since the message is not parsed, it has type "system")
			return new DataResponse(['error' => 'message'], Http::STATUS_METHOD_NOT_ALLOWED);
		}

		try {
			$systemMessageComment = $this->chatManager->deleteMessage(
				$this->room,
				$message,
				$this->participant,
				$this->timeFactory->getDateTime()
			);
		} catch (ShareNotFound) {
			return new DataResponse(['error' => 'message'], Http::STATUS_NOT_FOUND);
		}

		$systemMessage = $this->messageParser->createMessage($this->room, $this->participant, $systemMessageComment, $this->l);
		$this->messageParser->parseMessage($systemMessage);

		$comment = $this->chatManager->getComment($this->room, (string)$messageId);
		$message = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
		$this->messageParser->parseMessage($message);

		try {
			$threadId = (int)$comment->getTopmostParentId() ?: (int)$comment->getId();
			$thread = $this->threadService->findByThreadId($this->room->getId(), $threadId);
		} catch (DoesNotExistException) {
			$thread = null;
		}

		$data = $systemMessage->toArray($this->getResponseFormat(), $thread);
		$data['parent'] = $message->toArray($this->getResponseFormat(), $thread);

		$hasBotOrBridge = !empty($this->botService->getBotsForToken($this->room->getToken(), Bot::FEATURE_WEBHOOK));
		if (!$hasBotOrBridge) {
			$bridge = $this->matterbridgeManager->getBridgeOfRoom($this->room);
			$hasBotOrBridge = $bridge['enabled'];
		}

		$headers = [];
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$headers = ['X-Chat-Last-Common-Read' => (string)$this->chatManager->getLastCommonReadMessage($this->room)];
		}
		return new DataResponse($data, $hasBotOrBridge ? Http::STATUS_ACCEPTED : Http::STATUS_OK, $headers);
	}

	/**
	 * Edit a chat message
	 *
	 * @param int $messageId ID of the message
	 * @param string $message the message to send
	 * @psalm-param non-negative-int $messageId
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_ACCEPTED, TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_METHOD_NOT_ALLOWED|Http::STATUS_REQUEST_ENTITY_TOO_LARGE, array{error: string}, array{}>
	 *
	 * 200: Message edited successfully
	 * 202: Message edited successfully, but a bot or Matterbridge is configured, so the information can be replicated to other services
	 * 400: Editing message is not possible, e.g. when the new message is empty or the message is too old
	 * 403: Missing permissions to edit message
	 * 404: Message not found
	 * 405: Editing this message type is not allowed
	 * 413: Message too long
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireAuthenticatedParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/chat/{token}/{messageId}', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
		'messageId' => '[0-9]+',
	])]
	public function editMessage(int $messageId, string $message): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController::class);
			return $proxy->editMessage(
				$this->room,
				$this->participant,
				$messageId,
				$message,
			);
		}

		try {
			$comment = $this->chatManager->getComment($this->room, (string)$messageId);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => 'message'], Http::STATUS_NOT_FOUND);
		}

		$attendee = $this->participant->getAttendee();
		$isOwnMessage = $comment->getActorType() === $attendee->getActorType()
			&& $comment->getActorId() === $attendee->getActorId();

		// Special case for if the message is a bridged message, then the message is the bridge bot's message.
		$isOwnMessage = $isOwnMessage || ($comment->getActorType() === Attendee::ACTOR_BRIDGED && $attendee->getActorId() === MatterbridgeManager::BRIDGE_BOT_USERID);
		$isBotInOneToOne = $comment->getActorType() === Attendee::ACTOR_BOTS
			&& str_starts_with($comment->getActorId(), Attendee::ACTOR_BOT_PREFIX)
			&& ($this->room->getType() === Room::TYPE_ONE_TO_ONE
				|| $this->room->getType() === Room::TYPE_ONE_TO_ONE_FORMER);
		if (!($isOwnMessage || $isBotInOneToOne)
			&& (!$this->participant->hasModeratorPermissions(false)
				|| $this->room->getType() === Room::TYPE_ONE_TO_ONE
				|| $this->room->getType() === Room::TYPE_ONE_TO_ONE_FORMER)) {
			// Actor is not a moderator or not the owner of the message
			return new DataResponse(['error' => 'permission'], Http::STATUS_FORBIDDEN);
		}

		if ($comment->getVerb() !== ChatManager::VERB_MESSAGE && $comment->getVerb() !== ChatManager::VERB_OBJECT_SHARED) {
			// System message (since the message is not parsed, it has type "system")
			return new DataResponse(['error' => 'message'], Http::STATUS_METHOD_NOT_ALLOWED);
		}

		if ($this->room->getType() !== Room::TYPE_NOTE_TO_SELF) {
			$maxAge = $this->timeFactory->getDateTime();
			$maxAge->sub(new \DateInterval('P1D'));
			if ($comment->getCreationDateTime() < $maxAge) {
				// Message is too old
				return new DataResponse(['error' => 'age'], Http::STATUS_BAD_REQUEST);
			}
		}
		try {
			$systemMessageComment = $this->chatManager->editMessage(
				$this->room,
				$comment,
				$this->participant,
				$this->timeFactory->getDateTime(),
				$message
			);
		} catch (MessageTooLongException) {
			return new DataResponse(['error' => 'message'], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (\InvalidArgumentException $e) {
			if ($e->getMessage() === 'object_share') {
				return new DataResponse(['error' => 'message'], Http::STATUS_METHOD_NOT_ALLOWED);
			}
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		$systemMessage = $this->messageParser->createMessage($this->room, $this->participant, $systemMessageComment, $this->l);
		$this->messageParser->parseMessage($systemMessage);

		$comment = $this->chatManager->getComment($this->room, (string)$messageId);
		$parseMessage = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
		$this->messageParser->parseMessage($parseMessage);

		try {
			$threadId = (int)$comment->getTopmostParentId() ?: (int)$comment->getId();
			$thread = $this->threadService->findByThreadId($this->room->getId(), $threadId);
		} catch (DoesNotExistException) {
			$thread = null;
		}

		$data = $systemMessage->toArray($this->getResponseFormat(), $thread);
		$data['parent'] = $parseMessage->toArray($this->getResponseFormat(), $thread);

		$hasBotOrBridge = !empty($this->botService->getBotsForToken($this->room->getToken(), Bot::FEATURE_WEBHOOK));
		if (!$hasBotOrBridge) {
			$bridge = $this->matterbridgeManager->getBridgeOfRoom($this->room);
			$hasBotOrBridge = $bridge['enabled'];
		}

		$headers = [];
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$headers = ['X-Chat-Last-Common-Read' => (string)$this->chatManager->getLastCommonReadMessage($this->room)];
		}
		return new DataResponse($data, $hasBotOrBridge ? Http::STATUS_ACCEPTED : Http::STATUS_OK, $headers);
	}

	/**
	 * Set a reminder for a chat message
	 *
	 * @param int $messageId ID of the message
	 * @psalm-param non-negative-int $messageId
	 * @param int $timestamp Timestamp of the reminder
	 * @psalm-param non-negative-int $timestamp
	 * @return DataResponse<Http::STATUS_CREATED, TalkChatReminder, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error?: string}, array{}>
	 *
	 * 201: Reminder created successfully
	 * 404: Message not found
	 */
	#[FederationSupported]
	#[NoAdminRequired]
	#[RequireModeratorOrNoLobby]
	#[RequireLoggedInParticipant]
	#[UserRateLimit(limit: 60, period: 3600)]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/chat/{token}/{messageId}/reminder', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
		'messageId' => '[0-9]+',
	])]
	public function setReminder(int $messageId, int $timestamp): DataResponse {
		try {
			// FIXME fail 400 when reminder is after expiration
			// And system messages
			$this->validateMessageExists($messageId, sync: true);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'message'], Http::STATUS_NOT_FOUND);
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
	 * @return DataResponse<Http::STATUS_OK, TalkChatReminder, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error?: string}, array{}>
	 *
	 * 200: Reminder returned
	 * 404: No reminder found
	 * 404: Message not found
	 */
	#[FederationSupported]
	#[NoAdminRequired]
	#[RequireModeratorOrNoLobby]
	#[RequireLoggedInParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/{token}/{messageId}/reminder', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
		'messageId' => '[0-9]+',
	])]
	public function getReminder(int $messageId): DataResponse {
		try {
			$this->validateMessageExists($messageId);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'message'], Http::STATUS_NOT_FOUND);
		}

		try {
			$reminder = $this->reminderService->getReminder(
				$this->participant->getAttendee()->getActorId(),
				$this->room->getToken(),
				$messageId,
			);
			return new DataResponse($reminder->jsonSerialize(), Http::STATUS_OK);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'reminder'], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete a chat reminder
	 *
	 * @param int $messageId ID of the message
	 * @psalm-param non-negative-int $messageId
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array{error?: string}, array{}>
	 *
	 * 200: Reminder deleted successfully
	 * 404: Message not found
	 */
	#[FederationSupported]
	#[NoAdminRequired]
	#[RequireModeratorOrNoLobby]
	#[RequireLoggedInParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/chat/{token}/{messageId}/reminder', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
		'messageId' => '[0-9]+',
	])]
	public function deleteReminder(int $messageId): DataResponse {
		try {
			$this->validateMessageExists($messageId);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'message'], Http::STATUS_NOT_FOUND);
		}

		$this->reminderService->deleteReminder(
			$this->participant->getAttendee()->getActorId(),
			$this->room->getToken(),
			$messageId,
		);

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * Get all upcoming reminders
	 *
	 * Required capability: `upcoming-reminders`
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkChatReminderUpcoming>, array{}>
	 *
	 * 200: Reminders returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/upcoming-reminders', requirements: [
		'apiVersion' => '(v1)',
	])]
	public function getUpcomingReminders(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_OK);
		}

		$reminders = $this->reminderService->getUpcomingReminders($this->userId, Reminder::NUM_UPCOMING_REMINDERS);
		if (empty($reminders)) {
			return new DataResponse([], Http::STATUS_OK);
		}

		$tokens = array_unique(array_map(static fn (Reminder $reminder): string => $reminder->getToken(), $reminders));
		$rooms = $this->manager->getRoomsForActor(Attendee::ACTOR_USERS, $this->userId, tokens: $tokens);
		$roomMap = [];
		foreach ($rooms as $room) {
			if ($room->isFederatedConversation()) {
				// FIXME Federated chats
				continue;
			}
			$roomMap[$room->getToken()] = $room;
		}

		/** @var Reminder[] $reminders */
		$reminders = array_filter($reminders, static fn (Reminder $reminder): bool => isset($roomMap[$reminder->getToken()]));
		if (empty($reminders)) {
			return new DataResponse([], Http::STATUS_OK);
		}

		$messageIds = array_map(static fn (Reminder $reminder): int => $reminder->getMessageId(), $reminders);
		$comments = $this->chatManager->getMessagesById($messageIds);
		$now = $this->timeFactory->getDateTime();

		$resultData = [];
		foreach ($reminders as $reminder) {
			if (!isset($comments[$reminder->getMessageId()])) {
				continue;
			}
			$comment = $comments[$reminder->getMessageId()];
			$room = $roomMap[$reminder->getToken()];
			try {
				$participant = $this->participantService->getParticipant($room, $this->userId);
			} catch (ParticipantNotFoundException) {
				continue;
			}

			$message = $this->messageParser->createMessage($room, $participant, $comment, $this->l);
			$this->messageParser->parseMessage($message);

			$expireDate = $message->getExpirationDateTime();
			if ($expireDate instanceof \DateTime && $expireDate < $now) {
				continue;
			}

			if (!$message->getVisibility()) {
				continue;
			}

			$data = $message->toArray($this->getResponseFormat(), null);

			if ($participant->getAttendee()->isSensitive()) {
				$data['message'] = '';
				$data['messageParameters'] = [];
			}

			$resultData[] = [
				'reminderTimestamp' => $reminder->getDateTime()->getTimestamp(),
				'roomToken' => $reminder->getToken(),
				'messageId' => $reminder->getMessageId(),
				'actorType' => $data['actorType'],
				'actorId' => $data['actorId'],
				'actorDisplayName' => $data['actorDisplayName'],
				'message' => $data['message'],
				'messageParameters' => $data['messageParameters'],
			];
		}

		return new DataResponse($resultData, Http::STATUS_OK);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws CannotReachRemoteException
	 */
	protected function validateMessageExists(int $messageId, bool $sync = false): void {
		if ($this->room->isFederatedConversation()) {
			try {
				$this->pcmService->findByRemote($this->room->getRemoteServer(), $this->room->getRemoteToken(), $messageId);
			} catch (DoesNotExistException) {
				if ($sync) {
					$this->pcmService->syncRemoteMessage($this->room, $this->participant, $messageId);
				}
			}
			return;
		}

		try {
			$this->chatManager->getComment($this->room, (string)$messageId);
		} catch (NotFoundException $e) {
			throw new DoesNotExistException($e->getMessage());
		}
	}

	/**
	 * Clear the chat history
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_ACCEPTED, TalkChatMessage, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_FORBIDDEN, null, array{}>
	 *
	 * 200: History cleared successfully
	 * 202: History cleared successfully, but Federation or Matterbridge is configured, so the information can be replicated elsewhere
	 * 403: Missing permissions to clear history
	 */
	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	#[RequireReadWriteConversation]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/chat/{token}', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function clearHistory(): DataResponse {
		$attendee = $this->participant->getAttendee();
		if (!$this->participant->hasModeratorPermissions(false)) {
			// Actor is not a moderator
			return new DataResponse(null, Http::STATUS_FORBIDDEN);
		}

		if (!$this->appConfig->getAppValueBool('delete_one_to_one_conversations')
				&& ($this->room->getType() === Room::TYPE_ONE_TO_ONE
					|| $this->room->getType() === Room::TYPE_ONE_TO_ONE_FORMER)) {
			// Not allowed to purge one-to-one conversations
			return new DataResponse(null, Http::STATUS_FORBIDDEN);
		}

		$systemMessageComment = $this->chatManager->clearHistory(
			$this->room,
			$attendee->getActorType(),
			$attendee->getActorId()
		);

		$systemMessage = $this->messageParser->createMessage($this->room, $this->participant, $systemMessageComment, $this->l);
		$this->messageParser->parseMessage($systemMessage);


		$data = $systemMessage->toArray($this->getResponseFormat(), null);

		$bridge = $this->matterbridgeManager->getBridgeOfRoom($this->room);

		$headers = [];
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$headers = ['X-Chat-Last-Common-Read' => (string)$this->chatManager->getLastCommonReadMessage($this->room)];
		}
		return new DataResponse($data, $bridge['enabled'] ? Http::STATUS_ACCEPTED : Http::STATUS_OK, $headers);
	}

	/**
	 * Set the read marker to a specific message
	 *
	 * @param int|null $lastReadMessage ID if the last read message (Optional only with `chat-read-last` capability)
	 * @psalm-param int<-2, max>|null $lastReadMessage
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{X-Chat-Last-Common-Read?: numeric-string}>
	 *
	 * 200: Read marker set successfully
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireAuthenticatedParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/chat/{token}/read', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function setReadMarker(?int $lastReadMessage = null): DataResponse {
		$setToMessage = $lastReadMessage ?? $this->room->getLastMessageId();
		if ($setToMessage === 0) {
			/**
			 * Frontend and Desktop don't get chat context with ID 0,
			 * so we collectively tested and decided that @see ChatManager::UNREAD_FIRST_MESSAGE
			 * should be used instead.
			 */
			$setToMessage = ChatManager::UNREAD_FIRST_MESSAGE;
		}

		if ($setToMessage === $this->room->getLastMessageId()
			&& $this->participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
			$this->notifier->markMentionNotificationsRead($this->room, $this->participant->getAttendee()->getActorId());
		}

		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController::class);
			return $proxy->setReadMarker($this->room, $this->participant, $this->getResponseFormat(), $lastReadMessage);
		}

		$this->participantService->updateLastReadMessage($this->participant, $setToMessage);
		$attendee = $this->participant->getAttendee();

		$headers = $lastCommonRead = [];
		if ($attendee->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$lastCommonRead[$this->room->getId()] = $this->chatManager->getLastCommonReadMessage($this->room);
			$headers = ['X-Chat-Last-Common-Read' => (string)$lastCommonRead[$this->room->getId()]];
		}

		return new DataResponse($this->roomFormatter->formatRoom(
			$this->getResponseFormat(),
			$lastCommonRead,
			$this->room,
			$this->participant,
		), Http::STATUS_OK, $headers);
	}

	/**
	 * Mark a chat as unread
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{X-Chat-Last-Common-Read?: numeric-string}>
	 *
	 * 200: Read marker set successfully
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireAuthenticatedParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/chat/{token}/read', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function markUnread(): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController::class);
			return $proxy->markUnread($this->room, $this->participant, $this->getResponseFormat());
		}

		$message = $this->room->getLastMessage();
		if ($message instanceof IComment) {
			try {
				$previousMessage = $this->chatManager->getPreviousMessageWithVerb(
					$this->room,
					(int)$message->getId(),
					[ChatManager::VERB_MESSAGE, ChatManager::VERB_OBJECT_SHARED],
					$message->getVerb() === ChatManager::VERB_MESSAGE || $message->getVerb() === ChatManager::VERB_OBJECT_SHARED
				);
				return $this->setReadMarker((int)$previousMessage->getId());
			} catch (NotFoundException) {
				// No chat message found, try system messages …
			}

			try {
				$messages = $this->chatManager->getHistory(
					$this->room,
					(int)$message->getId(),
					1,
					false,
				);

				if (empty($messages)) {
					throw new NotFoundException('No comments found');
				}

				$previousMessage = array_pop($messages);
				return $this->setReadMarker((int)$previousMessage->getId());
			} catch (NotFoundException) {
				/**
				 * Neither system messages found, fall back to `-1`.
				 * This can happen when you:
				 * - Set up message expiration
				 * - Clear the chat history afterwards
				 */
			}
		}

		return $this->setReadMarker(ChatManager::UNREAD_FIRST_MESSAGE);
	}

	/**
	 * Get objects that are shared in the room overview
	 *
	 * @param int<1, 20> $limit Maximum number of objects
	 * @return DataResponse<Http::STATUS_OK, array<string, list<TalkChatMessage>>, array{}>
	 *
	 * 200: List of shared objects messages of each type returned
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/{token}/share/overview', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
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
			$messageIdsByType[$objectType] = array_map(static fn (Attachment $attachment): string => (string)$attachment->getMessageId(), $attachments);
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
	 * @param int<1, 200> $limit Maximum number of objects
	 * @return DataResponse<Http::STATUS_OK, array<string, TalkChatMessage>, array{X-Chat-Last-Given?: numeric-string}>
	 *
	 * 200: List of shared objects messages returned
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/{token}/share', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function getObjectsSharedInRoom(string $objectType, int $lastKnownMessageId = 0, int $limit = 100): DataResponse {
		$offset = max(0, $lastKnownMessageId);
		$limit = min(200, $limit);

		$attachments = $this->attachmentService->getAttachmentsByType($this->room, $objectType, $offset, $limit);
		$messageIds = array_map(static fn (Attachment $attachment): int => $attachment->getMessageId(), $attachments);

		$messages = $this->getMessagesForRoom($messageIds);

		$headers = [];
		if (!empty($messages)) {
			$newLastKnown = (string)(int)min(array_keys($messages));
			$headers = ['X-Chat-Last-Given' => $newLastKnown];
		}

		return new DataResponse($messages, Http::STATUS_OK, $headers);
	}

	/**
	 * @return array<string, TalkChatMessage>
	 */
	protected function getMessagesForRoom(array $messageIds): array {
		$comments = $this->chatManager->getMessagesForRoomById($this->room, $messageIds);
		$this->sharePreloader->preloadShares($comments);
		$potentialThreadIds = array_map(static fn (IComment $comment) => (int)$comment->getTopmostParentId() ?: (int)$comment->getId(), $comments);
		$threads = $this->threadService->findByThreadIds($this->room->getId(), $potentialThreadIds);

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

			$threadId = (int)$comment->getTopmostParentId() ?: (int)$comment->getId();
			$messages[$comment->getId()] = $message->toArray($this->getResponseFormat(), $threads[$threadId] ?? null);
		}

		return $messages;
	}

	/**
	 * Search for mentions
	 *
	 * @param string $search Text to search for
	 * @param int $limit Maximum number of results
	 * @param bool $includeStatus Include the user statuses
	 * @return DataResponse<Http::STATUS_OK, list<TalkChatMentionSuggestion>, array{}>
	 *
	 * 200: List of mention suggestions returned
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/{token}/mentions', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function mentions(string $search, int $limit = 20, bool $includeStatus = false): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController::class);
			return $proxy->mentions($this->room, $this->participant, $search, $limit, $includeStatus);
		}
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
		/** @psalm-suppress InvalidArgument */
		$this->autoCompleteManager->runSorters(['talk_chat_participants'], $results, [
			'itemType' => 'chat',
			'itemId' => (string)$this->room->getId(),
			'search' => $search,
			'selfUserId' => $this->userId,
			'selfCloudId' => $this->userId === null ? $this->federationAuthenticator->getCloudId() : null,
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
	 * @param array<string, IUserStatus> $statuses
	 * @return list<TalkChatMentionSuggestion>
	 */
	protected function prepareResultArray(array $results, array $statuses): array {
		$output = [];
		foreach ($results as $type => $subResult) {
			foreach ($subResult as $result) {
				$data = [
					'id' => $result['value']['shareWith'],
					'label' => $result['label'],
					'source' => $type,
					'mentionId' => $this->createMentionString($type, $result['value']['shareWith']),
				];

				if ($type === Attendee::ACTOR_USERS && isset($statuses[$data['id']])) {
					$data['status'] = $statuses[$data['id']]->getStatus();
					$data['statusIcon'] = $statuses[$data['id']]->getIcon();
					$data['statusMessage'] = $statuses[$data['id']]->getMessage();
					$data['statusClearAt'] = $statuses[$data['id']]->getClearAt()?->getTimestamp();
				}

				if ($type === Attendee::ACTOR_EMAILS && isset($result['details']) && $this->participant->hasModeratorPermissions()) {
					$data['details'] = $result['details']['email'];
				}

				$output[] = $data;
			}
		}
		return $output;
	}

	protected function createMentionString(string $type, string $id): string {
		if ($type !== Attendee::ACTOR_FEDERATED_USERS) {
			return $id;
		}

		// We want "federated_user/admin@example.tld" so we have to strip off the trailing "s" from the type "federated_users"
		return substr($type, 0, -1) . '/' . $id;
	}
}
