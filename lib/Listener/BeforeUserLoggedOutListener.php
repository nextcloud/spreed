<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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
