<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use GuzzleHttp\Exception\ConnectException;
use InvalidArgumentException;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Middleware\Attribute\RequireLoggedInModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireRoom;
use OCA\Talk\Room;
use OCA\Talk\Service\CertificateService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class RecordingController extends AEnvironmentAwareOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private Config $talkConfig,
		private IClientService $clientService,
		private Manager $manager,
		private CertificateService $certificateService,
		private ParticipantService $participantService,
		private RecordingService $recordingService,
		private RoomService $roomService,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the welcome message of a recording server
	 *
	 * @param int $serverId ID of the server
	 * @psalm-param non-negative-int $serverId
	 * @return DataResponse<Http::STATUS_OK, array{version: float}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Welcome message returned
	 * 404: Recording server not found or not configured
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION, tags: ['settings'])]
	public function getWelcomeMessage(int $serverId): DataResponse {
		$recordingServers = $this->talkConfig->getRecordingServers();
		if (empty($recordingServers) || !isset($recordingServers[$serverId])) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}

		$url = rtrim($recordingServers[$serverId]['server'], '/');
		$url = strtolower($url);

		$verifyServer = (bool)$recordingServers[$serverId]['verify'];

		if ($verifyServer && str_contains($url, 'https://')) {
			$expiration = $this->certificateService->getCertificateExpirationInDays($url);

			if ($expiration < 0) {
				return new DataResponse(['error' => 'CERTIFICATE_EXPIRED'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		}

		$client = $this->clientService->newClient();
		try {
			$timeBefore = $this->timeFactory->getTime();
			$response = $client->get($url . '/api/v1/welcome', [
				'verify' => $verifyServer,
				'nextcloud' => [
					'allow_local_address' => true,
				],
			]);
			$timeAfter = $this->timeFactory->getTime();

			if ($response->getHeader(\OCA\Talk\Signaling\Manager::FEATURE_HEADER)) {
				return new DataResponse([
					'error' => 'IS_SIGNALING_SERVER',
				], Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			$responseTime = $this->timeFactory->getDateTime($response->getHeader('date'))->getTimestamp();
			if (($timeBefore - Config::ALLOWED_BACKEND_TIMEOFFSET) > $responseTime
				|| ($timeAfter + Config::ALLOWED_BACKEND_TIMEOFFSET) < $responseTime) {
				return new DataResponse([
					'error' => 'TIME_OUT_OF_SYNC',
				], Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			$body = $response->getBody();
			$data = json_decode($body, true);

			if (!is_array($data)) {
				return new DataResponse([
					'error' => 'JSON_INVALID',
				], Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			return new DataResponse($data);
		} catch (ConnectException $e) {
			return new DataResponse(['error' => 'CAN_NOT_CONNECT'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\Exception $e) {
			return new DataResponse(['error' => (string)$e->getCode()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Check if the current request is coming from an allowed backend.
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
	private function validateBackendRequest(string $data): bool {
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
	 * Return the body of the backend request. This can be overridden in
	 * tests.
	 *
	 * @return string
	 */
	protected function getInputStream(): string {
		return (string)file_get_contents('php://input');
	}

	/**
	 * Update the recording status as a backend
	 *
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array{type: string, error: array{code: string, message: string}}, array{}>
	 *
	 * 200: Recording status updated successfully
	 * 400: Updating recording status is not possible
	 * 403: Missing permissions to update recording status
	 * 404: Room not found
	 */
	#[OpenAPI(scope: 'backend-recording')]
	#[PublicPage]
	#[BruteForceProtection(action: 'talkRecordingSecret')]
	#[BruteForceProtection(action: 'talkRecordingStatus')]
	#[RequestHeader(name: 'talk-recording-random', description: 'Random seed used to generate the request checksum', indirect: true)]
	#[RequestHeader(name: 'talk-recording-checksum', description: 'Checksum over the request body to verify authenticity from the recording backend', indirect: true)]
	public function backend(): DataResponse {
		$json = $this->getInputStream();
		if (!$this->validateBackendRequest($json)) {
			$response = new DataResponse([
				'type' => 'error',
				'error' => [
					'code' => 'invalid_request',
					'message' => 'The request could not be authenticated.',
				],
			], Http::STATUS_FORBIDDEN);
			$response->throttle(['action' => 'talkRecordingSecret']);
			return $response;
		}

		$message = json_decode($json, true);
		switch ($message['type'] ?? '') {
			case 'started':
				return $this->backendStarted($message['started']);
			case 'stopped':
				return $this->backendStopped($message['stopped']);
			case 'failed':
				return $this->backendFailed($message['failed']);
			default:
				return new DataResponse([
					'type' => 'error',
					'error' => [
						'code' => 'unknown_type',
						'message' => 'The given type ' . json_encode($message) . ' is not supported.',
					],
				], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{type: string, error: array{code: string, message: string}}, array{}>
	 */
	private function backendStarted(array $started): DataResponse {
		$token = $started['token'];
		$status = $started['status'];
		$actor = $started['actor'];

		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException $e) {
			$this->logger->debug('Failed to get room {token}', [
				'token' => $token,
				'app' => 'spreed-recording',
			]);
			return new DataResponse([
				'type' => 'error',
				'error' => [
					'code' => 'no_such_room',
					'message' => 'Room not found.',
				],
			], Http::STATUS_NOT_FOUND);
		}

		if ($room->getCallRecording() !== Room::RECORDING_VIDEO_STARTING && $room->getCallRecording() !== Room::RECORDING_AUDIO_STARTING) {
			$this->logger->error('Recording backend tried to start recording in room {token}, but it was not requested by a moderator.', [
				'token' => $token,
				'app' => 'spreed-recording',
			]);
			$response = new DataResponse([
				'type' => 'error',
				'error' => [
					'code' => 'no_such_room',
					'message' => 'Room not found.',
				],
			], Http::STATUS_NOT_FOUND);
			$response->throttle(['action' => 'talkRecordingStatus']);
			return $response;
		}

		try {
			$participant = $this->participantService->getParticipantByActor($room, $actor['type'], $actor['id']);
		} catch (ParticipantNotFoundException $e) {
			$participant = null;
		}

		$this->roomService->setCallRecording($room, $status, $participant);

		return new DataResponse(null);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{type: string, error: array{code: string, message: string}}, array{}>
	 */
	private function backendStopped(array $stopped): DataResponse {
		$token = $stopped['token'];
		$actor = null;
		if (array_key_exists('actor', $stopped)) {
			$actor = $stopped['actor'];
		}

		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException $e) {
			$this->logger->debug('Failed to get room {token}', [
				'token' => $token,
				'app' => 'spreed-recording',
			]);
			return new DataResponse([
				'type' => 'error',
				'error' => [
					'code' => 'no_such_room',
					'message' => 'Room not found.',
				],
			], Http::STATUS_NOT_FOUND);
		}

		try {
			if ($actor === null) {
				throw new ParticipantNotFoundException();
			}

			$participant = $this->participantService->getParticipantByActor($room, $actor['type'], $actor['id']);
		} catch (ParticipantNotFoundException $e) {
			$participant = null;
		}

		$this->roomService->setCallRecording($room, Room::RECORDING_NONE, $participant);

		return new DataResponse(null);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{type: string, error: array{code: string, message: string}}, array{}>
	 */
	private function backendFailed(array $failed): DataResponse {
		$token = $failed['token'];

		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException $e) {
			$this->logger->debug('Failed to get room {token}', [
				'token' => $token,
				'app' => 'spreed-recording',
			]);
			return new DataResponse([
				'type' => 'error',
				'error' => [
					'code' => 'no_such_room',
					'message' => 'Room not found.',
				],
			], Http::STATUS_NOT_FOUND);
		}

		$this->roomService->setCallRecording($room, Room::RECORDING_FAILED);

		return new DataResponse(null);
	}

	/**
	 * Start the recording
	 *
	 * @param int $status Type of the recording
	 * @psalm-param Room::RECORDING_* $status
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Recording started successfully
	 * 400: Starting recording is not possible
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function start(int $status): DataResponse {
		try {
			$this->recordingService->start($this->room, $status, $this->userId, $this->participant);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(null);
	}

	/**
	 * Stop the recording
	 *
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Recording stopped successfully
	 * 400: Stopping recording is not possible
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function stop(): DataResponse {
		try {
			$this->recordingService->stop($this->room, $this->participant);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(null);
	}

	/**
	 * Store the recording
	 *
	 * @param ?string $owner User that will own the recording file. `null` is actually not allowed and will always result in a "400 Bad Request". It's only allowed code-wise to handle requests where the post data exceeded the limits, so we can return a proper error instead of "500 Internal Server Error".
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{type: string, error: array{code: string, message: string}}, array{}>
	 *
	 * 200: Recording stored successfully
	 * 400: Storing recording is not possible
	 * 401: Missing permissions to store recording
	 */
	#[PublicPage]
	#[BruteForceProtection(action: 'talkRecordingSecret')]
	#[OpenAPI(scope: 'backend-recording')]
	#[RequireRoom]
	#[RequestHeader(name: 'talk-recording-random', description: 'Random seed used to generate the request checksum', indirect: true)]
	#[RequestHeader(name: 'talk-recording-checksum', description: 'Checksum over the request body to verify authenticity from the recording backend', indirect: true)]
	public function store(?string $owner): DataResponse {
		$data = $this->room->getToken();
		if (!$this->validateBackendRequest($data)) {
			$response = new DataResponse([
				'type' => 'error',
				'error' => [
					'code' => 'invalid_request',
					'message' => 'The request could not be authenticated.',
				],
			], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['action' => 'talkRecordingSecret']);
			return $response;
		}

		if ($owner === null) {
			$this->logger->error('Recording backend failed to provide the owner when uploading a recording [ conversation: "' . $this->room->getToken() . '" ]. Most likely the post_max_size or upload_max_filesize were exceeded.');
			try {
				$this->recordingService->notifyAboutFailedStore($this->room);
			} catch (InvalidArgumentException) {
				// Ignoring, we logged an error already
			}
			return new DataResponse(['error' => 'size'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$file = $this->request->getUploadedFile('file');
			$this->recordingService->store($this->getRoom(), $owner, $file);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(null);
	}

	/**
	 * Dismiss the store call recording notification
	 *
	 * @param int $timestamp Timestamp of the notification to be dismissed
	 * @psalm-param non-negative-int $timestamp
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Notification dismissed successfully
	 * 400: Dismissing notification is not possible
	 */
	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	public function notificationDismiss(int $timestamp): DataResponse {
		try {
			$this->recordingService->notificationDismiss(
				$this->getRoom(),
				$this->participant,
				$timestamp,
				null, // FIXME we would/should extend the URL, but the iOS app is crafting it manually atm due to OS limitations
			);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(null);
	}

	/**
	 * Share the recorded file to the chat
	 *
	 * @param int $fileId ID of the file
	 * @psalm-param non-negative-int $fileId
	 * @param int $timestamp Timestamp of the notification to be dismissed
	 * @psalm-param non-negative-int $timestamp
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Recording shared to chat successfully
	 * 400: Sharing recording to chat is not possible
	 */
	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	public function shareToChat(int $fileId, int $timestamp): DataResponse {
		try {
			$this->recordingService->shareToChat(
				$this->getRoom(),
				$this->participant,
				$fileId,
				$timestamp,
			);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(null);
	}
}
