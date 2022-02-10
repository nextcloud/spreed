<?php

declare(strict_types=1);
/**
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

use OCA\Talk\Events\EndCallForEveryoneEvent;
use OCA\Talk\Events\ModifyEveryoneEvent;
use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\UserStatus\IManager;
use OCP\UserStatus\IUserStatus;

class Listener {
	/** @var IManager $statusManager */
	public $statusManager;

	public function __construct(IManager $statusManager) {
		$this->statusManager = $statusManager;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener(Room::EVENT_BEFORE_SESSION_JOIN_CALL, static function (ModifyParticipantEvent $event) {
			/** @var self $listener */
			$listener = \OC::$server->get(self::class);
			$listener->setUserStatus($event);
		});

		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_LEAVE_CALL, static function (ModifyParticipantEvent $event) {
			/** @var self $listener */
			$listener = \OC::$server->get(self::class);
			$listener->revertUserStatus($event);
		});

		$dispatcher->addListener(Room::EVENT_AFTER_END_CALL_FOR_EVERYONE, static function (EndCallForEveryoneEvent $event) {
			/** @var self $listener */
			$listener = \OC::$server->get(self::class);
			$listener->revertUserStatusOnEndCallForEveryone($event);
		});
	}

	public function setUserStatus(ModifyParticipantEvent $event): void {
		if ($event->getParticipant()->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
			$this->statusManager->setUserStatus($event->getParticipant()->getAttendee()->getActorId(), 'call', IUserStatus::AWAY, true);
		}
	}

	public function revertUserStatus(ModifyParticipantEvent $event): void {
		if ($event instanceof ModifyEveryoneEvent) {
			// Do not revert the status with 3 queries per user.
			// We will update it in one go at the end.
			return;
		}

		if ($event->getParticipant()->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
			$this->statusManager->revertUserStatus($event->getParticipant()->getAttendee()->getActorId(), 'call', IUserStatus::AWAY);
		}
	}

	public function revertUserStatusOnEndCallForEveryone(EndCallForEveryoneEvent $event): void {
		$userIds = $event->getUserIds();
		if (!empty($userIds)) {
			$this->statusManager->revertMultipleUserStatus($userIds, 'call', IUserStatus::AWAY);
		}
	}
}
