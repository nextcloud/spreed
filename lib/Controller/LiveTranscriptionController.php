<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Exceptions\LiveTranscriptionAppNotEnabledException;
use OCA\Talk\Middleware\Attribute\RequireCallEnabled;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Participant;
use OCA\Talk\Service\LiveTranscriptionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

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
}
