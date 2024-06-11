<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Kate Döen <kate.doeen@nextcloud.com>
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

use OCA\Talk\Config;
use OCA\Talk\Exceptions\DialOutFailedException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Middleware\Attribute\RequireCallEnabled;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
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
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\IUserManager;

/**
 * @psalm-import-type TalkCallPeer from ResponseDefinitions
 */
class CallController extends AEnvironmentAwareController {

	public function __construct(
		string $appName,
		IRequest $request,
		private ConsentService $consentService,
		private ParticipantService $participantService,
		private RoomService $roomService,
		private IUserManager $userManager,
		private ITimeFactory $timeFactory,
		private Config $talkConfig,
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
	#[PublicPage]
	#[RequireCallEnabled]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequireReadWriteConversation]
	public function getPeersForCall(): DataResponse {
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
	 * Join a call
	 *
	 * @param int<0, 15>|null $flags In-Call flags
	 * @psalm-param int-mask-of<Participant::FLAG_*>|null $flags
	 * @param int<0, 255>|null $forcePermissions In-call permissions
	 * @psalm-param int-mask-of<Attendee::PERMISSIONS_*>|null $forcePermissions
	 * @param bool $silent Join the call silently
	 * @param bool $recordingConsent When the user ticked a checkbox and agreed with being recorded
	 *  (Only needed when the `config => call => recording-consent` capability is set to {@see RecordingService::CONSENT_REQUIRED_YES}
	 *   or the capability is {@see RecordingService::CONSENT_REQUIRED_OPTIONAL}
	 *   and the conversation `recordingConsent` value is {@see RecordingService::CONSENT_REQUIRED_YES} )
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array<empty>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error?: string}, array{}>
	 *
	 * 200: Call joined successfully
	 * 400: No recording consent was given
	 * 404: Call not found
	 */
	#[PublicPage]
	#[RequireCallEnabled]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	#[RequireReadWriteConversation]
	public function joinCall(?int $flags = null, ?int $forcePermissions = null, bool $silent = false, bool $recordingConsent = false): DataResponse {
		if (!$recordingConsent && $this->talkConfig->recordingConsentRequired() !== RecordingService::CONSENT_REQUIRED_NO) {
			if ($this->talkConfig->recordingConsentRequired() === RecordingService::CONSENT_REQUIRED_YES) {
				return new DataResponse(['error' => 'consent'], Http::STATUS_BAD_REQUEST);
			}
			if ($this->talkConfig->recordingConsentRequired() === RecordingService::CONSENT_REQUIRED_OPTIONAL
				&& $this->room->getRecordingConsent() === RecordingService::CONSENT_REQUIRED_YES) {
				return new DataResponse(['error' => 'consent'], Http::STATUS_BAD_REQUEST);
			}
		} elseif ($recordingConsent && $this->talkConfig->recordingConsentRequired() !== RecordingService::CONSENT_REQUIRED_NO) {
			$attendee = $this->participant->getAttendee();
			$this->consentService->storeConsent($this->room, $attendee->getActorType(), $attendee->getActorId());
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

		if ($forcePermissions !== null && $this->participant->hasModeratorPermissions()) {
			$this->roomService->setPermissions($this->room, 'call', Attendee::PERMISSIONS_MODIFY_SET, $forcePermissions, true);
		}

		$joined = $this->participantService->changeInCall($this->room, $this->participant, $flags, false, $silent);

		if (!$joined) {
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
	#[PublicPage]
	#[RequireCallEnabled]
	#[RequireParticipant]
	#[RequirePermission(permission: RequirePermission::START_CALL)]
	public function ringAttendee(int $attendeeId): DataResponse {
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
	#[PublicPage]
	#[RequireParticipant]
	public function updateCallFlags(int $flags): DataResponse {
		$session = $this->participant->getSession();
		if (!$session instanceof Session) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		try {
			$this->participantService->updateCallFlags($this->room, $this->participant, $flags);
		} catch (\Exception $exception) {
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
	#[PublicPage]
	#[RequireParticipant]
	public function leaveCall(bool $all = false): DataResponse {
		$session = $this->participant->getSession();
		if (!$session instanceof Session) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($all && $this->participant->hasModeratorPermissions()) {
			$this->participantService->endCallForEveryone($this->room, $this->participant);
		} else {
			$this->participantService->changeInCall($this->room, $this->participant, Participant::FLAG_DISCONNECTED);
		}

		return new DataResponse();
	}
}
