<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\AppAPI\PublicFunctions;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Exceptions\LiveTranscriptionAppAPIException;
use OCA\Talk\Exceptions\LiveTranscriptionAppNotEnabledException;
use OCA\Talk\Exceptions\LiveTranscriptionAppResponseException;
use OCA\Talk\Exceptions\LiveTranslationNotSupportedException;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class LiveTranscriptionService {

	public function __construct(
		private ?string $userId,
		private IAppManager $appManager,
		private IUserManager $userManager,
		private IFactory $l10nFactory,
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
	public function isLiveTranslationSupported(): bool {
		// If capabilities are not available live translation is not
		// available either, as it was introduced after capabilities.

		$capabilities = $this->getCapabilities();
		if (!isset($capabilities['features'])) {
			return false;
		}

		return in_array('live_translation', $capabilities['features'], true);
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
			'langId' => $languageId !== '' ? $languageId : 'en',
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
	 * Returns the supported translation languages.
	 *
	 * The returned array provides a list of origin languages
	 * ("originLanguages") and a list of target languages ("targetLanguages").
	 * Any origin language can be translated to any target language.
	 *
	 * The origin language list can contain "detect_language" as a special value
	 * indicating auto-detection support.
	 *
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 "live_transcription" is
	 *                                                 not enabled.
	 * @throws LiveTranscriptionAppAPIException if the request could not be sent
	 *                                          to the app or the response could
	 *                                          not be processed.
	 * @throws LiveTranscriptionAppResponseException if the request itself
	 *                                               succeeded but the app
	 *                                               responded with an error.
	 * @throws LiveTranslationNotSupportedException if live translations are not
	 *                                              supported.
	 */
	public function getAvailableTranslationLanguages(): array {
		// Target languages can be got from capabilities or directly for a
		// specific room, but the list should be the same in both cases.
		$capabilities = $this->getCapabilities();

		if (!isset($capabilities['live_translation'])
			|| !isset($capabilities['live_translation']['supported_translation_languages'])) {
			throw new LiveTranslationNotSupportedException();
		}

		$translationLanguages = $capabilities['live_translation']['supported_translation_languages'];

		if (!is_array($translationLanguages['origin_languages'])) {
			$this->logger->error('Request to live_transcription (ExApp) failed: list of translation origin languages not found');

			throw new LiveTranscriptionAppAPIException('response-no-origin-language-list');
		}

		if (!is_array($translationLanguages['target_languages'])) {
			$this->logger->error('Request to live_transcription (ExApp) failed: list of translation target languages not found');

			throw new LiveTranscriptionAppAPIException('response-no-target-language-list');
		}

		if (count($translationLanguages['target_languages']) === 0) {
			$this->logger->error('Request to live_transcription (ExApp) failed: empty list of translation target languages');

			throw new LiveTranscriptionAppAPIException('response-empty-language-list');
		}

		$translationLanguages['originLanguages'] = $translationLanguages['origin_languages'];
		$translationLanguages['targetLanguages'] = $translationLanguages['target_languages'];
		unset($translationLanguages['origin_languages']);
		unset($translationLanguages['target_languages']);

		$translationLanguages['defaultTargetLanguageId'] = $this->getDefaultTargetLanguageId($translationLanguages['targetLanguages']);

		return $translationLanguages;
	}

	private function getDefaultTargetLanguageId(array $targetLanguages): string {
		if (count($targetLanguages) === 0) {
			return '';
		}

		$defaultTargetLanguageId = $this->l10nFactory->findLanguage(Application::APP_ID);

		if (array_key_exists($defaultTargetLanguageId, $targetLanguages)) {
			return $defaultTargetLanguageId;
		}

		if (strpos($defaultTargetLanguageId, '_') !== false) {
			$defaultTargetLanguageId = substr($defaultTargetLanguageId, 0, strpos($defaultTargetLanguageId, '_'));

			if (array_key_exists($defaultTargetLanguageId, $targetLanguages)) {
				return $defaultTargetLanguageId;
			}
		}

		$defaultTargetLanguageId = 'en';
		if (array_key_exists($defaultTargetLanguageId, $targetLanguages)) {
			return $defaultTargetLanguageId;
		}

		return array_key_first($targetLanguages);
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
	public function setTargetLanguage(Room $room, Participant $participant, ?string $targetLanguageId): void {
		if ($targetLanguageId === '') {
			throw new \InvalidArgumentException('Empty target language id');
		}

		$parameters = [
			'roomToken' => $room->getToken(),
			'ncSessionId' => $participant->getSession()->getSessionId(),
			'langId' => $targetLanguageId,
		];

		try {
			$this->requestToExAppLiveTranscription('POST', '/api/v1/translation/set-target-language', $parameters);
		} catch (LiveTranscriptionAppResponseException $e) {
			if ($e->getResponse()->getStatusCode() === 550) {
				throw new LiveTranslationNotSupportedException();
			}

			throw $e;
		}
	}

	/**
	 * Returns the capabilities for the live_transcription app.
	 *
	 * If the installed live_transcription app version does not support yet
	 * capabilities an empty array will be returned. On the other hand, if the
	 * app is expected to provide capabilities but they are not returned
	 * LiveTranscriptionAppApiException is thrown instead.
	 *
	 * @return array an array with the capabilities.
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
	private function getCapabilities(): array {
		try {
			$capabilities = $this->requestToExAppLiveTranscription('GET', '/capabilities');
		} catch (LiveTranscriptionAppResponseException $e) {
			if ($e->getResponse()->getStatusCode() !== 404) {
				throw $e;
			}

			return [];
		}

		if (!is_array($capabilities)) {
			$this->logger->error('Request to live_transcription (ExApp) failed: capabilities is not an array');

			throw new LiveTranscriptionAppAPIException('response-capabilities-not-array');
		}

		if (!isset($capabilities['live_transcription'])) {
			$this->logger->error('Request to live_transcription (ExApp) failed: wrong capabilities structure');

			throw new LiveTranscriptionAppAPIException('response-capabilities-wrong-structure');
		}

		return $capabilities['live_transcription'];
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
