<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\User\Events\BeforeUserLoggedOutEvent;

/**
 * @template-implements IEventListener<Event>
 */
class BeforeUserLoggedOutListener implements IEventListener {

	public function __construct(
		private Manager $manager,
		private ParticipantService $participantService,
		private TalkSession $talkSession,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof BeforeUserLoggedOutEvent)) {
			// Unrelated
			return;
		}

		$user = $event->getUser();
		if (!$user instanceof IUser) {
			// User already not there anymore, so well …
			return;
		}

		$sessionIds = $this->talkSession->getAllActiveSessions();
		foreach ($sessionIds as $sessionId) {
			try {
				$room = $this->manager->getRoomForSession($user->getUID(), $sessionId);
				$participant = $this->participantService->getParticipant($room, $user->getUID(), $sessionId);
				if ($participant->getSession() && $participant->getSession()->getInCall() !== Participant::FLAG_DISCONNECTED) {
					$this->participantService->changeInCall($room, $participant, Participant::FLAG_DISCONNECTED);
				}
				$this->participantService->leaveRoomAsSession($room, $participant);
			} catch (RoomNotFoundException|ParticipantNotFoundException) {
			}
		}
	}
}
