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
use OCP\Defaults;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Server;

/**
 * @template-implements IEventListener<Event>
 */
class Changelog implements IEventListener {
	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof MessageParseEvent) {
			return;
		}

		$chatMessage = $event->getMessage();
		if ($chatMessage->getMessageType() !== ChatManager::VERB_MESSAGE) {
			return;
		}

		if ($chatMessage->getActorType() !== Attendee::ACTOR_GUESTS) {
			return;
		}

		if ($chatMessage->getActorId() === Attendee::ACTOR_ID_CHANGELOG) {
			$l = $chatMessage->getL10n();
			$chatMessage->setActor(Attendee::ACTOR_BOTS, Attendee::ACTOR_ID_CHANGELOG, $l->t('Talk updates ✅'));
			$event->stopPropagation();
		}

		if ($chatMessage->getActorId() === Attendee::ACTOR_ID_SAMPLE) {
			$theme = Server::get(Defaults::class);
			$chatMessage->setActor(Attendee::ACTOR_BOTS, Attendee::ACTOR_ID_SAMPLE, $theme->getName());
		}
	}
}
