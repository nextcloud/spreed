<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof AttendeeRemovedEvent) {
			return;
		}

		$attendee = $event->getAttendee();
		if ($attendee->getActorType() !== Attendee::ACTOR_FEDERATED_USERS) {
			return;
		}

		$this->retryNotificationMapper->deleteByProviderId(
			(string)$event->getAttendee()->getId()
		);
	}
}
