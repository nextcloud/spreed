<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Config;
use OCA\Talk\Events\BeforeSignalingResponseSentEvent;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Federation\Authenticator;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Service\BanService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\SessionService;
use OCA\Talk\Signaling\Messages;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClientService;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkSignalingFederationSettings from ResponseDefinitions
 * @psalm-import-type TalkSignalingSession from ResponseDefinitions
 * @psalm-import-type TalkSignalingSettings from ResponseDefinitions
 */
class SignalingController extends OCSController {
	/** @var int */
	private const PULL_MESSAGES_TIMEOUT = 30;

	public function __construct(
		string $appName,
		IRequest $request,
		private Config $talkConfig,
		private \OCA\Talk\Signaling\Manager $signalingManager,
		private TalkSession $session,
		private Manager $manager,
		private ParticipantService $participantService,
		private RoomService $roomService,
		private SessionService $sessionService,
		private IDBConnection $dbConnection,
		private Messages $messages,
		private IUserManager $userManager,
		private IEventDispatcher $dispatcher,
		private ITimeFactory $timeFactory,
		private IClientService $clientService,
		private BanService $banService,
		private LoggerInterface $logger,
		protected Authenticator $federationAuthenticator,
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
		$random = $this->request->getHeader('talk-recording-random');
		if (empty($random) || strlen($random) < 32) {
			$this->logger->debug('Missing random');
			return false;
		}
		$checksum = $this->request->getHeader('talk-recording-checksum');
		if (empty($checksum)) {
			$this->logger->debug('Missing checksum');
			return false;
		}
		$hash = hash_hmac('sha256', $random . $data, $this->talkConfig->getRecordingSecret());
		return hash_equals($hash, strtolower($checksum));
	}

	/**
	 * Get the signaling settings
	 *
	 * @param string $token Token of the room
	 * @return DataResponse<Http::STATUS_OK, TalkSignalingSettings, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Signaling settings returned
	 * 401: Recording request invalid
	 * 404: Room not found
	 */
	#[PublicPage]
	#[BruteForceProtection(action: 'talkRoomToken')]
	#[BruteForceProtection(action: 'talkRecordingSecret')]
	#[BruteForceProtection(action: 'talkFederationAccess')]
	#[OpenAPI(tags: ['internal_signaling', 'external_signaling'])]
	#[RequestHeader(name: 'talk-recording-random', description: 'Random seed used to generate the request checksum', indirect: true)]
	#[RequestHeader(name: 'talk-recording-checksum', description: 'Checksum over the request body to verify authenticity from the recording backend', indirect: true)]
	public function getSettings(string $token = ''): DataResponse {
		$isRecordingRequest = false;

		if (!empty($this->request->getHeader('talk-recording-random')) || !empty($this->request->getHeader('talk-recording-checksum'))) {
			if (!$this->validateRecordingBackendRequest('')) {
				$response = new DataResponse(null, Http::STATUS_UNAUTHORIZED);
				$response->throttle(['action' => 'talkRecordingSecret']);
				return $response;
			}

			$isRecordingRequest = true;
		}

		$isTalkFederation = $this->federationAuthenticator->isFederationRequest();

		try {
			$action = 'talkRoomToken';
			if ($token !== '' && $isRecordingRequest) {
				$room = $this->manager->getRoomByToken($token);
			} elseif ($token !== '' && $isTalkFederation) {
				$action = 'talkFederationAccess';
				$room = $this->manager->getRoomByRemoteAccess(
					$token,
					Attendee::ACTOR_FEDERATED_USERS,
					$this->federationAuthenticator->getCloudId(),
					$this->federationAuthenticator->getAccessToken(),
				);
				$participant = $this->participantService->getParticipantByActor(
					$room,
					Attendee::ACTOR_FEDERATED_USERS,
					$this->federationAuthenticator->getCloudId()
				);
				$this->federationAuthenticator->authenticated($room, $participant);
			} elseif ($token !== '') {
				$room = $this->manager->getRoomForUserByToken($token, $this->userId);
			} else {
				// FIXME Soft-fail for legacy support in mobile apps
				$room = null;
			}
		} catch (RoomNotFoundException|ParticipantNotFoundException) {
			$response = new DataResponse(null, Http::STATUS_NOT_FOUND);
			$response->throttle(['token' => $token, 'action' => $action]);
			return $response;
		}

		$stun = [];
		$stunUrls = [];
		$stunServers = $this->talkConfig->getStunServers();
		foreach ($stunServers as $stunServer) {
			if (empty($stunServer)) {
				continue;
			}

			$stunUrls[] = 'stun:' . $stunServer;
		}
		if (!empty($stunUrls)) {
			$stun[] = [
				'urls' => $stunUrls
			];
		}

		$turn = [];
		$turnSettings = $this->talkConfig->getTurnSettings();
		foreach ($turnSettings as $turnServer) {
			if (empty($turnServer['schemes']) || empty($turnServer['server']) || empty($turnServer['protocols'])) {
				continue;
			}

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
				'username' => (string)$turnServer['username'],
				'credential' => (string)$turnServer['password'],
			];
		}

		$signalingMode = $this->talkConfig->getSignalingMode();
		$signaling = $this->signalingManager->getSignalingServerLinkForConversation($room);

		$helloAuthParams20UserId = $isTalkFederation ? null : $this->userId;
		$helloAuthParams20CloudId = $isTalkFederation ? $this->federationAuthenticator->getCloudId() : null;
		$helloAuthParams = [
			'1.0' => [
				'userid' => $this->userId,
				'ticket' => $this->talkConfig->getSignalingTicket(Config::SIGNALING_TICKET_V1, $this->userId),
			],
			'2.0' => [
				'token' => $this->talkConfig->getSignalingTicket(Config::SIGNALING_TICKET_V2, $helloAuthParams20UserId, $helloAuthParams20CloudId),
			],
		];
		$data = [
			'signalingMode' => $signalingMode,
			'userId' => $this->userId,
			'hideWarning' => $signaling !== '' || $this->talkConfig->getHideSignalingWarning(),
			'server' => $signaling,
			'ticket' => $helloAuthParams['1.0']['ticket'],
			'helloAuthParams' => $helloAuthParams,
			'federation' => $this->getFederationSettings($room),
			'stunservers' => $stun,
			'turnservers' => $turn,
			'sipDialinInfo' => $this->talkConfig->isSIPConfigured() ? $this->talkConfig->getDialInInfo() : '',
		];

		return new DataResponse($data);
	}

	/**
	 * @psalm-return ?TalkSignalingFederationSettings
	 */
	private function getFederationSettings(?Room $room): ?array {
		if ($room === null || !$room->isFederatedConversation()) {
			return null;
		}

		try {
			$participant = $this->participantService->getParticipant($room, $this->userId);
		} catch (ParticipantNotFoundException $e) {
			return null;
		}

		/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\SignalingController $proxy */
		$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\SignalingController::class);
		$response = $proxy->getSettings($room, $participant);

		if ($response->getStatus() === Http::STATUS_NOT_FOUND) {
			return null;
		}

		/** @var TalkSignalingSettings $data */
		$data = $response->getData();

		return [
			'server' => $data['server'],
			'nextcloudServer' => $room->getRemoteServer(),
			'helloAuthParams' => [
				'token' => $data['helloAuthParams']['2.0']['token'],
			],
			'roomId' => $room->getRemoteToken(),
		];
	}

	/**
	 * Get the welcome message from a signaling server
	 *
	 * Only available for logged-in users because guests can not use the apps
	 * right now.
	 *
	 * @param int $serverId ID of the signaling server
	 * @psalm-param non-negative-int $serverId
	 * @return DataResponse<Http::STATUS_OK, array{version: string, warning?: string, features?: non-empty-list<string>}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string, version?: string}, array{}>
	 *
	 * 200: Welcome message returned
	 * 404: Signaling server not found
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION, tags: ['settings'])]
	public function getWelcomeMessage(int $serverId): DataResponse {
		try {
			$testResult = $this->signalingManager->checkServerCompatibility($serverId);
		} catch (\OutOfBoundsException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($testResult['data'], $testResult['status']);
	}

	/**
	 * Send signaling messages
	 *
	 * @param string $token Token of the room
	 * @param string $messages JSON encoded messages
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, string, array{}>
	 *
	 * 200: Signaling message sent successfully
	 * 400: Sending signaling message is not possible
	 */
	#[PublicPage]
	#[OpenAPI(tags: ['internal_signaling'])]
	public function sendMessages(string $token, string $messages): DataResponse {
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
					} elseif ($decodedMessage['type'] === 'offer' || $decodedMessage['type'] === 'answer') {
						$room = $this->manager->getRoomForSession($this->userId, $message['sessionId']);
						$participant = $this->participantService->getParticipantBySession($room, $message['sessionId']);

						if (!($participant->getPermissions() & Attendee::PERMISSIONS_PUBLISH_AUDIO) && $decodedMessage['roomType'] === 'video'
								&& $this->isTryingToPublishMedia($decodedMessage['payload']['sdp'], 'audio')) {
							break;
						}
						if (!($participant->getPermissions() & Attendee::PERMISSIONS_PUBLISH_VIDEO) && $decodedMessage['roomType'] === 'video'
								&& $this->isTryingToPublishMedia($decodedMessage['payload']['sdp'], 'video')) {
							break;
						}
						if (!($participant->getPermissions() & Attendee::PERMISSIONS_PUBLISH_SCREEN) && $decodedMessage['roomType'] === 'screen'
								&& ($this->isTryingToPublishMedia($decodedMessage['payload']['sdp'], 'audio')
									|| $this->isTryingToPublishMedia($decodedMessage['payload']['sdp'], 'video'))) {
							break;
						}
					}

					$this->messages->addMessage($message['sessionId'], $decodedMessage['to'], json_encode($decodedMessage));

					break;
			}
		}

		return new DataResponse(null);
	}

	/**
	 * Returns whether the SDP is trying to publish the given media based on the
	 * media direction.
	 *
	 * The SDP is trying to publish if the related media description contains a
	 * media direction of either "sendrecv" or "sendonly". If no media direction
	 * is provided in a media description the media direction in the session
	 * description is used instead. If that is not provided either then
	 * "sendrecv" is assumed.
	 *
	 * See https://www.rfc-editor.org/rfc/rfc8866.html#name-media-direction-attributes
	 *
	 * @param string $sdp the SDP to check
	 * @param string $media the media to check, either "audio" or "video"
	 * @return bool true if it is trying to publish, false otherwise
	 */
	private function isTryingToPublishMedia(string $sdp, string $media): bool {
		$lines = preg_split('/\r\n|\n|\r/', $sdp);

		$sessionMediaDirectionIndex = -1;
		$mediaDirectionIndex = -1;
		$mediaDescriptionIndex = -1;
		$matchingMediaDescriptionIndex = -1;

		for ($i = 0; $i < count($lines); $i++) {
			if (strpos($lines[$i], 'a=sendrecv') === 0
				|| strpos($lines[$i], 'a=sendonly') === 0
				|| strpos($lines[$i], 'a=recvonly') === 0
				|| strpos($lines[$i], 'a=inactive') === 0) {
				$mediaDirectionIndex = $i;

				if ($mediaDescriptionIndex < 0) {
					$sessionMediaDirectionIndex = $mediaDirectionIndex;
				}

				if ($matchingMediaDescriptionIndex >= 0
					&& $matchingMediaDescriptionIndex >= $mediaDescriptionIndex
					&& $mediaDirectionIndex > $matchingMediaDescriptionIndex
					&& (strpos($lines[$mediaDirectionIndex], 'a=sendrecv') === 0
						|| strpos($lines[$mediaDirectionIndex], 'a=sendonly') === 0)) {
					return true;
				}
			} elseif (strpos($lines[$i], 'm=') === 0) {
				// No media direction in previous matching media description,
				// fallback to media direction in the session description or, if
				// not set, default to "sendrecv".
				if ($matchingMediaDescriptionIndex >= 0
					&& $matchingMediaDescriptionIndex >= $mediaDescriptionIndex
					&& $mediaDirectionIndex < $matchingMediaDescriptionIndex
					&& ($sessionMediaDirectionIndex < 0
						|| strpos($lines[$sessionMediaDirectionIndex], 'a=sendrecv') === 0
						|| strpos($lines[$sessionMediaDirectionIndex], 'a=sendonly') === 0)) {
					return true;
				}

				$mediaDescriptionIndex = $i;

				if (strpos($lines[$i], 'm=' . $media) === 0) {
					$matchingMediaDescriptionIndex = $i;
				}
			}
		}

		// No media direction in last matching media description, fallback to
		// media direction in the session description or, if not set, default to
		// "sendrecv".
		if ($matchingMediaDescriptionIndex >= 0
			&& $matchingMediaDescriptionIndex >= $mediaDescriptionIndex
			&& $mediaDirectionIndex < $matchingMediaDescriptionIndex
			&& ($sessionMediaDirectionIndex < 0
				|| strpos($lines[$sessionMediaDirectionIndex], 'a=sendrecv') === 0
				|| strpos($lines[$sessionMediaDirectionIndex], 'a=sendonly') === 0)) {
			return true;
		}

		return false;
	}

	/**
	 * Get signaling messages
	 *
	 * @param string $token Token of the room
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND|Http::STATUS_CONFLICT, list<array{type: string, data: list<TalkSignalingSession>|string}>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, string, array{}>
	 *
	 * 200: Signaling messages returned
	 * 400: Getting signaling messages is not possible
	 * 404: Session, room or participant not found
	 * 409: Session killed
	 */
	#[PublicPage]
	#[OpenAPI(tags: ['internal_signaling'])]
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
		} catch (RoomNotFoundException) {
			$this->banIpIfGuestGotBanned($token);
			return new DataResponse([['type' => 'usersInRoom', 'data' => []]], Http::STATUS_NOT_FOUND);
		}

		while ($seconds > 0) {
			// Query all messages and send them to the user
			$data = $this->messages->getAndDeleteMessages($sessionId);
			$messageCount = count($data);
			$data = array_filter($data, function ($message) {
				return $message['data'] !== 'refresh-participant-list';
			});

			// Make sure the array is a json array not a json object,
			// because the index list has a gap
			$data = array_values($data);

			if ($messageCount !== count($data)) {
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
		} catch (RoomNotFoundException) {
			$this->banIpIfGuestGotBanned($token);
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
	 * @return list<TalkSignalingSession>
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
				'actorType' => $participant->getAttendee()->getActorType(),
				'actorId' => $participant->getAttendee()->getActorId(),
			];
		}

		return $usersInRoom;
	}

	protected function banIpIfGuestGotBanned(string $token): void {
		if ($this->userId !== null) {
			return;
		}

		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException) {
			return;
		}

		try {
			$this->banService->throwIfActorIsBanned($room, null);
		} catch (ForbiddenException) {
		}
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
		return (string)file_get_contents('php://input');
	}

	/**
	 * Backend API to query information required for standalone signaling
	 * servers
	 *
	 * See sections "Backend validation" in
	 * https://nextcloud-spreed-signaling.readthedocs.io/en/latest/standalone-signaling-api-v1/#backend-requests
	 *
	 * @return DataResponse<Http::STATUS_OK, array{type: string, error?: array{code: string, message: string}, auth?: array{version: string, userid?: string, user?: array<string, mixed>}, room?: array{version: string, roomid?: string, properties?: array<string, mixed>, permissions?: list<string>, session?: array<string, mixed>}}, array{}>
	 *
	 * 200: Always, sorry about that
	 */
	#[OpenAPI(scope: 'backend-signaling')]
	#[PublicPage]
	#[BruteForceProtection(action: 'talkSignalingSecret')]
	#[RequestHeader(name: 'spreed-signaling-random', description: 'Random seed used to generate the request checksum', indirect: true)]
	#[RequestHeader(name: 'spreed-signaling-checksum', description: 'Checksum over the request body to verify authenticity from the signaling backend', indirect: true)]
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

	/**
	 * @return DataResponse<Http::STATUS_OK, array{type: string, error?: array{code: string, message: string}, auth?: array{version: string, userid?: string, user?: array<string, mixed>}}, array{}>
	 */
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

	/**
	 * @return DataResponse<Http::STATUS_OK, array{type: string, error?: array{code: string, message: string}, room?: array{version: string, roomid: string, properties: array<string, mixed>, permissions: list<string>, session?: array<string, mixed>}}, array{}>
	 */
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
					'hpbRequest' => json_encode($roomRequest),
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
					'hpbRequest' => json_encode($roomRequest),
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
				'hpbRequest' => json_encode($roomRequest),
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
					$lastJoinedCall = $this->timeFactory->getDateTime();
					$this->participantService->changeInCall($room, $participant, $inCall, lastJoinedCall: $lastJoinedCall->getTimestamp());
					$this->roomService->setActiveSince($room, $participant, $lastJoinedCall, callFlag: $inCall, silent: false);
				}
				$this->sessionService->updateLastPing($participant->getSession(), $this->timeFactory->getTime());
			}
		} elseif ($action === 'leave') {
			$this->participantService->leaveRoomAsSession($room, $participant);
		}

		$this->logger->debug('Room request to "{action}" room {token} by actor {actorType}/{actorId}', [
			'token' => $token,
			'action' => $action ?? 'null',
			'actorType' => $participant->getAttendee()->getActorType(),
			'actorId' => $participant->getAttendee()->getActorId(),
			'app' => 'spreed-hpb',
			'hpbRequest' => json_encode($roomRequest),
		]);

		$permissions = [];
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

		$event = new BeforeSignalingResponseSentEvent($room, $participant, $action);
		$this->dispatcher->dispatchTyped($event);

		$response = [
			'type' => 'room',
			'room' => [
				'version' => '1.0',
				'roomid' => $room->getToken(),
				'properties' => $room->getPropertiesForSignaling((string)$userId),
				'permissions' => $permissions,
			],
		];
		if (!empty($event->getSession())) {
			$response['room']['session'] = $event->getSession();
		}
		return new DataResponse($response);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, array{type: string, room: array{version: string}}, array{}>
	 */
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
