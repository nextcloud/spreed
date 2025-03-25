<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Events\BeforeRoomsFetchEvent;
use OCA\Talk\Service\SampleConversationsService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class SampleConversationsListener implements IEventListener {
	public function __construct(
		protected SampleConversationsService $service,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof BeforeRoomsFetchEvent) {
			$this->service->initialCreateSamples($event->getUserId());
		}
	}
}
