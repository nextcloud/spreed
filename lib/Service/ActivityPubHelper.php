<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Events\BotInvokeEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BotServer;
use OCA\Talk\Model\Message;
use OCA\Talk\Room;
use OCP\Comments\IComment;

/**
 * @psalm-import-type ChatMessageParentData from BotInvokeEvent
 * @psalm-type NoteType = array{type: 'Note', id: numeric-string, name: string, content: string, mediaType: 'text/markdown'|'text/plain'}
 */
class ActivityPubHelper {
	/**
	 * @return array{type: 'Application', id: non-falsy-string, name: string}
	 */
	public function generateApplicationFromBot(BotServer $bot): array {
		return [
			'type' => 'Application',
			'id' => Attendee::ACTOR_BOTS . '/' . Attendee::ACTOR_BOT_PREFIX . $bot->getUrlHash(),
			'name' => $bot->getName(),
		];
	}

	/**
	 * @return array{type: 'Collection', id: non-empty-string, name: string}
	 */
	public function generateCollectionFromRoom(Room $room): array {
		/** @var non-empty-string $token */
		$token = $room->getToken();
		return [
			'type' => 'Collection',
			'id' => $token,
			'name' => $room->getName(),
		];
	}

	/**
	 * @psalm-param ?ChatMessageParentData $inReplyTo
	 * @psalm-return NoteType&array{inReplyTo?: ChatMessageParentData}
	 */
	public function generateNote(IComment $comment, array $messageData, string $messageType, ?array $inReplyTo = null): array {
		/** @var string $content */
		$content = json_encode($messageData, JSON_THROW_ON_ERROR);
		/** @var numeric-string $messageId */
		$messageId = $comment->getId();
		/** @var 'text/markdown'|'text/plain' $mediaType */
		$mediaType = 'text/markdown';// FIXME or text/plain when markdown is disabled
		$note = [
			'type' => 'Note',
			'id' => $messageId,
			'name' => $messageType,
			'content' => $content,
			'mediaType' => $mediaType,
		];
		if ($inReplyTo !== null) {
			$note['inReplyTo'] = $inReplyTo;
		}
		return $note;
	}

	/**
	 * @return array{type: 'Person', id: non-falsy-string, name: string, talkParticipantType: numeric-string}
	 */
	public function generatePersonFromAttendee(Attendee $attendee): array {
		return [
			'type' => 'Person',
			'id' => $attendee->getActorType() . '/' . $attendee->getActorId(),
			'name' => $attendee->getDisplayName(),
			'talkParticipantType' => (string)$attendee->getParticipantType(),
		];
	}

	/**
	 * @return array{type: 'Person', id: non-falsy-string, name: string}
	 */
	public function generatePersonFromMessageActor(Message $message): array {
		return [
			'type' => 'Person',
			'id' => $message->getActorType() . '/' . $message->getActorId(),
			'name' => $message->getActorDisplayName(),
		];
	}
}
