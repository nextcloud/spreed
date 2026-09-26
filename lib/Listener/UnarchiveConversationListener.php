<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Service\ConversationUnarchiveService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class UnarchiveConversationListener implements IEventListener {
	public function __construct(
		private readonly ConversationUnarchiveService $unarchiveService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof ChatMessageSentEvent) {
			return;
		}

		$this->unarchiveService->unarchiveAfterMessage(
			$event->getRoom(),
			$event->getComment(),
			$event->getParticipant(),
			$event->getParent(),
		);
	}
}
