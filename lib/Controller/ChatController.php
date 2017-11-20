<?php

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

use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Comments\IComment;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;

class ChatController extends OCSController {

	/** @var string */
	private $userId;

	/** @var IUserManager */
	private $userManager;

	/** @var ISession */
	private $session;

	/** @var Manager */
	private $manager;

	/** @var ChatManager */
	private $chatManager;

	/**
	 * @param string $appName
	 * @param string $UserId
	 * @param IRequest $request
	 * @param IUserManager $userManager
	 * @param ISession $session
	 * @param Manager $manager
	 * @param ChatManager $chatManager
	 */
	public function __construct($appName,
								$UserId,
								IRequest $request,
								IUserManager $userManager,
								ISession $session,
								Manager $manager,
								ChatManager $chatManager) {
		parent::__construct($appName, $request);

		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->session = $session;
		$this->manager = $manager;
		$this->chatManager = $chatManager;
	}

	/**
	 * Returns the Room for the current user.
	 *
	 * If the user is currently not joined to a room then the room with the
	 * given token is returned (provided that the current user is a participant
	 * of that room).
	 *
	 * @param string $token the token for the Room.
	 * @return \OCA\Spreed\Room|null the Room, or null if none was found.
	 */
	private function getRoom($token) {
		try {
			$room = $this->manager->getRoomForSession($this->userId, $this->session->get('spreed-session'));
		} catch (RoomNotFoundException $exception) {
			if ($this->userId === null) {
				return null;
			}

			// For logged in users we search for rooms where they are real
			// participants.
			try {
				$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
				$room->getParticipant($this->userId);
			} catch (RoomNotFoundException $exception) {
				return null;
			} catch (ParticipantNotFoundException $exception) {
				return null;
			}
		}

		return $room;
	}

	/**
	 * @PublicPage
	 *
	 * Sends a new chat message to the given room.
	 *
	 * The author and timestamp are automatically set to the current user/guest
	 * and time.
	 *
	 * @param string $token the room token
	 * @param string $message the message to send
	 * @return DataResponse the status code is "201 Created" if successful, and
	 *         "404 Not found" if the room or session for a guest user was not
	 *         found".
	 */
	public function sendMessage($token, $message) {
		$room = $this->getRoom($token);
		if ($room === null) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($this->userId === null) {
			$actorType = 'guests';
			$actorId = $this->session->get('spreed-session');
			// The character limit for actorId is 64, but the spreed-session is
			// 256 characters long, so it has to be hashed to get an ID that
			// fits (except if there is no session, as the actorId should be
			// empty in that case but sha1('') would generate a hash too
			// instead of returning an empty string).
			$actorId = $actorId ? sha1($actorId) : $actorId;
		} else {
			$actorType = 'users';
			$actorId = $this->userId;
		}

		if (!$actorId) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$creationDateTime = new \DateTime('now', new \DateTimeZone('UTC'));

		$this->chatManager->sendMessage((string) $room->getId(), $actorType, $actorId, $message, $creationDateTime);

		return new DataResponse([], Http::STATUS_CREATED);
	}

	/**
	 * @PublicPage
	 *
	 * Receives the chat messages from the given room.
	 *
	 * It is possible to limit the returned messages to those not older than
	 * certain date and time setting the $notOlderThan parameter. In the same
	 * way it is possible to ignore the first N messages setting the $offset
	 * parameter. Both parameters are optional; if not set all the messages from
	 * the chat are returned.
	 *
	 * If there are currently no messages the response will not be sent
	 * immediately. Instead, HTTP connection will be kept open waiting for new
	 * messages to arrive and, when they do, then the response will be sent. The
	 * connection will not be kept open indefinitely, though; the number of
	 * seconds to wait for new messages to arrive can be set using the timeout
	 * parameter; the default timeout is 30 seconds, maximum timeout is 60
	 * seconds. If the timeout ends then a successful but empty response will be
	 * sent.
	 *
	 * @param string $token the room token
	 * @param int $offset optional, the first N messages to ignore
	 * @param int $notOlderThanTimestamp optional, timestamp in seconds and UTC
	 *        time zone
	 * @param int $timeout optional, the number of seconds to wait for new
	 *        messages (30 by default, 60 at most)
	 * @return DataResponse an array of chat messages, or "404 Not found" if the
	 *         room token was not valid; each chat message is an array with
	 *         fields 'id', 'token', 'actoryType', 'actorId',
	 *         'actorDisplayName', 'timestamp' (in seconds and UTC timezone) and
	 *         'message'.
	 */
	public function receiveMessages($token, $offset = 0, $notOlderThanTimestamp = 0, $timeout = 30) {
		$room = $this->getRoom($token);
		if ($room === null) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$notOlderThan = null;
		if ($notOlderThanTimestamp > 0) {
			$notOlderThan = new \DateTime();
			$notOlderThan->setTimestamp($notOlderThanTimestamp);
			$notOlderThan->setTimezone(new \DateTimeZone('UTC'));
		}

		$maximumTimeout = 60;
		if ($timeout > $maximumTimeout) {
			$timeout = $maximumTimeout;
		}

		$comments = $this->chatManager->receiveMessages((string) $room->getId(), $this->userId, $timeout, $offset, $notOlderThan);

		return new DataResponse(array_map(function(IComment $comment) use ($token) {
			$displayName = null;
			if ($comment->getActorType() === 'users') {
				$user = $this->userManager->get($comment->getActorId());
				$displayName = $user instanceof IUser ? $user->getDisplayName() : null;
			}

			return [
				'id' => $comment->getId(),
				'token' => $token,
				'actorType' => $comment->getActorType(),
				'actorId' => $comment->getActorId(),
				'actorDisplayName' => $displayName,
				'timestamp' => $comment->getCreationDateTime()->getTimestamp(),
				'message' => $comment->getMessage()
			];
		}, $comments));
	}

}
