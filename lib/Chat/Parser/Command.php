<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
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
class Command implements IEventListener {
	public const RESPONSE_NONE = 0;
	public const RESPONSE_USER = 1;
	public const RESPONSE_ALL = 2;

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof MessageParseEvent) {
			return;
		}

		$message = $event->getMessage();

		if ($message->getMessageType() !== ChatManager::VERB_COMMAND) {
			return;
		}

		$message->setVisibility(false);

		$comment = $message->getComment();
		$data = json_decode($comment->getMessage(), true);
		if (!\is_array($data)) {
			return;
		}

		$event->stopPropagation();

		if ($data['visibility'] === self::RESPONSE_NONE) {
			$message->setVisibility(false);
			return;
		}

		$participant = $message->getParticipant();
		if ($data['visibility'] !== self::RESPONSE_ALL
			&& $participant !== null
			&& ($participant->getAttendee()->getActorType() !== Attendee::ACTOR_USERS
				|| $data['user'] !== $participant->getAttendee()->getActorId())) {
			$message->setVisibility(false);
			return;
		}

		$message->setMessage($data['output'], []);
	}
}
