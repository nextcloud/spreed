<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2021 Carl Schwan <carl@carlschwan.eu>
 *
 * @author Carl Schwan <carl@carlschwan.eu>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Status;

use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Events\CallEndedForEveryoneEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\UserStatus\IManager;
use OCP\UserStatus\IUserStatus;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {

	public function __construct(
		protected IManager $statusManager,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof BeforeParticipantModifiedEvent) {
			$this->beforeParticipantModified($event);
		}
		if ($event instanceof CallEndedForEveryoneEvent) {
			$this->revertUserStatusOnEndCallForEveryone($event);
		}
	}

	protected function beforeParticipantModified(BeforeParticipantModifiedEvent $event): void {
		if ($event->getParticipant()->getAttendee()->getActorType() !== Attendee::ACTOR_USERS) {
			return;
		}

		if ($event->getProperty() !== AParticipantModifiedEvent::PROPERTY_IN_CALL) {
			return;
		}

		if ($event->getDetail(AParticipantModifiedEvent::DETAIL_IN_CALL_END_FOR_EVERYONE)) {
			// Do not revert the status with 3 queries per user.
			// We will update it in one go at the end.
			return;
		}

		if ($event->getOldValue() === Participant::FLAG_DISCONNECTED && $event->getNewValue() !== Participant::FLAG_DISCONNECTED) {
			$this->setUserStatus($event);
		} elseif ($event->getOldValue() !== Participant::FLAG_DISCONNECTED && $event->getNewValue() === Participant::FLAG_DISCONNECTED) {
			$this->revertUserStatusOnLeaveCall($event);
		}
	}

	protected function setUserStatus(BeforeParticipantModifiedEvent $event): void {

		$status = IUserStatus::AWAY;

		$userId = $event->getParticipant()->getAttendee()->getActorId();

		$statuses = $this->statusManager->getUserStatuses([$userId]);

		if (isset($statuses[$userId])) {
			if ($statuses[$userId]->getStatus() === IUserStatus::INVISIBLE) {
				// If the user is invisible we do not overwrite the status
				// with "in a call" which would be visible to any user on the
				// instance opposed to users in the conversation the call is happening
				return;
			}

			if ($statuses[$userId]->getStatus() === IUserStatus::DND) {
				$status = IUserStatus::DND;
			}
		}

		$this->statusManager->setUserStatus(
			$userId,
			'call',
			$status,
			true
		);
	}

	protected function revertUserStatusOnLeaveCall(BeforeParticipantModifiedEvent $event): void {
		$this->statusManager->revertUserStatus($event->getParticipant()->getAttendee()->getActorId(), 'call', IUserStatus::AWAY);
	}

	protected function revertUserStatusOnEndCallForEveryone(CallEndedForEveryoneEvent $event): void {
		$userIds = $event->getUserIds();
		if (!empty($userIds)) {
			$this->statusManager->revertMultipleUserStatus($userIds, 'call', IUserStatus::AWAY);
		}
	}
}
