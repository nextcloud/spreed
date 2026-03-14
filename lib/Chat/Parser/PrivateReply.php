<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat\Parser;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\MessageParseEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class PrivateReply implements IEventListener {

    #[\Override]
    public function handle(Event $event): void {
        if (!$event instanceof MessageParseEvent) {
            return;
        }

        $message = $event->getMessage();

        if ($message->getMessageType() !== ChatManager::VERB_PRIVATE_REPLY) {
            return;
        }

        if ($message->getComment()->getParentId() === '0') {
            $message->setVisibility(false);
            $event->stopPropagation();
            return;
        }
    }
}