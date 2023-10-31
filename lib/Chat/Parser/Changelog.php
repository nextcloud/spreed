<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
			$chatMessage->getActorId() !== Attendee::ACTOR_ID_CHANGELOG) {
			return;
		}

		$l = $chatMessage->getL10n();
		$chatMessage->setActor(Attendee::ACTOR_BOTS, Attendee::ACTOR_ID_CHANGELOG, $l->t('Talk updates ✅'));
		$event->stopPropagation();
	}
}
