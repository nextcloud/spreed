<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Exceptions\LiveTranscriptionAppNotEnabledException;
use OCA\Talk\Exceptions\LiveTranslationNotSupportedException;
use OCA\Talk\Middleware\Attribute\RequireCallEnabled;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\LiveTranscriptionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type TalkLiveTranscriptionLanguage from ResponseDefinitions
 */
class LiveTranscriptionController extends AEnvironmentAwareOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private LiveTranscriptionService $liveTranscriptionService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Enable the live transcription
	 *
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'app'|'in-call'}, array{}>
	 *
	 * 200: Live transcription enabled successfully
	 * 400: The external app "live_transcription" is not available
	 * 400: The participant is not in the call
	 */
	#[PublicPage]
	#[RequireCallEnabled]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/live-transcription/{token}', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function enable(): DataResponse {
		if ($this->room->getCallFlag() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse(['error' => 'in-call'], Http::STATUS_BAD_REQUEST);
		}

		if ($this->participant->getSession() && $this->participant->getSession()->getInCall() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse(['error' => 'in-call'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->liveTranscriptionService->enable($this->room, $this->participant);
		} catch (LiveTranscriptionAppNotEnabledException $e) {
			return new DataResponse(['error' => 'app'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse(null);
	}

	/**
	 * Disable the live transcription
	 *
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'app'|'in-call'}, array{}>
	 *
	 * 200: Live transcription stopped successfully
	 * 400: The external app "live_transcription" is not available
	 * 400: The participant is not in the call
	 */
	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/live-transcription/{token}', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function disable(): DataResponse {
		if ($this->room->getCallFlag() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse(['error' => 'in-call'], Http::STATUS_BAD_REQUEST);
		}

		if ($this->participant->getSession() && $this->participant->getSession()->getInCall() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse(['error' => 'in-call'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->liveTranscriptionService->disable($this->room, $this->participant);
		} catch (LiveTranscriptionAppNotEnabledException $e) {
			return new DataResponse(['error' => 'app'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse(null);
	}

	/**
	 * Get available languages for live transcriptions
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, TalkLiveTranscriptionLanguage>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'app'}, array{}>
	 *
	 * 200: Available languages got successfully
	 * 400: The external app "live_transcription" is not available
	 */
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/live-transcription/languages', requirements: [
		'apiVersion' => '(v1)',
	])]
	public function getAvailableLanguages(): DataResponse {
		try {
			$languages = $this->liveTranscriptionService->getAvailableLanguages();
		} catch (LiveTranscriptionAppNotEnabledException $e) {
			return new DataResponse(['error' => 'app'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($languages);
	}

	/**
	 * Get available languages for live translations
	 *
	 * The returned array provides a list of origin languages
	 * ("originLanguages") and a list of target languages ("targetLanguages").
	 * Any origin language can be translated to any target language.
	 *
	 * The origin language list can contain "detect_language" as a special value
	 * indicating auto-detection support.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{originLanguages: array<string, TalkLiveTranscriptionLanguage>, targetLanguages: array<string, TalkLiveTranscriptionLanguage>, defaultTargetLanguageId: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'app'|'translations'}, array{}>
	 *
	 * 200: Available languages got successfully
	 * 400: The external app "live_transcription" is not available or
	 * translations are not supported.
	 */
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/live-transcription/translation-languages', requirements: [
		'apiVersion' => '(v1)',
	])]
	public function getAvailableTranslationLanguages(): DataResponse {
		try {
			$languages = $this->liveTranscriptionService->getAvailableTranslationLanguages();
		} catch (LiveTranscriptionAppNotEnabledException $e) {
			return new DataResponse(['error' => 'app'], Http::STATUS_BAD_REQUEST);
		} catch (LiveTranslationNotSupportedException $e) {
			return new DataResponse(['error' => 'translations'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($languages);
	}

	/**
	 * Set language for live transcriptions
	 *
	 * @param string $languageId the ID of the language to set
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN, array{error: 'app'}, array{}>
	 *
	 * 200: Language set successfully
	 * 400: The external app "live_transcription" is not available
	 * 403: Participant is not a moderator
	 */
	#[PublicPage]
	#[RequireModeratorParticipant]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/live-transcription/{token}/language', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function setLanguage(string $languageId): DataResponse {
		try {
			$this->liveTranscriptionService->setLanguage($this->room, $languageId);
		} catch (LiveTranscriptionAppNotEnabledException $e) {
			return new DataResponse(['error' => 'app'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse(null);
	}

	/**
	 * Set target language for live translations
	 *
	 * Each participant can set the language in which they want to receive the
	 * translations.
	 *
	 * Setting the target language is possible only during a call and
	 * immediately enables the translations. Translations can be disabled by
	 * sending a null value as the language id.
	 *
	 * @param string $targetLanguageId the ID of the language to set
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'app'|'translations'|'in-call'}, array{}>
	 *
	 * 200: Target language set successfully
	 * 400: The external app "live_transcription" is not available or
	 * translations are not supported.
	 * 400: The participant is not in the call.
	 */
	#[PublicPage]
	#[RequireCallEnabled]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/live-transcription/{token}/target-language', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function setTargetLanguage(?string $targetLanguageId): DataResponse {
		if ($this->room->getCallFlag() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse(['error' => 'in-call'], Http::STATUS_BAD_REQUEST);
		}

		if (!$this->participant->getSession() || $this->participant->getSession()->getInCall() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse(['error' => 'in-call'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->liveTranscriptionService->setTargetLanguage($this->room, $this->participant, $targetLanguageId);
		} catch (LiveTranscriptionAppNotEnabledException $e) {
			return new DataResponse(['error' => 'app'], Http::STATUS_BAD_REQUEST);
		} catch (LiveTranslationNotSupportedException $e) {
			return new DataResponse(['error' => 'translations'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse(null);
	}
}
