<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\AppAPI\PublicFunctions;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\LiveTranscriptionAppAPIException;
use OCA\Talk\Exceptions\LiveTranscriptionAppNotEnabledException;
use OCA\Talk\Exceptions\LiveTranscriptionAppResponseException;
use OCA\Talk\Exceptions\LiveTranscriptionHistoryNotSupportedException;
use OCA\Talk\Exceptions\LiveTranslationNotSupportedException;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\L10N\IFactory;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkLiveTranscriptionHistory from ResponseDefinitions
 * @psalm-import-type TalkLiveTranscriptionHistoryEntry from ResponseDefinitions
 */
class LiveTranscriptionService {

	public function __construct(
		private readonly ?string $userId,
		private readonly IAppConfig $appConfig,
		private readonly IAppManager $appManager,
		private readonly IFactory $l10nFactory,
		private readonly RoomService $roomService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function isLiveTranscriptionAppEnabled(?object $appApiPublicFunctions = null): bool {
		try {
			if ($appApiPublicFunctions === null) {
				$appApiPublicFunctions = $this->getAppApiPublicFunctions();
			}
		} catch (LiveTranscriptionAppAPIException) {
			return false;
		}

		$exApp = $appApiPublicFunctions->getExApp($this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID));
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
		} catch (ContainerExceptionInterface|NotFoundExceptionInterface) {
			throw new LiveTranscriptionAppAPIException('app-api-functions');
		}

		return $appApiPublicFunctions;
	}

	/**
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 is not enabled.
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
	 *                                                 is not enabled.
	 * @throws LiveTranscriptionAppAPIException if the request could not be sent
	 *                                          to the app or the response could
	 *                                          not be processed.
	 * @throws LiveTranscriptionAppResponseException if the request itself
	 *                                               succeeded but the app
	 *                                               responded with an error.
	 */
	public function isHistorySupported(): bool {
		$capabilities = $this->getCapabilities();
		if (!isset($capabilities['features'])) {
			return false;
		}

		return in_array('live_transcription_history', $capabilities['features'], true);
	}

	/**
	 * Returns the entries of the transcription history of the current call
	 * after the given one.
	 *
	 * @param int $since the "seq" of the last known entry, 0 to get the whole
	 *                   history.
	 * @return TalkLiveTranscriptionHistory
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 is not enabled.
	 * @throws LiveTranscriptionAppAPIException if the request could not be sent
	 *                                          to the app or the response could
	 *                                          not be processed.
	 * @throws LiveTranscriptionAppResponseException if the request itself
	 *                                               succeeded but the app
	 *                                               responded with an error.
	 * @throws LiveTranscriptionHistoryNotSupportedException if the external
	 *                                                       app does not
	 *                                                       provide a history.
	 */
	public function getHistory(Room $room, int $since): array {
		$parameters = [
			'roomToken' => $room->getToken(),
			'since' => $since,
		];

		try {
			$history = $this->requestToExAppLiveTranscription('GET', '/api/v1/call/history', $parameters);
		} catch (LiveTranscriptionAppResponseException $e) {
			if ($e->getResponse()->getStatusCode() === 404) {
				throw new LiveTranscriptionHistoryNotSupportedException();
			}

			throw $e;
		}

		$providerEntries = $history['entries'] ?? null;
		$head = $history['head'] ?? null;
		if (!is_array($providerEntries) || !is_int($head)) {
			$this->logger->error('Request to live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) failed: wrong history structure');

			throw new LiveTranscriptionAppAPIException('response-history-wrong-structure');
		}

		$entries = [];
		foreach ($providerEntries as $entry) {
			$entry = $this->formatHistoryEntry($entry);
			if ($entry === null) {
				$this->logger->error('Request to live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) failed: wrong history entry structure');

				throw new LiveTranscriptionAppAPIException('response-history-wrong-entry-structure');
			}

			$entries[] = $entry;
		}

		return [
			'entries' => $entries,
			'head' => $head,
		];
	}

	/**
	 * @return ?TalkLiveTranscriptionHistoryEntry
	 */
	private function formatHistoryEntry(mixed $entry): ?array {
		if (!is_array($entry)
			|| !is_int($entry['seq'] ?? null)
			|| !is_string($entry['speakerSessionId'] ?? null)
			|| !is_int($entry['t'] ?? null)) {
			return null;
		}

		$formattedEntry = [
			'seq' => $entry['seq'],
			'speakerSessionId' => $entry['speakerSessionId'],
			't' => $entry['t'],
		];

		if (($entry['type'] ?? null) === 'final') {
			if (!is_string($entry['message'] ?? null) || !is_string($entry['langId'] ?? null)) {
				return null;
			}

			$formattedEntry['type'] = 'final';
			$formattedEntry['message'] = $entry['message'];
			$formattedEntry['langId'] = $entry['langId'];
		} elseif (($entry['type'] ?? null) === 'join') {
			$formattedEntry['type'] = 'join';
		} else {
			return null;
		}

		foreach (['actorType', 'actorId', 'actorDisplayName'] as $actorField) {
			if (is_string($entry[$actorField] ?? null)) {
				$formattedEntry[$actorField] = $entry[$actorField];
			}
		}

		return $formattedEntry;
	}

	/**
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 is not enabled.
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
	 *                                                 is not enabled.
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
	 *                                                 is not enabled.
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
			$this->logger->error('Request to live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) failed: list of available languages is null');

			throw new LiveTranscriptionAppAPIException('response-null-language-list');
		}

		return $languages;
	}

	/**
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 is not enabled.
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
	 *                                                 is not enabled.
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
			$this->logger->error('Request to live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) failed: list of translation origin languages not found');

			throw new LiveTranscriptionAppAPIException('response-no-origin-language-list');
		}

		if (!is_array($translationLanguages['target_languages'])) {
			$this->logger->error('Request to live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) failed: list of translation target languages not found');

			throw new LiveTranscriptionAppAPIException('response-no-target-language-list');
		}

		if (count($translationLanguages['target_languages']) === 0) {
			$this->logger->error('Request to live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) failed: empty list of translation target languages');

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

		if (str_contains($defaultTargetLanguageId, '_')) {
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
	 *                                                 is not enabled.
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
	 * Returns the capabilities for the live transcription external app.
	 *
	 * If the installed live transcription app version does not support yet
	 * capabilities an empty array will be returned. On the other hand, if the
	 * app is expected to provide capabilities but they are not returned
	 * LiveTranscriptionAppApiException is thrown instead.
	 *
	 * @return array an array with the capabilities.
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 is not enabled.
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
			$this->logger->error('Request to live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) failed: capabilities is not an array');

			throw new LiveTranscriptionAppAPIException('response-capabilities-not-array');
		}

		$appId = $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID);

		if (!isset($capabilities[$appId])) {
			$this->logger->error('Request to live transcription (ExApp ID: `' . $appId . '`) failed: wrong capabilities structure');

			throw new LiveTranscriptionAppAPIException('response-capabilities-wrong-structure');
		}

		return $capabilities[$appId];
	}

	/**
	 * @throws LiveTranscriptionAppNotEnabledException if the external app
	 *                                                 is not enabled.
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
			$this->logger->error('live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) is not enabled');

			throw new LiveTranscriptionAppNotEnabledException('live-transcription-app');
		}

		$response = $appApiPublicFunctions->exAppRequest(
			$this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID),
			$route,
			$this->userId,
			$method,
			$parameters,
		);

		if (is_array($response) && isset($response['error'])) {
			$this->logger->error('Request to live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) failed: ' . $response['error']);

			throw new LiveTranscriptionAppAPIException('response-error');
		}

		if (is_array($response)) {
			// AppApi only uses array responses for errors, so this should never
			// happen.
			$this->logger->error('Request to live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) failed: response is not a valid response object');

			throw new LiveTranscriptionAppAPIException('response-invalid-object');
		}

		$responseContentType = $response->getHeader('Content-Type');
		if (str_contains((string)$responseContentType, 'application/json')) {
			$body = $response->getBody();
			if (!is_string($body)) {
				$this->logger->error('Request to live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) failed: response body is not a string, but content type is application/json', ['response' => $response]);

				throw new LiveTranscriptionAppAPIException('response-content-type');
			}

			$decodedBody = json_decode($body, true);
		} else {
			$decodedBody = ['response' => $response->getBody()];
		}

		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
			$this->logger->error('live transcription (ExApp ID: `' . $this->appConfig->getAppValueString(Config::LIVE_TRANSCRIPTION_APP_ID) . '`) returned an error', [
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
			throw new LiveTranscriptionAppResponseException($exceptionMessage, $response);
		}

		return $decodedBody;
	}
}
