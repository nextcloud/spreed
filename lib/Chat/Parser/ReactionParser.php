<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat\Parser;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\MessageParseEvent;
use OCA\Talk\Model\Message;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class ReactionParser implements IEventListener {
	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof MessageParseEvent) {
			return;
		}
		$message = $event->getMessage();
		if ($message->getMessageType() !== ChatManager::VERB_REACTION && $message->getMessageType() !== ChatManager::VERB_REACTION_DELETED) {
			return;
		}

		$comment = $message->getComment();
		if (!in_array($comment->getVerb(), [ChatManager::VERB_REACTION, ChatManager::VERB_REACTION_DELETED], true)) {
			return;
		}

		$message->setMessageType(ChatManager::VERB_SYSTEM);
		if ($comment->getVerb() === ChatManager::VERB_REACTION_DELETED) {
			// This message is necessary to make compatible with old clients
			$message->setMessage($message->getL10n()->t('Reaction deleted by author'), [], $comment->getVerb());
		} else {
			$message->setMessage($message->getMessage(), [], $comment->getVerb());
		}
	}
}
