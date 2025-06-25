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
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\ThreadService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
use OCP\AppFramework\Http\DataResponse;
use OCP\Comments\NotFoundException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkThread from ResponseDefinitions
 */
class ThreadController extends AEnvironmentAwareOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected ChatManager $chatManager,
		protected MessageParser $messageParser,
		protected ThreadService $threadService,
		protected IL10N $l,
		protected IEventDispatcher $eventDispatcher,
		protected LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get list of threads in a conversation
	 *
	 * @param int<1, 50> $limit Number of threads to return
	 * @param int $offsetId The last thread ID that was known, default 0 starts from the newest
	 * @psalm-param non-negative-int $offsetId
	 * @return DataResponse<Http::STATUS_OK, list<TalkThread>, array{}>
	 *
	 * 200: List of threads returned
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/chat/{token}/threads', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function listThreads(int $limit = 25, int $offsetId = 0): DataResponse {
		$threads = $this->threadService->findByRoom($this->room, $limit, $offsetId);
		$list = array_map(static fn (Thread $thread) => $thread->jsonSerialize(), $threads);
		return new DataResponse($list);
	}

	/**
	 * Create a thread out of a message or reply chain
	 *
	 * @param int $messageId The message (or a child) to create a thread for
	 * @psalm-param non-negative-int $messageId
	 * @return DataResponse<Http::STATUS_OK, TalkThread, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: 'message'|'top-most'}, array{}>
	 *
	 * 200: Thread successfully created
	 * 400: Root message is a system message and therefor not supported
	 * 404: Message or top most message not found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::CHAT)]
	#[RequireReadWriteConversation]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
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
			$this->threadService->addAttendeeToThread($this->participant->getAttendee(), $thread);
			return new DataResponse($thread->jsonSerialize(), Http::STATUS_OK);
		} catch (DoesNotExistException) {
		}

		$thread = $this->threadService->createThread($this->room, $threadId);
		$this->threadService->addAttendeeToThread($this->participant->getAttendee(), $thread);
		return new DataResponse($thread->jsonSerialize(), Http::STATUS_OK);
	}
}
