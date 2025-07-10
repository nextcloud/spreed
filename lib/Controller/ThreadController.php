<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Middleware\Attribute\RequirePermission;
use OCA\Talk\Middleware\Attribute\RequireReadWriteConversation;
use OCA\Talk\Model\Thread;
use OCA\Talk\Model\ThreadAttendee;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\ThreadService;
use OCA\Talk\Share\Helper\Preloader;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\PublicPage;
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
		protected ChatManager $chatManager,
		protected Preloader $sharePreloader,
		protected MessageParser $messageParser,
		protected ThreadService $threadService,
		protected ITimeFactory $timeFactory,
		protected IL10N $l,
		protected IEventDispatcher $eventDispatcher,
		protected LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get recent active threads in a conversation
	 *
	 * @param int<1, 50> $limit Number of threads to return
	 * @return DataResponse<Http::STATUS_OK, list<TalkThreadInfo>, array{}>
	 *
	 * 200: List of threads returned
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/{token}/threads/recent', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function getRecentActiveThreads(int $limit = 50): DataResponse {
		$threads = $this->threadService->getRecentByRoomId($this->room, $limit);
		$list = $this->prepareListOfThreads($threads);
		return new DataResponse($list);
	}

	/**
	 * Get thread info of a single thread
	 *
	 * @param int $threadId The thread ID to get the info for
	 * @psalm-param non-negative-int $threadId
	 * @return DataResponse<Http::STATUS_OK, TalkThreadInfo, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: 'thread'}, array{}>
	 *
	 * 200: Thread info returned
	 * 404: Thread not found
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/{token}/threads/{threadId}', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
		'threadId' => '[0-9]+',
	])]
	public function getThread(int $threadId): DataResponse {
		try {
			$thread = $this->threadService->findByThreadId($threadId);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'thread'], Http::STATUS_NOT_FOUND);
		}

		if ($thread->getRoomId() !== $this->room->getId()) {
			return new DataResponse(['error' => 'thread'], Http::STATUS_NOT_FOUND);
		}

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
	protected function prepareListOfThreads(array $threads, ?array $attendees = null): array {
		$threadIds = array_map(static fn (Thread $thread) => $thread->getId(), $threads);
		if ($attendees === null) {
			$attendees = $this->threadService->findAttendeeByThreadIds($this->participant->getAttendee(), $threadIds);
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
			$firstMessage = $lastMessage = null;
			$attendee = $attendees[$thread->getId()] ?? null;

			$first = $comments[$thread->getId()] ?? null;
			if ($first !== null) {
				$firstMessage = $this->messageParser->createMessage($this->room, $this->participant, $first, $this->l);
				$this->messageParser->parseMessage($firstMessage);
			}

			$last = $comments[$thread->getLastMessageId()] ?? null;
			if ($last !== null) {
				$lastMessage = $this->messageParser->createMessage($this->room, $this->participant, $last, $this->l);
				$this->messageParser->parseMessage($lastMessage);
			}

			$list[] = [
				'thread' => $thread->jsonSerialize(),
				'attendee' => $attendee?->jsonSerialize(),
				'first' => $firstMessage?->toArray($this->getResponseFormat()),
				'last' => $lastMessage?->toArray($this->getResponseFormat()),
			];
		}

		return $list;
	}

	/**
	 * Create a thread out of a message or reply chain
	 *
	 * @param int $messageId The message to create a thread for (Doesn't have to be the root)
	 * @psalm-param non-negative-int $messageId
	 * @return DataResponse<Http::STATUS_OK, TalkThreadInfo, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: 'message'|'top-most'}, array{}>
	 *
	 * 200: Thread successfully created
	 * 400: Root message is a system message and therefor not supported
	 * 404: Message or top most message not found
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/chat/{token}/threads/{messageId}', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
		'messageId' => '[0-9]+',
	])]
	public function makeThread(int $messageId): DataResponse {
		try {
			// Todo: What if the root already expired
			$comment = $this->chatManager->getTopMostComment($this->room, (string)$messageId);
		} catch (NotFoundException) {
			return new DataResponse(['error' => 'message'], Http::STATUS_NOT_FOUND);
		}

		$threadId = (int)$comment->getId();

		$threadMessage = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
		$this->messageParser->parseMessage($threadMessage);
		if ($threadMessage->getMessageType() === ChatManager::VERB_SYSTEM) {
			return new DataResponse(['error' => 'message'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$thread = $this->threadService->findByThreadId($threadId);
			$threadAttendee = $this->threadService->addAttendeeToThread($this->participant->getAttendee(), $thread);
			$attendees = [$thread->getId() => $threadAttendee];

			$list = $this->prepareListOfThreads([$thread], $attendees);
			/** @var TalkThreadInfo $threadInfo */
			$threadInfo = array_shift($list);
			return new DataResponse($threadInfo);
		} catch (DoesNotExistException) {
		}

		$thread = $this->threadService->createThread($this->room, $threadId);
		$this->chatManager->addSystemMessage(
			$this->room,
			$this->participant->getAttendee()->getActorType(),
			$this->participant->getAttendee()->getActorId(),
			json_encode(['message' => 'thread_created', 'parameters' => ['thread' => $threadId]]),
			$this->timeFactory->getDateTime(),
			false,
			null,
			$comment,
			true,
			true
		);

		$threadAttendee = $this->threadService->addAttendeeToThread($this->participant->getAttendee(), $thread);
		$attendees = [$thread->getId() => $threadAttendee];

		$list = $this->prepareListOfThreads([$thread], $attendees);
		/** @var TalkThreadInfo $threadInfo */
		$threadInfo = array_shift($list);
		return new DataResponse($threadInfo);
	}
}
