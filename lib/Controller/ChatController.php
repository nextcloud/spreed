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

namespace OCA\Talk\Controller;

use OCA\Talk\Chat\AutoComplete\SearchPlugin;
use OCA\Talk\Chat\AutoComplete\Sorter;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\SessionService;
use OCA\Talk\TalkSession;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
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
use OCP\User\Events\UserLiveStatusEvent;
use OCP\UserStatus\IManager as IUserStatusManager;
use OCP\UserStatus\IUserStatus;

class ChatController extends AEnvironmentAwareController {

	/** @var string */
	private $userId;

	/** @var IUserManager */
	private $userManager;

	/** @var TalkSession */
	private $session;

	/** @var IAppManager */
	private $appManager;

	/** @var ChatManager */
	private $chatManager;

	/** @var ParticipantService */
	private $participantService;

	/** @var SessionService */
	private $sessionService;

	/** @var GuestManager */
	private $guestManager;

	/** @var string[] */
	protected $guestNames;

	/** @var MessageParser */
	private $messageParser;

	/** @var IManager */
	private $autoCompleteManager;

	/** @var IUserStatusManager */
	private $statusManager;

	/** @var SearchPlugin */
	private $searchPlugin;

	/** @var ISearchResult */
	private $searchResult;

	/** @var ITimeFactory */
	protected $timeFactory;

	/** @var IEventDispatcher */
	protected $eventDispatcher;

	/** @var IL10N */
	private $l;

	public function __construct(string $appName,
								?string $UserId,
								IRequest $request,
								IUserManager $userManager,
								TalkSession $session,
								IAppManager $appManager,
								ChatManager $chatManager,
								ParticipantService $participantService,
								SessionService $sessionService,
								GuestManager $guestManager,
								MessageParser $messageParser,
								IManager $autoCompleteManager,
								IUserStatusManager $statusManager,
								SearchPlugin $searchPlugin,
								ISearchResult $searchResult,
								ITimeFactory $timeFactory,
								IEventDispatcher $eventDispatcher,
								IL10N $l) {
		parent::__construct($appName, $request);

		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->session = $session;
		$this->appManager = $appManager;
		$this->chatManager = $chatManager;
		$this->participantService = $participantService;
		$this->sessionService = $sessionService;
		$this->guestManager = $guestManager;
		$this->messageParser = $messageParser;
		$this->autoCompleteManager = $autoCompleteManager;
		$this->statusManager = $statusManager;
		$this->searchPlugin = $searchPlugin;
		$this->searchResult = $searchResult;
		$this->timeFactory = $timeFactory;
		$this->eventDispatcher = $eventDispatcher;
		$this->l = $l;
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * Sends a new chat message to the given room.
	 *
	 * The author and timestamp are automatically set to the current user/guest
	 * and time.
	 *
	 * @param string $message the message to send
	 * @param string $actorDisplayName for guests
	 * @param string $referenceId for the message to be able to later identify it again
	 * @param int $replyTo Parent id which this message is a reply to
	 * @return DataResponse the status code is "201 Created" if successful, and
	 *         "404 Not found" if the room or session for a guest user was not
	 *         found".
	 */
	public function sendMessage(string $message, string $actorDisplayName = '', string $referenceId = '', int $replyTo = 0): DataResponse {
		if ($this->userId === null) {
			$actorType = Attendee::ACTOR_GUESTS;
			$sessionId = $this->session->getSessionForRoom($this->room->getToken());
			// The character limit for actorId is 64, but the spreed-session is
			// 256 characters long, so it has to be hashed to get an ID that
			// fits (except if there is no session, as the actorId should be
			// empty in that case but sha1('') would generate a hash too
			// instead of returning an empty string).
			$actorId = $sessionId ? sha1($sessionId) : 'failed-to-get-session';

			if ($sessionId && $actorDisplayName) {
				$this->guestManager->updateName($this->room, $this->participant, $actorDisplayName);
			}
		} else {
			$actorType = Attendee::ACTOR_USERS;
			$actorId = $this->userId;
		}

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
			$comment = $this->chatManager->sendMessage($this->room, $this->participant, $actorType, $actorId, $message, $creationDateTime, $parent, $referenceId);
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

		$this->participantService->updateLastReadMessage($this->participant, (int) $comment->getId());

		$data = $chatMessage->toArray();
		if ($parentMessage instanceof Message) {
			$data['parent'] = $parentMessage->toArray();
		}
		return new DataResponse($data, Http::STATUS_CREATED);
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireModeratorOrNoLobby
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
	 * The limit specifies the maximum number of messages that will be returned,
	 * although the actual number of returned messages could be lower if some
	 * messages are not visible to the participant. Note that if none of the
	 * messages are visible to the participant the returned number of messages
	 * will be 0, yet the status will still be 200. Also note that
	 * `X-Chat-Last-Given` may reference a message not visible and thus not
	 * returned, but it should be used nevertheless as the $lastKnownMessageId
	 * for the follow up query.
	 *
	 * @param int $lookIntoFuture Polling for new messages (1) or getting the history of the chat (0)
	 * @param int $limit Number of chat messages to receive (100 by default, 200 at most)
	 * @param int $lastKnownMessageId The last known message (serves as offset)
	 * @param int $timeout Number of seconds to wait for new messages (30 by default, 30 at most)
	 * @param int $setReadMarker Automatically set the last read marker when 1,
	 *                           if your client does this itself via chat/{token}/read set to 0
	 * @param int $includeLastKnown Include the $lastKnownMessageId in the messages when 1 (default 0)
	 * @param int $noStatusUpdate When the user status should not be automatically set to online set to 1 (default 0)
	 * @return DataResponse an array of chat messages, "404 Not found" if the
	 *         room token was not valid or "304 Not modified" if there were no messages;
	 *         each chat message is an array with
	 *         fields 'id', 'token', 'actorType', 'actorId',
	 *         'actorDisplayName', 'timestamp' (in seconds and UTC timezone) and
	 *         'message'.
	 */
	public function receiveMessages(int $lookIntoFuture, int $limit = 100, int $lastKnownMessageId = 0, int $timeout = 30, int $setReadMarker = 1, int $includeLastKnown = 0, int $noStatusUpdate = 0): DataResponse {
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
			$comments = $this->chatManager->waitForNewMessages($this->room, $lastKnownMessageId, $limit, $timeout, $currentUser, (bool) $includeLastKnown);
		} else {
			$comments = $this->chatManager->getHistory($this->room, $lastKnownMessageId, $limit, (bool) $includeLastKnown);
		}

		if (empty($comments)) {
			return new DataResponse([], Http::STATUS_NOT_MODIFIED);
		}

		$i = 0;
		$messages = $commentIdToIndex = $parentIds = [];
		foreach ($comments as $comment) {
			$id = (int) $comment->getId();
			$message = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
			$this->messageParser->parseMessage($message);

			if (!$message->getVisibility()) {
				$commentIdToIndex[$id] = null;
				continue;
			}

			if ($comment->getParentId() !== '0') {
				$parentIds[$id] = $comment->getParentId();
			}

			$messages[] = $message->toArray();
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
						$loadedParents[$parentId] = $message->toArray();
						$messages[$commentKey]['parent'] = $loadedParents[$parentId];
						continue;
					}

					$loadedParents[$parentId] = [
						'id' => $parentId,
						'deleted' => true,
					];
				} catch (NotFoundException $e) {
				}
			}

			// Message is not visible to the user
			$messages[$commentKey]['parent'] = [
				'id' => $parentId,
				'deleted' => true,
			];
		}

		$response = new DataResponse($messages, Http::STATUS_OK);

		$newLastKnown = end($comments);
		if ($newLastKnown instanceof IComment) {
			$response->addHeader('X-Chat-Last-Given', $newLastKnown->getId());
			/**
			 * This falsely set the read marker on new messages although you
			 * navigated away to a different chat already. So we removed this
			 * and instead update the read marker before your next waiting.
			 * So when you are still there, it will just have a wrong read
			 * marker for the time until your next request starts, while it will
			 * not update the value, when you actually left the chat already.
			 * if ($setReadMarker === 1 && $lookIntoFuture) {
			 * $this->participantService->updateLastReadMessage($this->participant, (int) $newLastKnown->getId());
			 * }
			 */
		}

		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @RequireParticipant
	 *
	 * @param int $lastReadMessage
	 * @return DataResponse
	 */
	public function setReadMarker(int $lastReadMessage): DataResponse {
		$this->participantService->updateLastReadMessage($this->participant, $lastReadMessage);
		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * @param string $search
	 * @param int $limit
	 * @param bool $includeStatus
	 * @return DataResponse
	 */
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

		$attendee = $this->participant->getAttendee();
		$userId = $attendee->getActorType() === Attendee::ACTOR_USERS ? $attendee->getActorId() : '';
		$roomDisplayName = $this->room->getDisplayName($userId);
		if (($search === '' || strpos('all', $search) !== false || stripos($roomDisplayName, $search) !== false) && $this->room->getType() !== Room::ONE_TO_ONE_CALL) {
			if ($search === '' ||
				stripos($roomDisplayName, $search) === 0 ||
				strpos('all', $search) === 0) {
				array_unshift($results, [
					'id' => 'all',
					'label' => $roomDisplayName,
					'source' => 'calls',
				]);
			} else {
				$results[] = [
					'id' => 'all',
					'label' => $roomDisplayName,
					'source' => 'calls',
				];
			}
		}

		return new DataResponse($results);
	}


	/**
	 * @param array $results
	 * @param IUserStatus[] $statuses
	 * @return array
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
