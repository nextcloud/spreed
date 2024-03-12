<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Federation\Proxy\TalkV1\Notifier;

use OCA\Talk\Events\AttendeeRemovedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\RetryNotificationMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class CancelRetryOCMListener implements IEventListener {
	public function __construct(
		protected RetryNotificationMapper $retryNotificationMapper,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AttendeeRemovedEvent) {
			return;
		}

		$attendee = $event->getAttendee();
		if ($attendee->getActorType() !== Attendee::ACTOR_FEDERATED_USERS) {
			return;
		}

		$this->retryNotificationMapper->deleteByProviderId(
			(string) $event->getAttendee()->getId()
		);
	}
}
