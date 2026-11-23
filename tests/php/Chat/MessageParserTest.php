<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Chat;

use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\BotService;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class MessageParserTest extends TestCase {
	public static function dataAddActorRole(): array {
		return [
			'owner' => [Room::TYPE_GROUP, Participant::OWNER, Message::ACTOR_ROLE_OWNER],
			'moderator' => [Room::TYPE_PUBLIC, Participant::MODERATOR, Message::ACTOR_ROLE_MODERATOR],
			'guest moderator' => [Room::TYPE_PUBLIC, Participant::GUEST_MODERATOR, Message::ACTOR_ROLE_MODERATOR],
			'user' => [Room::TYPE_GROUP, Participant::USER, null],
			'guest' => [Room::TYPE_PUBLIC, Participant::GUEST, null],
			'not stored' => [Room::TYPE_GROUP, null, null],
			'one-to-one' => [Room::TYPE_ONE_TO_ONE, Participant::OWNER, null],
			'former one-to-one' => [Room::TYPE_ONE_TO_ONE_FORMER, Participant::OWNER, null],
			'changelog' => [Room::TYPE_CHANGELOG, Participant::OWNER, null],
			'note to self' => [Room::TYPE_NOTE_TO_SELF, Participant::OWNER, null],
		];
	}

	#[DataProvider('dataAddActorRole')]
	public function testAddActorRole(int $roomType, ?int $participantType, ?string $expected): void {
		$parser = new MessageParser(
			$this->createMock(IEventDispatcher::class),
			$this->createMock(IUserManager::class),
			$this->createMock(ParticipantService::class),
			$this->createMock(BotService::class),
		);
		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn($roomType);

		$metaData = $participantType === null ? [] : [Message::METADATA_ACTOR_PARTICIPANT_TYPE => $participantType];
		$actual = self::invokePrivate($parser, 'addActorRole', [$room, $metaData]);
		$this->assertSame($expected, $actual[Message::METADATA_ACTOR_ROLE] ?? null);
	}
}
