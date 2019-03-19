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

namespace OCA\Spreed\Controller;

use OCA\Spreed\Chat\AutoComplete\SearchPlugin;
use OCA\Spreed\Chat\AutoComplete\Sorter;
use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Chat\MessageParser;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Room;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Comments\IComment;
use OCP\Comments\MessageTooLongException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;

class ChatController extends AEnvironmentAwareController {

	/** @var string */
	private $userId;

	/** @var IUserManager */
	private $userManager;

	/** @var TalkSession */
	private $session;

	/** @var ChatManager */
	private $chatManager;

	/** @var GuestManager */
	private $guestManager;

	/** @var string[] */
	protected $guestNames;

	/** @var MessageParser */
	private $messageParser;

	/** @var IManager */
	private $autoCompleteManager;

	/** @var SearchPlugin */
	private $searchPlugin;

	/** @var ISearchResult */
	private $searchResult;

	/** @var IL10N */
	private $l;
	/** @var ITimeFactory */
	protected $timeFactory;

	public function __construct(string $appName,
								?string $UserId,
								IRequest $request,
								IUserManager $userManager,
								TalkSession $session,
								ChatManager $chatManager,
								GuestManager $guestManager,
								MessageParser $messageParser,
								IManager $autoCompleteManager,
								SearchPlugin $searchPlugin,
								ISearchResult $searchResult,
								ITimeFactory $timeFactory,
								IL10N $l) {
		parent::__construct($appName, $request);

		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->session = $session;
		$this->chatManager = $chatManager;
		$this->guestManager = $guestManager;
		$this->messageParser = $messageParser;
		$this->autoCompleteManager = $autoCompleteManager;
		$this->searchPlugin = $searchPlugin;
		$this->searchResult = $searchResult;
		$this->timeFactory = $timeFactory;
		$this->l = $l;
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 *
	 * Sends a new chat message to the given room.
	 *
	 * The author and timestamp are automatically set to the current user/guest
	 * and time.
	 *
	 * @param string $message the message to send
	 * @param string $actorDisplayName for guests
	 * @return DataResponse the status code is "201 Created" if successful, and
	 *         "404 Not found" if the room or session for a guest user was not
	 *         found".
	 */
	public function sendMessage(string $message, string $actorDisplayName = ''): DataResponse {

		if ($this->userId === null) {
			$actorType = 'guests';
			$sessionId = $this->session->getSessionForRoom($this->room->getToken());
			// The character limit for actorId is 64, but the spreed-session is
			// 256 characters long, so it has to be hashed to get an ID that
			// fits (except if there is no session, as the actorId should be
			// empty in that case but sha1('') would generate a hash too
			// instead of returning an empty string).
			$actorId = $sessionId ? sha1($sessionId) : 'failed-to-get-session';

			if ($sessionId && $actorDisplayName) {
				$this->guestManager->updateName($this->room, $sessionId, $actorDisplayName);
			}
		} else {
			$actorType = 'users';
			$actorId = $this->userId;
		}

		if (!$actorId) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$this->room->ensureOneToOneRoomIsFilled();
		$creationDateTime = $this->timeFactory->getDateTime('now', new \DateTimeZone('UTC'));

		try {
			$comment = $this->chatManager->sendMessage($this->room, $this->participant, $actorType, $actorId, $message, $creationDateTime);
		} catch (MessageTooLongException $e) {
			return new DataResponse([], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$chatMessage = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
		$this->messageParser->parseMessage($chatMessage);

		if (!$chatMessage->getVisibility()) {
			return new DataResponse([], Http::STATUS_CREATED);
		}

		return new DataResponse([
			'id' => (int) $comment->getId(),
			'token' => $this->room->getToken(),
			'actorType' => $chatMessage->getActorType(),
			'actorId' => $chatMessage->getActorId(),
			'actorDisplayName' => $chatMessage->getActorDisplayName(),
			'timestamp' => $comment->getCreationDateTime()->getTimestamp(),
			'message' => $chatMessage->getMessage(),
			'messageParameters' => $chatMessage->getMessageParameters(),
			'systemMessage' => $chatMessage->getMessageType() === 'system' ? $comment->getMessage() : '',
		], Http::STATUS_CREATED);
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 *
	 * Receives chat messages from the given room.
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
	 * @param int $lookIntoFuture Polling for new messages (1) or getting the history of the chat (0)
	 * @param int $limit Number of chat messages to receive (100 by default, 200 at most)
	 * @param int $lastKnownMessageId The last known message (serves as offset)
	 * @param int $timeout Number of seconds to wait for new messages (30 by default, 30 at most)
	 * @return DataResponse an array of chat messages, "404 Not found" if the
	 *         room token was not valid or "304 Not modified" if there were no messages;
	 *         each chat message is an array with
	 *         fields 'id', 'token', 'actorType', 'actorId',
	 *         'actorDisplayName', 'timestamp' (in seconds and UTC timezone) and
	 *         'message'.
	 */
	public function receiveMessages(int $lookIntoFuture, int $limit = 100, int $lastKnownMessageId = 0, int $timeout = 30): DataResponse {
		$limit = min(200, $limit);
		$timeout = min(30, $timeout);

		if ($this->participant->getSessionId() !== '0') {
			$this->room->ping($this->participant->getUser(), $this->participant->getSessionId(), $this->timeFactory->getTime());
		}

		$currentUser = $this->userManager->get($this->userId);
		if ($lookIntoFuture) {
			$comments = $this->chatManager->waitForNewMessages($this->room, $lastKnownMessageId, $limit, $timeout, $currentUser);
		} else {
			$comments = $this->chatManager->getHistory($this->room, $lastKnownMessageId, $limit);
		}

		if (empty($comments)) {
			return new DataResponse([], Http::STATUS_NOT_MODIFIED);
		}

		$messages = [];
		foreach ($comments as $comment) {
			$message = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
			$this->messageParser->parseMessage($message);

			if (!$message->getVisibility()) {
				continue;
			}

			$messages[] = [
				'id' => (int) $comment->getId(),
				'token' => $this->room->getToken(),
				'actorType' => $message->getActorType(),
				'actorId' => $message->getActorId(),
				'actorDisplayName' => $message->getActorDisplayName(),
				'timestamp' => $comment->getCreationDateTime()->getTimestamp(),
				'message' => $message->getMessage(),
				'messageParameters' => $message->getMessageParameters(),
				'systemMessage' => $message->getMessageType() === 'system' ? $comment->getMessage() : '',
			];
		}


		$response = new DataResponse($messages, Http::STATUS_OK);

		$newLastKnown = end($comments);
		if ($newLastKnown instanceof IComment) {
			$response->addHeader('X-Chat-Last-Given', $newLastKnown->getId());
		}

		return $response;
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 *
	 * @param string $search
	 * @param int $limit
	 * @return DataResponse
	 */
	public function mentions(string $search, int $limit = 20): DataResponse {
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
		]);

		$results = $this->prepareResultArray($results);

		if (($search === '' || strpos('all', $search) !== false) && $this->room->getType() !== Room::ONE_TO_ONE_CALL) {
			array_unshift($results, [
				'id' => 'all',
				'label' => $this->room->getDisplayName($this->participant->getUser()),
				'source' => 'calls',
			]);
		}

		return new DataResponse($results);
	}


	protected function prepareResultArray(array $results): array {
		$output = [];
		foreach ($results as $type => $subResult) {
			foreach ($subResult as $result) {
				$output[] = [
					'id' => $result['value']['shareWith'],
					'label' => $result['label'],
					'source' => $type,
				];
			}
		}
		return $output;
	}
}
