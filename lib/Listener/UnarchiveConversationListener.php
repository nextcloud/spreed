<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
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
		// Shared files and objects are system messages, but count as unread messages
		if (!$event instanceof ChatMessageSentEvent
			&& !($event instanceof SystemMessageSentEvent && $event->getComment()->getVerb() === ChatManager::VERB_OBJECT_SHARED)) {
			return;
		}

		$this->unarchiveService->unarchiveAfterMessage(
			$event->getRoom(),
			$event->getComment(),
			$event->getParent(),
		);
	}
}
