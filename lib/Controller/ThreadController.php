<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Manager;
use OCA\Talk\Middleware\Attribute\FederationSupported;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Middleware\Attribute\RequirePermission;
use OCA\Talk\Middleware\Attribute\RequireReadWriteConversation;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Thread;
use OCA\Talk\Model\ThreadAttendee;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ThreadService;
use OCA\Talk\Share\Helper\Preloader;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\NotFoundException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkThreadInfo from ResponseDefinitions
 */
class ThreadController extends AEnvironmentAwareOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected Manager $manager,
		protected ChatManager $chatManager,
		protected Preloader $sharePreloader,
		protected MessageParser $messageParser,
		protected ParticipantService $participantService,
		protected ThreadService $threadService,
		protected ITimeFactory $timeFactory,
		protected IL10N $l,
		protected IEventDispatcher $eventDispatcher,
		protected LoggerInterface $logger,
		protected ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get recent active threads in a conversation
	 *
	 * Required capability: `threads`
	 *
	 * @param int<1, 50> $limit Number of threads to return
	 * @return DataResponse<Http::STATUS_OK, list<TalkThreadInfo>, array{}>
	 *
	 * 200: List of threads returned
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/{token}/threads/recent', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function getRecentActiveThreads(int $limit = 50): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ThreadController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ThreadController::class);
			return $proxy->getRecentActiveThreads($this->room, $this->participant, $limit);
		}

		$threads = $this->threadService->getRecentByRoomId($this->room, $limit);
		$list = $this->prepareListOfThreads($threads);
		return new DataResponse($list);
	}


	/**
	 * Get subscribed threads for a user
	 *
	 * Required capability: `threads`
	 *
	 * @param int<1, 100> $limit Number of threads to return
	 * @param non-negative-int $offset Offset in the threads list
	 * @return DataResponse<Http::STATUS_OK, list<TalkThreadInfo>, array{}>
	 *
	 * 200: List of threads returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/subscribed-threads', requirements: [
		'apiVersion' => '(v1)',
	])]
	public function getSubscribedThreads(int $limit = 100, int $offset = 0): DataResponse {
		$results = $this->threadService->getRecentByActor(Attendee::ACTOR_USERS, $this->userId, $limit, $offset);

		$roomIds = array_keys($results);
		$rooms = $this->manager->getRoomsByIdForUser($roomIds, $this->userId);

		$threads = $threadAttendees = [];
		foreach ($results as $roomId => $data) {
			if (!isset($rooms[$roomId])) {
				continue;
			}

			foreach ($data as $threadData) {
				$threads[] = $threadData['thread'];
				$threadAttendees[$threadData['thread']->getId()] = $threadData['attendee'];
			}
		}

		// Sort by last activity again
		usort($threads, static function (Thread $a, Thread $b): int {
			if ($b->getLastActivity() === $a->getLastActivity()) {
				return $b->getId() <=> $a->getId();
			}
			return $b->getLastActivity() <=> $a->getLastActivity();
		});

		return new DataResponse($this->prepareListOfThreads($threads, $threadAttendees, $rooms));
	}

	/**
	 * Get thread info of a single thread
	 *
	 * Required capability: `threads`
	 *
	 * @param int $threadId The thread ID to get the info for
	 * @psalm-param non-negative-int $threadId
	 * @return DataResponse<Http::STATUS_OK, TalkThreadInfo, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: 'thread'|'status'}, array{}>
	 *
	 * 200: Thread info returned
	 * 404: Thread not found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/{token}/threads/{threadId}', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
		'threadId' => '[0-9]+',
	])]
	public function getThread(int $threadId): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ThreadController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ThreadController::class);
			return $proxy->getThread($this->room, $this->participant, $threadId);
		}

		try {
			$thread = $this->threadService->findByThreadId($this->room->getId(), $threadId);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'thread'], Http::STATUS_NOT_FOUND);
		}

		$list = $this->prepareListOfThreads([$thread]);
		/** @var TalkThreadInfo $threadInfo */
		$threadInfo = array_shift($list);
		return new DataResponse($threadInfo);
	}

	/**
	 * Rename a thread
	 *
	 * Required capability: `threads`
	 *
	 * @param int $threadId The thread ID to get the info for
	 * @psalm-param non-negative-int $threadId
	 * @param string $threadTitle New thread title, must not be empty
	 * @return DataResponse<Http::STATUS_OK, TalkThreadInfo, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'title'}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{error: 'permission'}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: 'thread'}, array{}>
	 *
	 * 200: Thread renamed successfully
	 * 400: When the provided title is empty
	 * 403: Not allowed, either not the original author or not a moderator
	 * 404: Thread not found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/chat/{token}/threads/{threadId}', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
		'threadId' => '[0-9]+',
	])]
	public function renameThread(int $threadId, string $threadTitle): DataResponse {
		$threadTitle = trim($threadTitle);
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ThreadController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ThreadController::class);
			return $proxy->renameThread($this->room, $this->participant, $threadId, $threadTitle);
		}

		try {
			$thread = $this->threadService->findByThreadId($this->room->getId(), $threadId);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'thread'], Http::STATUS_NOT_FOUND);
		}

		$attendee = $this->participant->getAttendee();
		$isOwnMessage = false;
		try {
			$comment = $this->chatManager->getComment($this->room, (string)$threadId);
			$isOwnMessage = $comment->getActorType() === $attendee->getActorType()
				&& $comment->getActorId() === $attendee->getActorId();
		} catch (NotFoundException) {
			// Root message expired, only moderators can edit
		}

		if (!$isOwnMessage
			&& !$this->participant->hasModeratorPermissions(false)) {
			// Actor is not a moderator or not the owner of the message
			return new DataResponse(['error' => 'permission'], Http::STATUS_FORBIDDEN);
		}

		try {
			$this->threadService->renameThread($thread, $threadTitle);
		} catch (\InvalidArgumentException) {
			return new DataResponse(['error' => 'title'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$comment = $this->chatManager->getComment($this->room, (string)$threadId);
		} catch (NotFoundException) {
			// Root message expired, continuing without replying
			$comment = null;
		}

		$this->chatManager->addSystemMessage(
			$this->room,
			$this->participant,
			$this->participant->getAttendee()->getActorType(),
			$this->participant->getAttendee()->getActorId(),
			json_encode(['message' => 'thread_renamed', 'parameters' => ['thread' => $threadId, 'title' => $thread->getName()]]),
			$this->timeFactory->getDateTime(),
			false,
			null,
			$comment,
			true,
			true,
			$threadId,
		);

		$list = $this->prepareListOfThreads([$thread]);
		/** @var TalkThreadInfo $threadInfo */
		$threadInfo = array_shift($list);
		return new DataResponse($threadInfo);
	}

	/**
	 * @param list<Thread> $threads
	 * @param ?list<ThreadAttendee> $attendees
	 * @return list<TalkThreadInfo>
	 */
	protected function prepareListOfThreads(array $threads, ?array $attendees = null, ?array $rooms = null): array {
		$threadIds = array_map(static fn (Thread $thread) => $thread->getId(), $threads);
		if ($attendees === null) {
			$attendees = $this->threadService->findAttendeeByThreadIds($this->participant->getAttendee(), $threadIds);
		}
		if ($rooms === null) {
			$rooms = [$this->room->getId() => $this->room];
			$participants = [$this->room->getId() => $this->participant];
		}

		$messageIds = [];
		foreach ($threads as $thread) {
			$messageIds[] = $thread->getId();
			$messageIds[] = $thread->getLastMessageId();
		}

		$comments = $this->chatManager->getMessagesById($messageIds);
		$this->sharePreloader->preloadShares($comments);

		$list = [];
		foreach ($threads as $thread) {
			if (!isset($rooms[$thread->getRoomId()])) {
				continue;
			}

			$room = $rooms[$thread->getRoomId()];
			// The getParticipant here should read only from the cache, so it's no problem inside the loop
			$participant = $participants[$thread->getRoomId()] ?? $this->participantService->getParticipant($room, $this->userId);

			$firstMessage = $lastMessage = null;
			$attendee = $attendees[$thread->getId()] ?? null;
			if ($attendee === null) {
				$attendee = ThreadAttendee::createFromParticipant($thread->getId(), $participant);
			}

			$first = $comments[$thread->getId()] ?? null;
			if ($first !== null) {
				$firstMessage = $this->messageParser->createMessage($room, $participant, $first, $this->l);
				$this->messageParser->parseMessage($firstMessage);
			}

			$last = $comments[$thread->getLastMessageId()] ?? null;
			if ($last !== null) {
				$lastMessage = $this->messageParser->createMessage($room, $participant, $last, $this->l);
				$this->messageParser->parseMessage($lastMessage);
			}

			$list[] = [
				'thread' => $thread->toArray($room),
				'attendee' => $attendee->jsonSerialize(),
				'first' => $firstMessage?->toArray($this->getResponseFormat(), $thread),
				'last' => $lastMessage?->toArray($this->getResponseFormat(), $thread),
			];
		}

		return $list;
	}

	/**
	 * Set notification level for a specific thread
	 *
	 * Required capability: `threads`
	 *
	 * @param int $messageId The message to create a thread for (Doesn't have to be the root)
	 * @psalm-param non-negative-int $messageId
	 * @param int $level New level
	 * @psalm-param Participant::NOTIFY_* $level
	 * @return DataResponse<Http::STATUS_OK, TalkThreadInfo, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: 'level'|'message'|'status'|'top-most'}, array{}>
	 *
	 * 200: Successfully set notification level for thread
	 * 400: Notification level was invalid
	 * 404: Message or top most message not found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/chat/{token}/threads/{messageId}/notify', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
		'messageId' => '[0-9]+',
	])]
	public function setNotificationLevel(int $messageId, int $level): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ThreadController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ThreadController::class);
			$response = $proxy->setNotificationLevel($this->room, $this->participant, $messageId, $level);

			if ($response->getStatus() === Http::STATUS_OK) {
				// Also save locally, for later handling when receiving a federated message
				$this->threadService->setNotificationLevel($this->participant->getAttendee(), $messageId, $level);
			}

			return $response;
		}

		if (!\in_array($level, [
			Participant::NOTIFY_DEFAULT,
			Participant::NOTIFY_ALWAYS,
			Participant::NOTIFY_MENTION,
			Participant::NOTIFY_NEVER,
		], true)) {
			return new DataResponse(['error' => 'level'], Http::STATUS_BAD_REQUEST);
		}


		try {
			$thread = $this->threadService->findByThreadId($this->room->getId(), $messageId);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'message'], Http::STATUS_NOT_FOUND);
		}

		$threadAttendee = $this->threadService->setNotificationLevel($this->participant->getAttendee(), $thread->getId(), $level);
		$attendees = [$thread->getId() => $threadAttendee];
		$list = $this->prepareListOfThreads([$thread], $attendees);

		/** @var TalkThreadInfo $threadInfo */
		$threadInfo = array_shift($list);
		return new DataResponse($threadInfo);
	}
}
