<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

	#[\Override]
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

		$status = IUserStatus::BUSY;

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
		$this->statusManager->revertUserStatus($event->getParticipant()->getAttendee()->getActorId(), 'call', IUserStatus::BUSY);
	}

	protected function revertUserStatusOnEndCallForEveryone(CallEndedForEveryoneEvent $event): void {
		$userIds = $event->getUserIds();
		if (!empty($userIds)) {
			$this->statusManager->revertMultipleUserStatus($userIds, 'call', IUserStatus::BUSY);
		}
	}
}
