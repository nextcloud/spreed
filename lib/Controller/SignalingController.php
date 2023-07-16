<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

use GuzzleHttp\Exception\ConnectException;
use OCA\Talk\Config;
use OCA\Talk\Events\SignalingEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\CertificateService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\SessionService;
use OCA\Talk\Signaling\Messages;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\Bruteforce\IThrottler;
use Psr\Log\LoggerInterface;

class SignalingController extends OCSController {
	/** @var int */
	private const PULL_MESSAGES_TIMEOUT = 30;

	public const EVENT_BACKEND_SIGNALING_ROOMS = self::class . '::signalingBackendRoom';

	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $serverConfig,
		private Config $talkConfig,
		private \OCA\Talk\Signaling\Manager $signalingManager,
		private TalkSession $session,
		private Manager $manager,
		private CertificateService $certificateService,
		private ParticipantService $participantService,
		private SessionService $sessionService,
		private IDBConnection $dbConnection,
		private Messages $messages,
		private IUserManager $userManager,
		private IEventDispatcher $dispatcher,
		private ITimeFactory $timeFactory,
		private IClientService $clientService,
		IThrottler $throttler,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Check if the current request is coming from an allowed recording backend.
	 *
	 * The backends are sending the custom header "Talk-Recording-Random"
	 * containing at least 32 bytes random data, and the header
	 * "Talk-Recording-Checksum", which is the SHA256-HMAC of the random data
	 * and the body of the request, calculated with the shared secret from the
	 * configuration.
	 *
	 * @param string $data
	 * @return bool
	 */
	private function validateRecordingBackendRequest(string $data): bool {
		$random = $this->request->getHeader('Talk-Recording-Random');
		if (empty($random) || strlen($random) < 32) {
			$this->logger->debug("Missing random");
			return false;
		}
		$checksum = $this->request->getHeader('Talk-Recording-Checksum');
		if (empty($checksum)) {
			$this->logger->debug("Missing checksum");
			return false;
		}
		$hash = hash_hmac('sha256', $random . $data, $this->talkConfig->getRecordingSecret());
		return hash_equals($hash, strtolower($checksum));
	}

	#[PublicPage]
	#[BruteForceProtection(action: 'talkRoomToken')]
	#[BruteForceProtection(action: 'talkRecordingSecret')]
	public function getSettings(string $token = ''): DataResponse {
		$isRecordingRequest = false;

		if (!empty($this->request->getHeader('Talk-Recording-Random')) || !empty($this->request->getHeader('Talk-Recording-Checksum'))) {
			if (!$this->validateRecordingBackendRequest('')) {
				$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
				$response->throttle(['action' => 'talkRecordingSecret']);
				return $response;
			}

			$isRecordingRequest = true;
		}

		try {
			if ($token !== '' && $isRecordingRequest) {
				$room = $this->manager->getRoomByToken($token);
			} elseif ($token !== '') {
				$room = $this->manager->getRoomForUserByToken($token, $this->userId);
			} else {
				// FIXME Soft-fail for legacy support in mobile apps
				$room = null;
			}
		} catch (RoomNotFoundException $e) {
			$response = new DataResponse([], Http::STATUS_NOT_FOUND);
			$response->throttle(['token' => $token, 'action' => 'talkRoomToken']);
			return $response;
		}

		$stun = [];
		$stunUrls = [];
		$stunServers = $this->talkConfig->getStunServers();
		foreach ($stunServers as $stunServer) {
			$stunUrls[] = 'stun:' . $stunServer;
		}
		$stun[] = [
			'urls' => $stunUrls
		];

		$turn = [];
		$turnSettings = $this->talkConfig->getTurnSettings();
		foreach ($turnSettings as $turnServer) {
			$turnUrls = [];
			$schemes = explode(',', $turnServer['schemes']);
			$protocols = explode(',', $turnServer['protocols']);
			foreach ($schemes as $scheme) {
				foreach ($protocols as $proto) {
					$turnUrls[] = $scheme . ':' . $turnServer['server'] . '?transport=' . $proto;
				}
			}

			$turn[] = [
				'urls' => $turnUrls,
				'username' => $turnServer['username'],
				'credential' => $turnServer['password'],
			];
		}

		$signalingMode = $this->talkConfig->getSignalingMode();
		$signaling = $this->signalingManager->getSignalingServerLinkForConversation($room);

		$helloAuthParams = [
			'1.0' => [
				'userid' => $this->userId,
				'ticket' => $this->talkConfig->getSignalingTicket(Config::SIGNALING_TICKET_V1, $this->userId),
			],
			'2.0' => [
				'token' => $this->talkConfig->getSignalingTicket(Config::SIGNALING_TICKET_V2, $this->userId),
			],
		];
		$data = [
			'signalingMode' => $signalingMode,
			'userId' => $this->userId,
			'hideWarning' => $signaling !== '' || $this->talkConfig->getHideSignalingWarning(),
			'server' => $signaling,
			'ticket' => $helloAuthParams['1.0']['ticket'],
			'helloAuthParams' => $helloAuthParams,
			'stunservers' => $stun,
			'turnservers' => $turn,
			'sipDialinInfo' => $this->talkConfig->isSIPConfigured() ? $this->talkConfig->getDialInInfo() : '',
		];

		return new DataResponse($data);
	}

	/**
	 * Only available for logged-in users because guests can not use the apps
	 * right now.
	 *
	 * @param int $serverId
	 * @return DataResponse
	 */
	public function getWelcomeMessage(int $serverId): DataResponse {
		$signalingServers = $this->talkConfig->getSignalingServers();
		if (empty($signalingServers) || !isset($signalingServers[$serverId])) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$url = rtrim($signalingServers[$serverId]['server'], '/');
		$url = strtolower($url);

		if (str_starts_with($url, 'wss://')) {
			$url = 'https://' . substr($url, 6);
		}

		if (str_starts_with($url, 'ws://')) {
			$url = 'http://' . substr($url, 5);
		}

		$verifyServer = (bool) $signalingServers[$serverId]['verify'];

		if ($verifyServer && str_contains($url, 'https://')) {
			$expiration = $this->certificateService->getCertificateExpirationInDays($url);

			if ($expiration < 0) {
				return new DataResponse(['error' => 'CERTIFICATE_EXPIRED'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		}

		$client = $this->clientService->newClient();
		try {
			$response = $client->get($url . '/api/v1/welcome', [
				'verify' => $verifyServer,
				'nextcloud' => [
					'allow_local_address' => true,
				],
			]);

			$body = $response->getBody();
			$data = json_decode($body, true);

			if (!is_array($data)) {
				return new DataResponse([
					'error' => 'JSON_INVALID',
				], Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			if (!isset($data['version'])) {
				return new DataResponse([
					'error' => 'UPDATE_REQUIRED',
					'version' => '',
				], Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			if (!$this->signalingManager->isCompatibleSignalingServer($response)) {
				return new DataResponse([
					'error' => 'UPDATE_REQUIRED',
					'version' => $data['version'] ?? '',
				], Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			return new DataResponse($data);
		} catch (ConnectException $e) {
			return new DataResponse(['error' => 'CAN_NOT_CONNECT'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getCode()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[PublicPage]
	public function signaling(string $token, string $messages): DataResponse {
		if ($this->talkConfig->getSignalingMode() !== Config::SIGNALING_INTERNAL) {
			return new DataResponse('Internal signaling disabled.', Http::STATUS_BAD_REQUEST);
		}

		$response = [];
		$messages = json_decode($messages, true);
		foreach ($messages as $message) {
			$ev = $message['ev'];
			switch ($ev) {
				case 'message':
					$fn = $message['fn'];
					if (!is_string($fn)) {
						break;
					}
					$decodedMessage = json_decode($fn, true);
					if ($message['sessionId'] !== $this->session->getSessionForRoom($token)) {
						break;
					}
					$decodedMessage['from'] = $message['sessionId'];

					if ($decodedMessage['type'] === 'control') {
						$room = $this->manager->getRoomForSession($this->userId, $message['sessionId']);
						$participant = $this->participantService->getParticipantBySession($room, $message['sessionId']);

						if (!$participant->hasModeratorPermissions(false)) {
							break;
						}
					}

					$this->messages->addMessage($message['sessionId'], $decodedMessage['to'], json_encode($decodedMessage));

					break;
			}
		}

		return new DataResponse($response);
	}

	#[PublicPage]
	public function pullMessages(string $token): DataResponse {
		if ($this->talkConfig->getSignalingMode() !== Config::SIGNALING_INTERNAL) {
			return new DataResponse('Internal signaling disabled.', Http::STATUS_BAD_REQUEST);
		}

		$data = [];
		$seconds = self::PULL_MESSAGES_TIMEOUT;

		try {
			$sessionId = $this->session->getSessionForRoom($token);
			if ($sessionId === null) {
				// User is not active in this room
				return new DataResponse([['type' => 'usersInRoom', 'data' => []]], Http::STATUS_NOT_FOUND);
			}

			$room = $this->manager->getRoomForSession($this->userId, $sessionId);
			$participant = $this->participantService->getParticipantBySession($room, $sessionId); // FIXME this causes another query

			$pingTimestamp = $this->timeFactory->getTime();
			if ($participant->getSession() instanceof Session) {
				$this->sessionService->updateLastPing($participant->getSession(), $pingTimestamp);
			}
		} catch (RoomNotFoundException $e) {
			return new DataResponse([['type' => 'usersInRoom', 'data' => []]], Http::STATUS_NOT_FOUND);
		}

		while ($seconds > 0) {
			// Query all messages and send them to the user
			$data = $this->messages->getAndDeleteMessages($sessionId);
			$messageCount = count($data);
			$data = array_filter($data, function ($message) {
				return $message['data'] !== 'refresh-participant-list';
			});

			if ($messageCount !== count($data)) {
				// Make sure the array is a json array not a json object,
				// because the index list has a gap
				$data = array_values($data);
				// Participant list changed, bail out and deliver the info to the user
				break;
			}

			$this->dbConnection->close();
			if (empty($data)) {
				$seconds--;
			} else {
				break;
			}
			sleep(1);

			// Refresh the session and retry
			$sessionId = $this->session->getSessionForRoom($token);
			if ($sessionId === null) {
				// User is not active in this room
				return new DataResponse([['type' => 'usersInRoom', 'data' => []]], Http::STATUS_NOT_FOUND);
			}
		}

		try {
			// Add an update of the room participants at the end of the waiting
			$room = $this->manager->getRoomForSession($this->userId, $sessionId);
			$data[] = ['type' => 'usersInRoom', 'data' => $this->getUsersInRoom($room, $pingTimestamp)];
		} catch (RoomNotFoundException $e) {
			$data[] = ['type' => 'usersInRoom', 'data' => []];

			// Was the session killed or the complete conversation?
			try {
				$room = $this->manager->getRoomForUserByToken($token, $this->userId);
				if ($this->userId) {
					// For logged in users we check if they are still part of the public conversation,
					// if not they were removed instead of having a conflict.
					$this->participantService->getParticipant($room, $this->userId, false);
				}

				// Session was killed, make the UI redirect to an error
				return new DataResponse($data, Http::STATUS_CONFLICT);
			} catch (ParticipantNotFoundException $e) {
				// User removed from conversation, bye!
				return new DataResponse($data, Http::STATUS_NOT_FOUND);
			} catch (RoomNotFoundException $e) {
				// Complete conversation was killed, bye!
				return new DataResponse($data, Http::STATUS_NOT_FOUND);
			}
		}

		return new DataResponse($data);
	}

	/**
	 * @param Room $room
	 * @param int $pingTimestamp
	 * @return array[]
	 */
	protected function getUsersInRoom(Room $room, int $pingTimestamp): array {
		$usersInRoom = [];
		// Get participants active in the last 40 seconds (an extra time is used
		// to include other participants pinging almost at the same time as the
		// current user), or since the last signaling ping of the current user
		// if it was done more than 40 seconds ago.
		$timestamp = min($this->timeFactory->getTime() - (self::PULL_MESSAGES_TIMEOUT + 10), $pingTimestamp);
		// "- 1" is needed because only the participants whose last ping is
		// greater than the given timestamp are returned.
		$participants = $this->participantService->getParticipantsForAllSessions($room, $timestamp - 1);
		foreach ($participants as $participant) {
			$session = $participant->getSession();
			if (!$session instanceof Session) {
				// This is just to make Psalm happy, since we select by session it's always with one.
				continue;
			}

			$userId = '';
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
				$userId = $participant->getAttendee()->getActorId();
			}

			$usersInRoom[] = [
				'userId' => $userId,
				'roomId' => $room->getId(),
				'lastPing' => $session->getLastPing(),
				'sessionId' => $session->getSessionId(),
				'inCall' => $session->getInCall(),
				'participantPermissions' => $participant->getPermissions(),
			];
		}

		return $usersInRoom;
	}

	/**
	 * Check if the current request is coming from an allowed backend.
	 *
	 * The backends are sending the custom header "Talk-Signaling-Random"
	 * containing at least 32 bytes random data, and the header
	 * "Talk-Signaling-Checksum", which is the SHA256-HMAC of the random data
	 * and the body of the request, calculated with the shared secret from the
	 * configuration.
	 *
	 * @param string $data
	 * @return bool
	 */
	private function validateBackendRequest(string $data): bool {
		if (!isset($_SERVER['HTTP_SPREED_SIGNALING_RANDOM'],
			$_SERVER['HTTP_SPREED_SIGNALING_CHECKSUM'])) {
			return false;
		}
		$random = $_SERVER['HTTP_SPREED_SIGNALING_RANDOM'];
		if (empty($random) || strlen($random) < 32) {
			return false;
		}
		$checksum = $_SERVER['HTTP_SPREED_SIGNALING_CHECKSUM'];
		if (empty($checksum)) {
			return false;
		}
		$hash = hash_hmac('sha256', $random . $data, $this->talkConfig->getSignalingSecret());
		return hash_equals($hash, strtolower($checksum));
	}

	/**
	 * Return the body of the backend request. This can be overridden in
	 * tests.
	 *
	 * @return string
	 */
	protected function getInputStream(): string {
		return file_get_contents('php://input');
	}

	/**
	 * Backend API to query information required for standalone signaling
	 * servers.
	 *
	 * See sections "Backend validation" in
	 * https://nextcloud-spreed-signaling.readthedocs.io/en/latest/standalone-signaling-api-v1/#backend-requests
	 *
	 * @return DataResponse
	 */
	#[PublicPage]
	#[BruteForceProtection(action: 'talkSignalingSecret')]
	public function backend(): DataResponse {
		$json = $this->getInputStream();
		if (!$this->validateBackendRequest($json)) {
			$response = new DataResponse([
				'type' => 'error',
				'error' => [
					'code' => 'invalid_request',
					'message' => 'The request could not be authenticated.',
				],
			]);
			$response->throttle(['action' => 'talkSignalingSecret']);
			return $response;
		}

		$message = json_decode($json, true);
		switch ($message['type'] ?? '') {
			case 'auth':
				// Query authentication information about a user.
				return $this->backendAuth($message['auth']);
			case 'room':
				// Query information about a room.
				return $this->backendRoom($message['room']);
			case 'ping':
				// Ping sessions connected to a room.
				return $this->backendPing($message['ping']);
			default:
				return new DataResponse([
					'type' => 'error',
					'error' => [
						'code' => 'unknown_type',
						'message' => 'The given type ' . json_encode($message) . ' is not supported.',
					],
				]);
		}
	}

	private function backendAuth(array $auth): DataResponse {
		$params = $auth['params'];
		$userId = $params['userid'];
		if (!$this->talkConfig->validateSignalingTicket($userId, $params['ticket'])) {
			$this->logger->debug('Signaling ticket for {user} was not valid', [
				'user' => !empty($userId) ? $userId : '(guests)',
				'app' => 'spreed-hpb',
			]);
			return new DataResponse([
				'type' => 'error',
				'error' => [
					'code' => 'invalid_ticket',
					'message' => 'The given ticket is not valid for this user.',
				],
			]);
		}

		if (!empty($userId)) {
			$user = $this->userManager->get($userId);
			if (!$user instanceof IUser) {
				$this->logger->debug('Tried to validate signaling ticket for {user}, but user manager returned no user', [
					'user' => $userId,
					'app' => 'spreed-hpb',
				]);
				return new DataResponse([
					'type' => 'error',
					'error' => [
						'code' => 'no_such_user',
						'message' => 'The given user does not exist.',
					],
				]);
			}
		}

		$response = [
			'type' => 'auth',
			'auth' => [
				'version' => '1.0',
			],
		];
		if (!empty($userId)) {
			$response['auth']['userid'] = $user->getUID();
			$response['auth']['user'] = $this->talkConfig->getSignalingUserData($user);
		}
		$this->logger->debug('Validated signaling ticket for {user}', [
			'user' => !empty($userId) ? $userId : '(guests)',
			'app' => 'spreed-hpb',
		]);
		return new DataResponse($response);
	}

	private function backendRoom(array $roomRequest): DataResponse {
		$token = $roomRequest['roomid']; // It's actually the room token
		$userId = $roomRequest['userid'];
		$sessionId = $roomRequest['sessionid'];
		$action = !empty($roomRequest['action']) ? $roomRequest['action'] : 'join';
		$actorId = $roomRequest['actorid'] ?? null;
		$actorType = $roomRequest['actortype'] ?? null;
		$inCall = $roomRequest['incall'] ?? null;

		$participant = null;
		if ($actorId !== null && $actorType !== null) {
			try {
				$room = $this->manager->getRoomByActor($token, $actorType, $actorId);
			} catch (RoomNotFoundException $e) {
				$this->logger->debug('Failed to get room {token} by actor {actorType}/{actorId}', [
					'token' => $token,
					'actorType' => $actorType ?? 'null',
					'actorId' => $actorId ?? 'null',
					'app' => 'spreed-hpb',
				]);
				return new DataResponse([
					'type' => 'error',
					'error' => [
						'code' => 'no_such_room',
						'message' => 'The user is not invited to this room.',
					],
				]);
			}

			if ($sessionId) {
				try {
					$participant = $this->participantService->getParticipantBySession($room, $sessionId);
				} catch (ParticipantNotFoundException $e) {
					if ($action === 'join') {
						// If the user joins the session might not be known to the server yet.
						// In this case we load by actor information and use the session id as new session.
						try {
							$participant = $this->participantService->getParticipantByActor($room, $actorType, $actorId);
						} catch (ParticipantNotFoundException $e) {
						}
					}
				}
			} else {
				try {
					$participant = $this->participantService->getParticipantByActor($room, $actorType, $actorId);
				} catch (ParticipantNotFoundException $e) {
				}
			}
		} else {
			try {
				// FIXME Don't preload with the user as that misses the session, kinda meh.
				$room = $this->manager->getRoomByToken($token);
			} catch (RoomNotFoundException $e) {
				$this->logger->debug('Failed to get room by token {token}', [
					'token' => $token,
					'app' => 'spreed-hpb',
				]);
				return new DataResponse([
					'type' => 'error',
					'error' => [
						'code' => 'no_such_room',
						'message' => 'The user is not invited to this room.',
					],
				]);
			}

			if ($sessionId) {
				try {
					$participant = $this->participantService->getParticipantBySession($room, $sessionId);
				} catch (ParticipantNotFoundException $e) {
				}
			} elseif (!empty($userId)) {
				// User trying to join room.
				try {
					$participant = $this->participantService->getParticipant($room, $userId, false);
				} catch (ParticipantNotFoundException $e) {
				}
			}
		}

		if (!$participant instanceof Participant) {
			$this->logger->debug('Failed to get room {token} with participant', [
				'token' => $token,
				'app' => 'spreed-hpb',
			]);
			// Return generic error to avoid leaking which rooms exist.
			return new DataResponse([
				'type' => 'error',
				'error' => [
					'code' => 'no_such_room',
					'message' => 'The user is not invited to this room.',
				],
			]);
		}

		if ($action === 'join') {
			if ($sessionId && !$participant->getSession() instanceof Session) {
				try {
					$session = $this->sessionService->createSessionForAttendee($participant->getAttendee(), $sessionId);
				} catch (Exception $e) {
					return new DataResponse([
						'type' => 'error',
						'error' => [
							'code' => 'duplicate_session',
							'message' => 'The given session is already in use.',
						],
					]);
				}
				$participant->setSession($session);
			}

			if ($participant->getSession() instanceof Session) {
				if ($inCall !== null) {
					$this->participantService->changeInCall($room, $participant, $inCall);
				}
				$this->sessionService->updateLastPing($participant->getSession(), $this->timeFactory->getTime());
			}
		} elseif ($action === 'leave') {
			// Guests are removed completely as they don't reuse attendees,
			// but this is only true for guests that joined directly.
			// Emails are retained as their PIN needs to remain and stay
			// valid.
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_GUESTS) {
				$this->participantService->removeAttendee($room, $participant, Room::PARTICIPANT_LEFT);
			} else {
				$this->participantService->leaveRoomAsSession($room, $participant);
			}
		}

		$permissions = [];
		if ($participant instanceof Participant) {
			$this->logger->debug('Room request to "{action}" room {token} by actor {actorType}/{actorId}', [
				'token' => $token,
				'action' => $action ?? 'null',
				'actorType' => $participant->getAttendee()->getActorType(),
				'actorId' => $participant->getAttendee()->getActorId(),
				'app' => 'spreed-hpb',
			]);

			if ($participant->getPermissions() & Attendee::PERMISSIONS_PUBLISH_AUDIO) {
				$permissions[] = 'publish-audio';
			}
			if ($participant->getPermissions() & Attendee::PERMISSIONS_PUBLISH_VIDEO) {
				$permissions[] = 'publish-video';
			}
			if ($participant->getPermissions() & Attendee::PERMISSIONS_PUBLISH_SCREEN) {
				$permissions[] = 'publish-screen';
			}
			if ($participant->hasModeratorPermissions(false)) {
				$permissions[] = 'control';
			}
		} else {
			$this->logger->debug('Room request to "{action}" room {token} without session', [
				'token' => $token,
				'action' => $action ?? 'null',
				'app' => 'spreed-hpb',
			]);
		}

		$event = new SignalingEvent($room, $participant, $action);
		$this->dispatcher->dispatch(self::EVENT_BACKEND_SIGNALING_ROOMS, $event);

		$response = [
			'type' => 'room',
			'room' => [
				'version' => '1.0',
				'roomid' => $room->getToken(),
				'properties' => $room->getPropertiesForSignaling((string) $userId),
				'permissions' => $permissions,
			],
		];
		if ($event->getSession()) {
			$response['room']['session'] = $event->getSession();
		}
		return new DataResponse($response);
	}

	private function backendPing(array $request): DataResponse {
		$pingSessionIds = [];
		$now = $this->timeFactory->getTime();
		foreach ($request['entries'] as $entry) {
			if ($entry['sessionid'] !== '0') {
				$pingSessionIds[] = $entry['sessionid'];
			}
		}

		// Ping all active sessions with one query
		$this->sessionService->updateMultipleLastPings($pingSessionIds, $now);

		$response = [
			'type' => 'room',
			'room' => [
				'version' => '1.0',
			],
		];
		$this->logger->debug('Pinged {numSessions} sessions {token}', [
			'numSessions' => count($pingSessionIds),
			'token' => !empty($request['roomid']) ? ('in room ' . $request['roomid']) : '',
			'app' => 'spreed-hpb',
		]);
		return new DataResponse($response);
	}
}
