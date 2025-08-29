<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\AppAPI\PublicFunctions;
use OCA\Talk\Exceptions\LiveTranscriptionAppAPIException;
use OCA\Talk\Exceptions\LiveTranscriptionAppNotEnabledException;
use OCA\Talk\Exceptions\LiveTranscriptionAppResponseException;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\IUserManager;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class LiveTranscriptionService {

	public function __construct(
		private ?string $userId,
		private IAppManager $appManager,
		private IUserManager $userManager,
		private RoomService $roomService,
		protected LoggerInterface $logger,
	) {
	}

	public function isLiveTranscriptionAppEnabled(?object $appApiPublicFunctions = null): bool {
		try {
			if ($appApiPublicFunctions === null) {
				$appApiPublicFunctions = $this->getAppApiPublicFunctions();
			}
		} catch (LiveTranscriptionAppAPIException $e) {
			return false;
		}

		$exApp = $appApiPublicFunctions->getExApp('live_transcription');
		if ($exApp === null || !$exApp['enabled']) {
			return false;
		}

		return true;
	}

	/**
	 * @throws LiveTranscriptionAppAPIException if app_api is not enabled or the
	 *                                          public functions could not be
	 *                                          got.
	 */
	private function getAppApiPublicFunctions(): object {
		if (!$this->appManager->isEnabledForUser('app_api')) {
			throw new LiveTranscriptionAppAPIException('app-api');
		}

		try {
			$appApiPublicFunctions = Server::get(PublicFunctions::class);
		} catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
			throw new LiveTranscriptionAppAPIException('app-api-functions');
		}

		return $appApiPublicFunctions;
	}

	/**
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 "live_transcription" is
	 *                                                 not enabled.
	 * @throws LiveTranscriptionAppAPIException if the request could not be sent
	 *                                          to the app or the response could
	 *                                          not be processed.
	 * @throws LiveTranscriptionAppResponseException if the request itself
	 *                                               succeeded but the app
	 *                                               responded with an error.
	 */
	public function enable(Room $room, Participant $participant): void {
		$parameters = [
			'roomToken' => $room->getToken(),
			'ncSessionId' => $participant->getSession()->getSessionId(),
			'enable' => true,
		];

		$languageId = $room->getLiveTranscriptionLanguageId();
		if (!empty($languageId)) {
			$parameters['langId'] = $languageId;
		}

		$this->requestToExAppLiveTranscription('POST', '/api/v1/call/transcribe', $parameters);
	}

	/**
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 "live_transcription" is
	 *                                                 not enabled.
	 * @throws LiveTranscriptionAppAPIException if the request could not be sent
	 *                                          to the app or the response could
	 *                                          not be processed.
	 * @throws LiveTranscriptionAppResponseException if the request itself
	 *                                               succeeded but the app
	 *                                               responded with an error.
	 */
	public function disable(Room $room, Participant $participant): void {
		$parameters = [
			'roomToken' => $room->getToken(),
			'ncSessionId' => $participant->getSession()->getSessionId(),
			'enable' => false,
		];

		$this->requestToExAppLiveTranscription('POST', '/api/v1/call/transcribe', $parameters);
	}

	/**
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 "live_transcription" is
	 *                                                 not enabled.
	 * @throws LiveTranscriptionAppAPIException if the request could not be sent
	 *                                          to the app or the response could
	 *                                          not be processed.
	 * @throws LiveTranscriptionAppResponseException if the request itself
	 *                                               succeeded but the app
	 *                                               responded with an error.
	 */
	public function getAvailableLanguages(): array {
		$languages = $this->requestToExAppLiveTranscription('GET', '/api/v1/languages');
		if ($languages === null) {
			$this->logger->error('Request to live_transcription (ExApp) failed: list of available languages is null');

			throw new LiveTranscriptionAppAPIException('response-null-language-list');
		}

		return $languages;
	}

	/**
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 "live_transcription" is
	 *                                                 not enabled.
	 * @throws LiveTranscriptionAppAPIException if the request could not be sent
	 *                                          to the app or the response could
	 *                                          not be processed.
	 * @throws LiveTranscriptionAppResponseException if the request itself
	 *                                               succeeded but the app
	 *                                               responded with an error.
	 */
	public function setLanguage(Room $room, string $languageId): void {
		$parameters = [
			'roomToken' => $room->getToken(),
			'langId' => ! empty($languageId) ? $languageId : 'es',
		];

		try {
			$this->requestToExAppLiveTranscription('POST', '/api/v1/call/set-language', $parameters);
		} catch (LiveTranscriptionAppResponseException $e) {
			// If there is no active transcription continue setting the language
			// in the room. In any other case, abort.
			if ($e->getResponse()->getStatusCode() !== 404) {
				throw $e;
			}
		}

		$this->roomService->setLiveTranscriptionLanguageId($room, $languageId);
	}

	/**
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 "live_transcription" is
	 *                                                 not enabled.
	 * @throws LiveTranscriptionAppAPIException if the request could not be sent
	 *                                          to the app or the response could
	 *                                          not be processed.
	 * @throws LiveTranscriptionAppResponseException if the request itself
	 *                                               succeeded but the app
	 *                                               responded with an error.
	 */
	private function requestToExAppLiveTranscription(string $method, string $route, array $parameters = []): ?array {
		try {
			$appApiPublicFunctions = $this->getAppApiPublicFunctions();
		} catch (LiveTranscriptionAppAPIException $e) {
			if ($e->getMessage() === 'app-api') {
				$this->logger->error('AppAPI is not enabled');
			} elseif ($e->getMessage() === 'app-api-functions') {
				$this->logger->error('Could not get AppAPI public functions', ['exception' => $e]);
			}

			throw new LiveTranscriptionAppNotEnabledException($e->getMessage());
		}

		if (!$this->isLiveTranscriptionAppEnabled($appApiPublicFunctions)) {
			$this->logger->error('live_transcription (ExApp) is not enabled');

			throw new LiveTranscriptionAppNotEnabledException('live-transcription-app');
		}

		$response = $appApiPublicFunctions->exAppRequest(
			'live_transcription',
			$route,
			$this->userId,
			$method,
			$parameters,
		);

		if (is_array($response) && isset($response['error'])) {
			$this->logger->error('Request to live_transcription (ExApp) failed: ' . $response['error']);

			throw new LiveTranscriptionAppAPIException('response-error');
		}

		if (is_array($response)) {
			// AppApi only uses array responses for errors, so this should never
			// happen.
			$this->logger->error('Request to live_transcription (ExApp) failed: response is not a valid response object');

			throw new LiveTranscriptionAppAPIException('response-invalid-object');
		}

		$responseContentType = $response->getHeader('Content-Type');
		if (strpos($responseContentType, 'application/json') !== false) {
			$body = $response->getBody();
			if (!is_string($body)) {
				$this->logger->error('Request to live_transcription (ExApp) failed: response body is not a string, but content type is application/json', ['response' => $response]);

				throw new LiveTranscriptionAppAPIException('response-content-type');
			}

			$decodedBody = json_decode($body, true);
		} else {
			$decodedBody = ['response' => $response->getBody()];
		}

		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
			$this->logger->error('live_transcription (ExApp) returned an error', [
				'status-code' => $response->getStatusCode(),
				'response' => $decodedBody,
				'method' => $method,
				'route' => $route,
				'parameters' => $parameters,
			]);

			$exceptionMessage = 'response-status-code';
			if (is_array($decodedBody) && isset($decodedBody['error'])) {
				$exceptionMessage .= ': ' . $decodedBody['error'];
			}
			throw new LiveTranscriptionAppResponseException($exceptionMessage, 0, null, $response);
		}

		return $decodedBody;
	}
}
