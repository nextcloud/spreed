<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Config;
use OCA\Talk\Exceptions\DialOutFailedException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Federation\Authenticator;
use OCA\Talk\Manager;
use OCA\Talk\Middleware\Attribute\FederationSupported;
use OCA\Talk\Middleware\Attribute\RequireCallEnabled;
use OCA\Talk\Middleware\Attribute\RequireFederatedParticipant;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Middleware\Attribute\RequirePermission;
use OCA\Talk\Middleware\Attribute\RequireReadWriteConversation;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\ConsentService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\SIPDialOutService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;

/**
 * @psalm-import-type TalkCallPeer from ResponseDefinitions
 */
class CallController extends AEnvironmentAwareController {

	public function __construct(
		string $appName,
		IRequest $request,
		protected Manager $manager,
		private ConsentService $consentService,
		private ParticipantService $participantService,
		private RoomService $roomService,
		private IUserManager $userManager,
		private ITimeFactory $timeFactory,
		private IConfig $serverConfig,
		private Config $talkConfig,
		protected Authenticator $federationAuthenticator,
		private SIPDialOutService $dialOutService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the peers for a call
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkCallPeer[], array{}>
	 *
	 * 200: List of peers in the call returned
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireCallEnabled]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequireReadWriteConversation]
	public function getPeersForCall(): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\CallController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\CallController::class);
			return $proxy->getPeersForCall($this->room, $this->participant);
		}

		$timeout = $this->timeFactory->getTime() - Session::SESSION_TIMEOUT;
		$result = [];
		$participants = $this->participantService->getParticipantsInCall($this->room, $timeout);

		foreach ($participants as $participant) {
			$displayName = $participant->getAttendee()->getActorId();
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
				if ($participant->getAttendee()->getDisplayName()) {
					$displayName = $participant->getAttendee()->getDisplayName();
				} else {
					$userDisplayName = $this->userManager->getDisplayName($participant->getAttendee()->getActorId());
					if ($userDisplayName !== null) {
						$displayName = $userDisplayName;
					}
				}
			} else {
				$displayName = $participant->getAttendee()->getDisplayName();
			}

			$result[] = [
				'actorType' => $participant->getAttendee()->getActorType(),
				'actorId' => $participant->getAttendee()->getActorId(),
				'displayName' => $displayName,
				'token' => $this->room->getToken(),
				'lastPing' => $participant->getSession()->getLastPing(),
				'sessionId' => $participant->getSession()->getSessionId(),
			];
		}

		return new DataResponse($result);
	}

	/**
	 * Download the list of current call participants
	 *
	 * Required capability: `download-call-participants`
	 *
	 * @param 'csv'|'pdf' $format Download format
	 * @return DataDownloadResponse<Http::STATUS_OK, 'text/csv'|'application/pdf', array{}>|Response<Http::STATUS_BAD_REQUEST, array{}>
	 *
	 * 200: List of participants in the call downloaded in the requested format
	 * 400: No call in progress
	 */
	#[PublicPage]
	#[RequireModeratorParticipant]
	#[Http\Attribute\NoCSRFRequired]
	public function downloadParticipantsForCall(string $format = 'csv'): DataDownloadResponse|Response {
		$callStart = $this->room->getActiveSince()?->getTimestamp() ?? 0;
		if ($callStart === 0) {
			return new Response(Http::STATUS_BAD_REQUEST);
		}
		$participants = $this->participantService->getParticipantsJoinedCurrentCall($this->room, $callStart);

		if (empty($participants)) {
			return new Response(Http::STATUS_BAD_REQUEST);
		}

		if ($format !== 'csv' && $format !== 'pdf') {
			// Unsupported format
			return new Response(Http::STATUS_BAD_REQUEST);
		}

		if ($format !== 'csv') {
			// FIXME Remove once pdf was implemented.
			return new Response(Http::STATUS_BAD_REQUEST);
		}

		$output = fopen('php://memory', 'w');
		fputcsv($output, [
			'name',
			'email',
			'type',
			'identifier',
		], escape: '');

		foreach ($participants as $participant) {
			$email = '';
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_EMAILS) {
				$email = $participant->getAttendee()->getInvitedCloudId();
			} elseif ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
				$email = $this->userManager->get($participant->getAttendee()->getActorId())?->getEMailAddress() ?? '';
			}
			fputcsv($output, array_map([$this, 'escapeFormulae'], [
				$participant->getAttendee()->getDisplayName(),
				$email,
				$participant->getAttendee()->getActorType(),
				$participant->getAttendee()->getActorId(),
			]), escape: '');
		}

		fseek($output, 0);

		// Clean the room name
		$cleanedRoomName = preg_replace('/[\/\\:*?"<>|\- ]+/', '-', $this->room->getName());
		// Limit to a reasonable length
		$cleanedRoomName = substr($cleanedRoomName, 0, 100);

		$timezone = 'UTC';
		if ($this->participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
			$timezone = $this->serverConfig->getUserValue($this->participant->getAttendee()->getActorId(), 'core', 'timezone', 'UTC');
		}

		try {
			$dateTimeZone = new \DateTimeZone($timezone);
		} catch (\Throwable) {
			$dateTimeZone = null;
		}

		$date = $this->timeFactory->getDateTime('now', $dateTimeZone)->format('Y-m-d');
		$fileName = $cleanedRoomName . ' ' . $date . '.csv';

		return new DataDownloadResponse(stream_get_contents($output), $fileName, 'text/csv');
	}

	protected function escapeFormulae(string $value): string {
		if (preg_match('/^[=+\-@\t\r]/', $value)) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Join a call
	 *
	 * @param int<0, 15>|null $flags In-Call flags
	 * @psalm-param int-mask-of<Participant::FLAG_*>|null $flags
	 * @param bool $silent Join the call silently
	 * @param bool $recordingConsent When the user ticked a checkbox and agreed with being recorded
	 *                               (Only needed when the `config => call => recording-consent` capability is set to {@see RecordingService::CONSENT_REQUIRED_YES}
	 *                               or the capability is {@see RecordingService::CONSENT_REQUIRED_OPTIONAL}
	 *                               and the conversation `recordingConsent` value is {@see RecordingService::CONSENT_REQUIRED_YES} )
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array<empty>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error?: string}, array{}>
	 *
	 * 200: Call joined successfully
	 * 400: No recording consent was given
	 * 404: Call not found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireCallEnabled]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequireReadWriteConversation]
	public function joinCall(?int $flags = null, bool $silent = false, bool $recordingConsent = false): DataResponse {
		try {
			$this->validateRecordingConsent($recordingConsent);
		} catch (\InvalidArgumentException) {
			return new DataResponse(['error' => 'consent'], Http::STATUS_BAD_REQUEST);
		}

		$this->participantService->ensureOneToOneRoomIsFilled($this->room);

		$session = $this->participant->getSession();
		if (!$session instanceof Session) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($flags === null) {
			// Default flags: user is in room with audio/video.
			$flags = Participant::FLAG_IN_CALL | Participant::FLAG_WITH_AUDIO | Participant::FLAG_WITH_VIDEO;
		}

		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\CallController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\CallController::class);
			$response = $proxy->joinFederatedCall($this->room, $this->participant, $flags, $silent, $recordingConsent);

			if ($response->getStatus() === Http::STATUS_OK) {
				$this->participantService->changeInCall($this->room, $this->participant, $flags, false, $silent);
			}

			return $response;
		}

		try {
			$this->participantService->changeInCall($this->room, $this->participant, $flags, silent: $silent);
			$this->roomService->setActiveSince($this->room, $this->participant, $this->timeFactory->getDateTime(), $flags, silent: $silent);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse();
	}

	/**
	 * Validates and stores recording consent.
	 *
	 * @throws \InvalidArgumentException if recording consent is required but
	 *                                   not given
	 */
	protected function validateRecordingConsent(bool $recordingConsent): void {
		if (!$recordingConsent && $this->talkConfig->recordingConsentRequired() !== RecordingService::CONSENT_REQUIRED_NO) {
			if ($this->talkConfig->recordingConsentRequired() === RecordingService::CONSENT_REQUIRED_YES) {
				throw new \InvalidArgumentException();
			}
			if ($this->talkConfig->recordingConsentRequired() === RecordingService::CONSENT_REQUIRED_OPTIONAL
				&& $this->room->getRecordingConsent() === RecordingService::CONSENT_REQUIRED_YES) {
				throw new \InvalidArgumentException();
			}
		} elseif ($recordingConsent && $this->talkConfig->recordingConsentRequired() !== RecordingService::CONSENT_REQUIRED_NO) {
			$attendee = $this->participant->getAttendee();
			$this->consentService->storeConsent($this->room, $attendee->getActorType(), $attendee->getActorId());
		}
	}

	/**
	 * Join call on the host server using the session id of the federated user.
	 *
	 * @param string $sessionId Federated session id to join with
	 * @param int<0, 15>|null $flags In-Call flags
	 * @psalm-param int-mask-of<Participant::FLAG_*>|null $flags
	 * @param bool $silent Join the call silently
	 * @param bool $recordingConsent Agreement to be recorded
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error?: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Call joined successfully
	 * 400: Conditions to join not met
	 * 404: Call not found
	 */
	#[PublicPage]
	#[RequireCallEnabled]
	#[RequireModeratorOrNoLobby]
	#[RequireFederatedParticipant]
	#[RequireReadWriteConversation]
	#[BruteForceProtection(action: 'talkFederationAccess')]
	#[BruteForceProtection(action: 'talkRoomToken')]
	public function joinFederatedCall(string $sessionId, ?int $flags = null, bool $silent = false, bool $recordingConsent = false): DataResponse {
		if (!$this->federationAuthenticator->isFederationRequest()) {
			$response = new DataResponse(null, Http::STATUS_NOT_FOUND);
			$response->throttle(['token' => $this->room->getToken(), 'action' => 'talkRoomToken']);
			return $response;
		}

		try {
			$this->validateRecordingConsent($recordingConsent);
		} catch (\InvalidArgumentException) {
			return new DataResponse(['error' => 'consent'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->participantService->changeInCall($this->room, $this->participant, $flags, false, $silent);
			$this->roomService->setActiveSince($this->room, $this->participant, $this->timeFactory->getDateTime(), $flags, silent: $silent);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * Ring an attendee
	 *
	 * @param int $attendeeId ID of the attendee to ring
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array<empty>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Attendee rang successfully
	 * 400: Ringing attendee is not possible
	 * 404: Attendee could not be found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireCallEnabled]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::START_CALL)]
	public function ringAttendee(int $attendeeId): DataResponse {
		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\CallController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\CallController::class);
			return $proxy->ringAttendee($this->room, $this->participant, $attendeeId);
		}

		if ($this->room->getCallFlag() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($this->participant->getSession() && $this->participant->getSession()->getInCall() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->participantService->sendCallNotificationForAttendee($this->room, $this->participant, $attendeeId);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse();
	}

	/**
	 * Call a SIP dial-out attendee
	 *
	 * @param int $attendeeId ID of the attendee to call
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_NOT_IMPLEMENTED, array{error?: string, message?: string}, array{}>
	 *
	 * 201: Dial-out initiated successfully
	 * 400: SIP dial-out not possible
	 * 404: Participant could not be found or is a wrong type
	 * 501: SIP dial-out is not configured on the server
	 */
	#[PublicPage]
	#[RequireCallEnabled]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::START_CALL)]
	public function sipDialOut(int $attendeeId): DataResponse {
		if ($this->room->getCallFlag() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($this->participant->getSession() && $this->participant->getSession()->getInCall() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->participantService->startDialOutRequest($this->dialOutService, $this->room, $attendeeId);
		} catch (ParticipantNotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (DialOutFailedException $e) {
			return new DataResponse([
				'error' => $e->getMessage(),
				'message' => $e->getReadableError(),
			], Http::STATUS_NOT_IMPLEMENTED);
		} catch (\InvalidArgumentException) {
			return new DataResponse([], Http::STATUS_NOT_IMPLEMENTED);
		}

		return new DataResponse([], Http::STATUS_CREATED);
	}

	/**
	 * Update the in-call flags
	 *
	 * @param int<0, 15> $flags New flags
	 * @psalm-param int-mask-of<Participant::FLAG_*> $flags New flags
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: In-call flags updated successfully
	 * 400: Updating in-call flags is not possible
	 * 404: Call session not found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireParticipant]
	public function updateCallFlags(int $flags): DataResponse {
		$session = $this->participant->getSession();
		if (!$session instanceof Session) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\CallController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\CallController::class);
			$response = $proxy->updateFederatedCallFlags($this->room, $this->participant, $flags);

			if ($response->getStatus() === Http::STATUS_OK) {
				$this->participantService->updateCallFlags($this->room, $this->participant, $flags);
			}

			return $response;
		}

		try {
			$this->participantService->updateCallFlags($this->room, $this->participant, $flags);
		} catch (\Exception $exception) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * Update the in-call flags on the host server using the session id of the
	 * federated user.
	 *
	 * @param string $sessionId Federated session id to update the flags with
	 * @param int<0, 15> $flags New flags
	 * @psalm-param int-mask-of<Participant::FLAG_*> $flags New flags
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST, array<empty>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: In-call flags updated successfully
	 * 400: Updating in-call flags is not possible
	 * 404: Call session not found
	 */
	#[PublicPage]
	#[RequireFederatedParticipant]
	#[BruteForceProtection(action: 'talkFederationAccess')]
	#[BruteForceProtection(action: 'talkRoomToken')]
	public function updateFederatedCallFlags(string $sessionId, int $flags): DataResponse {
		if (!$this->federationAuthenticator->isFederationRequest()) {
			$response = new DataResponse(null, Http::STATUS_NOT_FOUND);
			$response->throttle(['token' => $this->room->getToken(), 'action' => 'talkRoomToken']);
			return $response;
		}

		try {
			$this->participantService->updateCallFlags($this->room, $this->participant, $flags);
		} catch (\Exception) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * Leave a call
	 *
	 * @param bool $all whether to also terminate the call for all participants
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Call left successfully
	 * 404: Call session not found
	 */
	#[FederationSupported]
	#[PublicPage]
	#[RequireParticipant]
	public function leaveCall(bool $all = false): DataResponse {
		$session = $this->participant->getSession();
		if (!$session instanceof Session) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($this->room->isFederatedConversation()) {
			/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\CallController $proxy */
			$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\CallController::class);
			$response = $proxy->leaveFederatedCall($this->room, $this->participant);

			if ($response->getStatus() === Http::STATUS_OK) {
				$this->participantService->changeInCall($this->room, $this->participant, Participant::FLAG_DISCONNECTED);
			}

			return $response;
		}

		if ($all && $this->participant->hasModeratorPermissions()) {
			$result = $this->roomService->resetActiveSinceInDatabaseOnly($this->room);
			if (!$result) {
				// Someone else won the race condition, make sure this user disconnects directly and then return
				$this->participantService->changeInCall($this->room, $this->participant, Participant::FLAG_DISCONNECTED);
				return new DataResponse();
			}
			$this->participantService->endCallForEveryone($this->room, $this->participant);
			$this->roomService->resetActiveSinceInModelOnly($this->room);
		} else {
			$this->participantService->changeInCall($this->room, $this->participant, Participant::FLAG_DISCONNECTED);
			if (!$this->participantService->hasActiveSessionsInCall($this->room)) {
				$this->roomService->resetActiveSince($this->room, $this->participant);
			}
		}

		return new DataResponse();
	}

	/**
	 * Leave a call on the host server using the session id of the federated
	 * user.
	 *
	 * @param string $sessionId Federated session id to leave with
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Call left successfully
	 * 404: Call session not found
	 */
	#[PublicPage]
	#[RequireFederatedParticipant]
	#[BruteForceProtection(action: 'talkFederationAccess')]
	#[BruteForceProtection(action: 'talkRoomToken')]
	public function leaveFederatedCall(string $sessionId): DataResponse {
		if (!$this->federationAuthenticator->isFederationRequest()) {
			$response = new DataResponse(null, Http::STATUS_NOT_FOUND);
			$response->throttle(['token' => $this->room->getToken(), 'action' => 'talkRoomToken']);
			return $response;
		}

		$this->participantService->changeInCall($this->room, $this->participant, Participant::FLAG_DISCONNECTED);
		if (!$this->participantService->hasActiveSessionsInCall($this->room)) {
			$this->roomService->resetActiveSince($this->room, $this->participant);
		}

		return new DataResponse();
	}
}
