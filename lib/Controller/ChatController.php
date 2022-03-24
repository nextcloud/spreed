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
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\SessionService;
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
use OCP\RichObjectStrings\InvalidObjectExeption;
use OCP\RichObjectStrings\IValidator;
use OCP\Security\ITrustedDomainHelper;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\User\Events\UserLiveStatusEvent;
use OCP\UserStatus\IManager as IUserStatusManager;
use OCP\UserStatus\IUserStatus;

class ChatController extends AEnvironmentAwareController {

	/** @var string */
	private $userId;

	/** @var IUserManager */
	private $userManager;

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

	/** @var MatterbridgeManager */
	protected $matterbridgeManager;

	/** @var SearchPlugin */
	private $searchPlugin;

	/** @var ISearchResult */
	private $searchResult;

	/** @var ITimeFactory */
	protected $timeFactory;

	/** @var IEventDispatcher */
	protected $eventDispatcher;

	/** @var IValidator */
	protected $richObjectValidator;

	/** @var ITrustedDomainHelper */
	protected $trustedDomainHelper;

	/** @var IL10N */
	private $l;

	public function __construct(string $appName,
								?string $UserId,
								IRequest $request,
								IUserManager $userManager,
								IAppManager $appManager,
								ChatManager $chatManager,
								ParticipantService $participantService,
								SessionService $sessionService,
								GuestManager $guestManager,
								MessageParser $messageParser,
								IManager $autoCompleteManager,
								IUserStatusManager $statusManager,
								MatterbridgeManager $matterbridgeManager,
								SearchPlugin $searchPlugin,
								ISearchResult $searchResult,
								ITimeFactory $timeFactory,
								IEventDispatcher $eventDispatcher,
								IValidator $richObjectValidator,
								ITrustedDomainHelper $trustedDomainHelper,
								IL10N $l) {
		parent::__construct($appName, $request);

		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->appManager = $appManager;
		$this->chatManager = $chatManager;
		$this->participantService = $participantService;
		$this->sessionService = $sessionService;
		$this->guestManager = $guestManager;
		$this->messageParser = $messageParser;
		$this->autoCompleteManager = $autoCompleteManager;
		$this->statusManager = $statusManager;
		$this->matterbridgeManager = $matterbridgeManager;
		$this->searchPlugin = $searchPlugin;
		$this->searchResult = $searchResult;
		$this->timeFactory = $timeFactory;
		$this->eventDispatcher = $eventDispatcher;
		$this->richObjectValidator = $richObjectValidator;
		$this->trustedDomainHelper = $trustedDomainHelper;
		$this->l = $l;
	}

	protected function getActorInfo(string $actorDisplayName = ''): array {
		if ($this->userId === null) {
			$actorType = Attendee::ACTOR_GUESTS;
			$actorId = $this->participant->getAttendee()->getActorId();

			if ($actorDisplayName) {
				$this->guestManager->updateName($this->room, $this->participant, $actorDisplayName);
			}
		} elseif ($this->userId === MatterbridgeManager::BRIDGE_BOT_USERID && $actorDisplayName) {
			$actorType = Attendee::ACTOR_BRIDGED;
			$actorId = str_replace(["/", "\""], "", $actorDisplayName);
		} else {
			$actorType = Attendee::ACTOR_USERS;
			$actorId = $this->userId;
		}

		return [$actorType, $actorId];
	}

	public function parseCommentToResponse(IComment $comment, Message $parentMessage = null): DataResponse {
		$chatMessage = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
		$this->messageParser->parseMessage($chatMessage);

		if (!$chatMessage->getVisibility()) {
			$response = new DataResponse([], Http::STATUS_CREATED);
			if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
				$response->addHeader('X-Chat-Last-Common-Read', $this->chatManager->getLastCommonReadMessage($this->room));
			}
			return $response;
		}

		$this->participantService->updateLastReadMessage($this->participant, (int) $comment->getId());

		$data = $chatMessage->toArray();
		if ($parentMessage instanceof Message) {
			$data['parent'] = $parentMessage->toArray();
		}

		$response = new DataResponse($data, Http::STATUS_CREATED);
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$response->addHeader('X-Chat-Last-Common-Read', $this->chatManager->getLastCommonReadMessage($this->room));
		}
		return $response;
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
			$comment = $this->chatManager->sendMessage($this->room, $this->participant, $actorType, $actorId, $message, $creationDateTime, $parent, $referenceId);
		} catch (MessageTooLongException $e) {
			return new DataResponse([], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return $this->parseCommentToResponse($comment, $parentMessage);
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * Sends a rich-object to the given room.
	 *
	 * The author and timestamp are automatically set to the current user/guest
	 * and time.
	 *
	 * @param string $objectType
	 * @param string $objectId
	 * @param string $metaData
	 * @param string $actorDisplayName
	 * @param string $referenceId
	 * @return DataResponse the status code is "201 Created" if successful, and
	 *         "404 Not found" if the room or session for a guest user was not
	 *         found".
	 */
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
	 * @param int $lastCommonReadId The last known common read message
	 *                              (so the response is 200 instead of 304 when
	 *                              it changes even when there are no messages)
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
	public function receiveMessages(int $lookIntoFuture,
									int $limit = 100,
									int $lastKnownMessageId = 0,
									int $lastCommonReadId = 0,
									int $timeout = 30,
									int $setReadMarker = 1,
									int $includeLastKnown = 0,
									int $noStatusUpdate = 0): DataResponse {
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
			$response = new DataResponse([], Http::STATUS_NOT_MODIFIED);
			if ($lastCommonReadId && $this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
				$newLastCommonRead = $this->chatManager->getLastCommonReadMessage($this->room);
				if ($lastCommonReadId !== $newLastCommonRead) {
					// Set the status code to 200 so the header is sent to the client.
					// As per "section 10.3.5 of RFC 2616" entity headers shall be
					// stripped out on 304: https://stackoverflow.com/a/17822709
					$response->setStatus(Http::STATUS_OK);
				}
				$response->addHeader('X-Chat-Last-Common-Read', $newLastCommonRead);
			}
			return $response;
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
			if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
				$response->addHeader('X-Chat-Last-Common-Read', $this->chatManager->getLastCommonReadMessage($this->room));
			}
		}

		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * @param int $messageId
	 * @return DataResponse
	 */
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
				|| $this->room->getType() === Room::TYPE_ONE_TO_ONE)) {
			// Actor is not a moderator or not the owner of the message
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($message->getVerb() !== 'comment' && $message->getVerb() !== 'object_shared') {
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

		$data = $systemMessage->toArray();
		$data['parent'] = $message->toArray();

		$bridge = $this->matterbridgeManager->getBridgeOfRoom($this->room);

		$response = new DataResponse($data, $bridge['enabled'] ? Http::STATUS_ACCEPTED : Http::STATUS_OK);
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$response->addHeader('X-Chat-Last-Common-Read', $this->chatManager->getLastCommonReadMessage($this->room));
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @RequireModeratorParticipant
	 * @RequireReadWriteConversation
	 *
	 * @return DataResponse
	 */
	public function clearHistory(): DataResponse {
		$attendee = $this->participant->getAttendee();
		if (!$this->participant->hasModeratorPermissions(false)
				|| $this->room->getType() === Room::TYPE_ONE_TO_ONE) {
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


		$data = $systemMessage->toArray();

		$bridge = $this->matterbridgeManager->getBridgeOfRoom($this->room);

		$response = new DataResponse($data, $bridge['enabled'] ? Http::STATUS_ACCEPTED : Http::STATUS_OK);
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$response->addHeader('X-Chat-Last-Common-Read', $this->chatManager->getLastCommonReadMessage($this->room));
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
		$response = new DataResponse();
		if ($this->participant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			$response->addHeader('X-Chat-Last-Common-Read', $this->chatManager->getLastCommonReadMessage($this->room));
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @RequireParticipant
	 *
	 * @return DataResponse
	 */
	public function markUnread(): DataResponse {
		$message = $this->room->getLastMessage();
		$unreadId = 0;

		if ($message instanceof IComment) {
			try {
				$previousMessage = $this->chatManager->getPreviousMessageWithVerb(
					$this->room,
					(int)$message->getId(),
					['comment'],
					$message->getVerb() === 'comment'
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
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * @param int $lastKnownMessageId
	 * @param int $limit
	 * @return DataResponse
	 */
	public function getObjectsSharedInRoom(int $lastKnownMessageId = 0, int $limit = 100): DataResponse {
		$offset = max(0, $lastKnownMessageId);
		$limit = min(200, $limit);

		$comments = $this->chatManager->getSharedObjectMessages($this->room, $offset, $limit);

		$messages = [];
		foreach ($comments as $comment) {
			$message = $this->messageParser->createMessage($this->room, $this->participant, $comment, $this->l);
			$this->messageParser->parseMessage($message);

			if (!$message->getVisibility()) {
				continue;
			}

			$messages[] = $message->toArray();
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

		$results = $this->chatManager->addConversationNotify($results, $search, $this->room, $this->participant);

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
