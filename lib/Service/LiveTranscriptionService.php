<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use \RuntimeException;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\Http\Client\IResponse;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class LiveTranscriptionService {

	public function __construct(
		private ?string $userId,
		private IAppManager $appManager,
		private IUserManager $userManager,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * @throws RuntimeException if the external app "live_transcription" is not
	 *         available, or if the request failed.
	 */
	public function enable(Room $room, Participant $participant): void {
		$params = [
			'roomToken' => $room->getToken(),
			'sessionId' => $participant->getSession()->getSessionId(),
			'enable' => true,
		];

		$this->requestToExAppLiveTranscription('/transcribeCall', $params);
	}

	/**
	 * @throws RuntimeException if the external app "live_transcription" is not
	 *         available, or if the request failed.
	 */
	public function disable(Room $room, Participant $participant): void {
		$params = [
			'roomToken' => $room->getToken(),
			'sessionId' => $participant->getSession()->getSessionId(),
			'enable' => true,
		];

		$this->requestToExAppLiveTranscription('/transcribeCall', $params);
	}

	/**
	 * @throws RuntimeException if the external app "live_transcription" is not
	 *         available, or if the request failed.
	 */
	private function requestToExAppLiveTranscription(string $route, array $params): ?IResponse {
		$user = $this->userManager->get($this->userId);
		if (!$this->appManager->isEnabledForUser('app_api', $user)) {
			$this->logger->error('AppAPI is not enabled');
			throw new RuntimeException('app-api');
		}

		try {
			$appApiFunctions = \OCP\Server::get(\OCA\AppAPI\PublicFunctions::class);
		} catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
			$this->logger->error('Could not get AppAPI public functions', ['exception' => $e]);
			throw new RuntimeException('app-api-functions');
		}

		if (!$this->appManager->isEnabledForUser('live_transcription', $user)) {
			$this->logger->error('External app live_transcription is not enabled');
			throw new RuntimeException('live-transcription-app');
		}

		$response = $appApiFunctions->exAppRequest(
			'live_transcription',
			$route,
			$this->userId,
			'POST',
			$params,
		);

		if (is_array($response) && isset($response['error'])) {
			$this->logger->error('Request to external app live_transcription failed: ' . $response['error']);
			throw new RuntimeException('request');
		}
		if (is_array($response)) {
			// AppApi only uses array responses for errors, so this should never
			// happen.
			$this->logger->error('Request to external app live_transcription failed: response is not a valid response object');
			throw new RuntimeException('response');
		}

		return $response;
	}
}
