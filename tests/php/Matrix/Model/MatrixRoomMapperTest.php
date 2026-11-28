<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Model;

use OCA\Talk\Manager;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class MatrixRoomMapperTest extends TestCase {
	protected MatrixRoomMapper $mapper;
	protected string $suffix;
	/** @var list<int> */
	protected array $roomIds = [];
	/** @var list<MatrixRoom> */
	protected array $matrixRooms = [];

	public function setUp(): void {
		parent::setUp();
		$this->mapper = new MatrixRoomMapper(Server::get(IDBConnection::class));
		$this->suffix = (string)random_int(1_000_000, 9_000_000);
	}

	public function tearDown(): void {
		foreach ($this->matrixRooms as $matrixRoom) {
			$this->mapper->delete($matrixRoom);
		}
		$db = Server::get(IDBConnection::class);
		foreach (['talk_attendees' => 'room_id', 'talk_rooms' => 'id'] as $table => $column) {
			$qb = $db->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->in($column, $qb->createNamedParameter($this->roomIds, IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		parent::tearDown();
	}

	/**
	 * @param list<string> $userIds Users that take part in the conversation
	 * @return string Token of the conversation, empty when the room is not mirrored yet
	 */
	protected function createMatrixRoom(string $matrixRoomId, bool $mirrored, array $userIds): string {
		$token = '';
		$matrixRoom = new MatrixRoom();
		$matrixRoom->setMatrixRoomId($matrixRoomId . $this->suffix . ':hs');
		if ($mirrored) {
			$room = Server::get(Manager::class)->createRoom(Room::TYPE_GROUP, $matrixRoomId);
			$this->roomIds[] = $room->getId();
			$token = $room->getToken();
			$matrixRoom->setRoomId($room->getId());
			foreach ($userIds as $userId) {
				$attendee = new Attendee();
				$attendee->setRoomId($room->getId());
				$attendee->setActorType(Attendee::ACTOR_USERS);
				$attendee->setActorId($userId . $this->suffix);
				$attendee->setParticipantType(Participant::USER);
				Server::get(AttendeeMapper::class)->insert($attendee);
			}
		}
		$this->matrixRooms[] = $this->mapper->insert($matrixRoom);
		return $token;
	}

	public function testFindConversationTokens(): void {
		$mine = $this->createMatrixRoom('!mine', true, ['alice', 'bob']);
		$this->createMatrixRoom('!other', true, ['bob']);
		$this->createMatrixRoom('!invited', false, []);

		$ids = array_map(fn (string $id): string => $id . $this->suffix . ':hs', ['!mine', '!other', '!invited', '!unknown']);

		self::assertSame([$ids[0] => $mine], $this->mapper->findConversationTokens($ids, 'alice' . $this->suffix));
		self::assertSame([], $this->mapper->findConversationTokens([], 'alice' . $this->suffix));
	}
}
