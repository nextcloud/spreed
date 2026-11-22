<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Service\TeamRoomSyncService;
use OCP\App\Events\AppEnableEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Team rooms may be out of sync after Circles or Talk was disabled: resync
 * them as soon as possible instead of waiting for the hourly job.
 *
 * @template-implements IEventListener<Event>
 */
class CirclesEnabledListener implements IEventListener {
	public function __construct(
		protected readonly TeamRoomSyncService $syncService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof AppEnableEvent || $event->getAppId() !== 'circles') {
			return;
		}

		$this->syncService->queueFullResync();
	}
}
