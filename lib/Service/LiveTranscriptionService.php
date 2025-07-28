<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Participant;
use OCA\Talk\Room;
use Psr\Log\LoggerInterface;

class LiveTranscriptionService {

	public function __construct(
		private ?string $userId,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * @throws RuntimeException if the external app "live_transcription" is not
	 *         available.
	 */
	public function enable(Room $room, Participant $participant): void {
		$params = [
			'roomToken' => $room->getToken(),
			'sessionId' => $participant->getSession()->getSessionId(),
			'enable' => true,
		];

		$this->requestToExAppLiveTranscription($params);
	}

	/**
	 * @throws RuntimeException if the external app "live_transcription" is not
	 *         available.
	 */
	public function disable(Room $room, Participant $participant): void {
		$params = [
			'roomToken' => $room->getToken(),
			'sessionId' => $participant->getSession()->getSessionId(),
			'enable' => true,
		];

		$this->requestToExAppLiveTranscription($params);
	}

	/**
	 * @throws RuntimeException if the external app "live_transcription" is not
	 *         available.
	 */
	private function requestToExAppLiveTranscription(string $method, string $params): void {
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
			'/transcribeCall',
			$this->userId,
			'POST',
			$params,
		);
	}
}
