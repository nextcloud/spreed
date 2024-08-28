<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat\Parser;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\MessageParseEvent;
use OCA\Talk\Model\Attendee;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class Changelog implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof MessageParseEvent) {
			return;
		}

		$chatMessage = $event->getMessage();
		if ($chatMessage->getMessageType() !== ChatManager::VERB_MESSAGE) {
			return;
		}

		if ($chatMessage->getActorType() !== Attendee::ACTOR_GUESTS ||
			$chatMessage->getActorId() !== Attendee::CHANGELOG_ACTOR_ID) {
			return;
		}

		$l = $chatMessage->getL10n();
		$chatMessage->setActor(Attendee::ACTOR_BOTS, Attendee::CHANGELOG_ACTOR_ID, $l->t('Talk updates ✅'));
		$event->stopPropagation();
	}
}
