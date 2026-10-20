<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Model;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Room;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class MessageTest extends TestCase {
	public static function dataIsReplyable(): array {
		return [
			'user' => [Attendee::ACTOR_USERS, ChatManager::VERB_MESSAGE, true],
			'matrix user' => [Attendee::ACTOR_MATRIX, ChatManager::VERB_MESSAGE, true],
			'matrix attachment' => [Attendee::ACTOR_MATRIX, ChatManager::VERB_OBJECT_SHARED, true],
			'deleted message of matrix user' => [Attendee::ACTOR_MATRIX, ChatManager::VERB_MESSAGE_DELETED, false],
			'bridged' => [Attendee::ACTOR_BRIDGED, ChatManager::VERB_MESSAGE, false],
		];
	}

	#[DataProvider('dataIsReplyable')]
	public function testIsReplyable(string $actorType, string $messageType, bool $expected): void {
		$message = new Message($this->createMock(Room::class), null, null, $this->createMock(IL10N::class));
		$message->setActor($actorType, 'actor', 'Actor');
		$message->setMessageType($messageType);

		self::assertSame($expected, $message->isReplyable());
	}
}
