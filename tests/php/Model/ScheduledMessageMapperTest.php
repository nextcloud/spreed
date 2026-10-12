<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Model;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\ScheduledMessage;
use OCA\Talk\Model\ScheduledMessageMapper;
use OCA\Talk\Room;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class ScheduledMessageMapperTest extends TestCase {
	protected ScheduledMessageMapper $mapper;
	protected Room $room;
	protected string $actorId;

	public function setUp(): void {
		parent::setUp();

		$this->mapper = new ScheduledMessageMapper(Server::get(IDBConnection::class));
		$roomId = random_int(1_000_000, 9_000_000);
		$this->room = $this->createMock(Room::class);
		$this->room->method('getId')->willReturn($roomId);
		$this->actorId = 'scheduled-order-' . $roomId;
	}

	public function tearDown(): void {
		$this->mapper->deleteMessagesByRoomAndActor($this->room, Attendee::ACTOR_USERS, $this->actorId);
		parent::tearDown();
	}

	/**
	 * Generates the ids in creation order but inserts the messages in reverse,
	 * so the result order only matches when the query breaks ties by id
	 *
	 * @return list<string> Ids in creation order
	 */
	protected function scheduleMessagesInSameSecond(int $count): array {
		$sendAt = new \DateTime('2026-01-01 12:00:00');
		$messages = [];
		for ($i = 0; $i < $count; $i++) {
			$message = new ScheduledMessage();
			$message->generateId();
			$message->setRoomId($this->room->getId());
			$message->setActorType(Attendee::ACTOR_USERS);
			$message->setActorId($this->actorId);
			$message->setMessage('Message ' . $i);
			$message->setMessageType('comment');
			$message->setSendAt($sendAt);
			$messages[] = $message;
		}

		foreach (array_reverse($messages) as $message) {
			$this->mapper->insert($message);
		}

		return array_map(static fn (ScheduledMessage $message): string => (string)$message->getId(), $messages);
	}

	public function testFindByRoomAndActorOrdersSameSendAtById(): void {
		$expected = $this->scheduleMessagesInSameSecond(3);

		$rows = $this->mapper->findByRoomAndActor($this->room, Attendee::ACTOR_USERS, $this->actorId);

		$this->assertSame($expected, array_map(static fn (array $row): string => (string)$row['id'], $rows));
	}

	public function testGetMessagesDueOrdersSameSendAtById(): void {
		$expected = $this->scheduleMessagesInSameSecond(3);

		$due = array_values(array_filter(
			$this->mapper->getMessagesDue(new \DateTime('2026-01-01 12:00:00')),
			fn (ScheduledMessage $message): bool => $message->getActorId() === $this->actorId,
		));

		$this->assertSame($expected, array_map(static fn (ScheduledMessage $message): string => (string)$message->getId(), $due));
	}
}
