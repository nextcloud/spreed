<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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
use InvalidArgumentException;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Middleware\Attribute\RequireLoggedInModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireRoom;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\Http\Client\IClientService;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class RecordingController extends AEnvironmentAwareController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private Config $talkConfig,
		private IClientService $clientService,
		private Manager $manager,
		private ParticipantService $participantService,
		private RecordingService $recordingService,
		private RoomService $roomService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	public function getWelcomeMessage(int $serverId): DataResponse {
		$recordingServers = $this->talkConfig->getRecordingServers();
		if ($recordingServers === [] || !isset($recordingServers[$serverId])) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$url = rtrim($recordingServers[$serverId]['server'], '/');

		$client = $this->clientService->newClient();
		try {
			$response = $client->get($url . '/api/v1/welcome', [
				'verify' => (bool) $recordingServers[$serverId]['verify'],
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

			return new DataResponse($data);
		} catch (ConnectException $e) {
			return new DataResponse(['error' => 'CAN_NOT_CONNECT'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getCode()], Http::STATUS_INTERNAL_SERVER_ERROR);
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
	 * Backend API to update recording status by backends.
	 *
	 * @return DataResponse
	 */
	#[PublicPage]
	#[BruteForceProtection(action: 'talkRecordingSecret')]
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

		try {
			$participant = $this->participantService->getParticipantByActor($room, $actor['type'], $actor['id']);
		} catch (ParticipantNotFoundException $e) {
			$participant = null;
		}

		$this->roomService->setCallRecording($room, $status, $participant);

		return new DataResponse();
	}

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

		return new DataResponse();
	}

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

		return new DataResponse();
	}

	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function start(int $status): DataResponse {
		try {
			$this->recordingService->start($this->room, $status, $this->userId, $this->participant);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse();
	}

	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function stop(): DataResponse {
		try {
			$this->recordingService->stop($this->room, $this->participant);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse();
	}

	#[PublicPage]
	#[BruteForceProtection(action: 'talkRecordingSecret')]
	#[RequireRoom]
	public function store(string $owner): DataResponse {
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

		try {
			$file = $this->request->getUploadedFile('file');
			$this->recordingService->store($this->getRoom(), $owner, $file);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse();
	}

	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	public function notificationDismiss(int $timestamp): DataResponse {
		try {
			$this->recordingService->notificationDismiss(
				$this->getRoom(),
				$this->participant,
				$timestamp
			);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse();
	}

	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	public function shareToChat(int $fileId, int $timestamp): DataResponse {
		try {
			$this->recordingService->shareToChat(
				$this->getRoom(),
				$this->participant,
				$fileId,
				$timestamp
			);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse();
	}
}
