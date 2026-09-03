<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Migration;

use OCA\Talk\Migration\CleanupOldShares;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Share\RoomShareProvider;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Server;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

#[Group('DB')]
class CleanupOldSharesTest extends TestCase {
	protected IDBConnection $connection;
	protected IOutput&MockObject $output;
	protected CleanupOldShares $repairStep;

	/** @var list<int> */
	protected array $shareIds = [];
	/** @var list<int> */
	protected array $roomIds = [];
	/** @var list<int> */
	protected array $attendeeIds = [];

	#[\Override]
	public function setUp(): void {
		parent::setUp();

		$this->connection = Server::get(IDBConnection::class);
		$this->output = $this->createMock(IOutput::class);

		$this->repairStep = new CleanupOldShares(
			$this->connection,
		);
	}

	#[\Override]
	public function tearDown(): void {
		$this->deleteRows('share', $this->shareIds);
		$this->deleteRows('talk_attendees', $this->attendeeIds);
		$this->deleteRows('talk_rooms', $this->roomIds);

		$this->shareIds = [];
		$this->attendeeIds = [];
		$this->roomIds = [];

		parent::tearDown();
	}

	/**
	 * @param list<int> $ids
	 */
	protected function deleteRows(string $table, array $ids): void {
		if (empty($ids)) {
			return;
		}

		$delete = $this->connection->getQueryBuilder();
		$delete->delete($table)
			->where($delete->expr()->in('id', $delete->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$delete->executeStatement();
	}

	protected function createRoom(string $token): int {
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('talk_rooms')
			->values([
				'token' => $insert->createNamedParameter($token),
				'type' => $insert->createNamedParameter(Room::TYPE_GROUP, IQueryBuilder::PARAM_INT),
			]);
		$insert->executeStatement();

		$roomId = $insert->getLastInsertId();
		$this->roomIds[] = $roomId;
		return $roomId;
	}

	protected function createAttendee(int $roomId, string $actorType, string $actorId): int {
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('talk_attendees')
			->values([
				'room_id' => $insert->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
				'actor_type' => $insert->createNamedParameter($actorType),
				'actor_id' => $insert->createNamedParameter($actorId),
			]);
		$insert->executeStatement();

		$attendeeId = $insert->getLastInsertId();
		$this->attendeeIds[] = $attendeeId;
		return $attendeeId;
	}

	protected function createShare(int $shareType, string $shareWith, ?int $parent = null): int {
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('share')
			->values([
				'share_type' => $insert->createNamedParameter($shareType, IQueryBuilder::PARAM_INT),
				'share_with' => $insert->createNamedParameter($shareWith),
				'uid_owner' => $insert->createNamedParameter('owner'),
				'uid_initiator' => $insert->createNamedParameter('owner'),
				'item_type' => $insert->createNamedParameter('file'),
				'file_source' => $insert->createNamedParameter(42, IQueryBuilder::PARAM_INT),
				'file_target' => $insert->createNamedParameter('/file.txt'),
				'parent' => $insert->createNamedParameter($parent, $parent === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
			]);
		$insert->executeStatement();

		$shareId = $insert->getLastInsertId();
		$this->shareIds[] = $shareId;
		return $shareId;
	}

	protected function shareExists(int $id): bool {
		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->from('share')
			->where($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();

		return $row !== false;
	}

	public function testGetName(): void {
		$this->assertSame('Remove old user-room-shares', $this->repairStep->getName());
	}

	/**
	 * User-room shares of attendees that are still in the conversation must be
	 * kept, all others must be removed.
	 */
	public function testRunRemovesSharesOfFormerAttendees(): void {
		$roomId = $this->createRoom('token123');
		$this->createAttendee($roomId, Attendee::ACTOR_USERS, 'still-in-room');

		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$keptShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'still-in-room', $roomShare);
		$removedShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'left-the-room', $roomShare);

		$this->repairStep->run($this->output);

		$this->assertTrue($this->shareExists($roomShare), 'The room share itself must not be touched');
		$this->assertTrue($this->shareExists($keptShare), 'The share of an attendee must not be removed');
		$this->assertFalse($this->shareExists($removedShare), 'The share of a former attendee must be removed');
	}

	/**
	 * Attendees are matched per conversation, so being in another conversation
	 * does not keep the share alive.
	 */
	public function testRunIgnoresAttendeesOfOtherRooms(): void {
		$roomId = $this->createRoom('token123');
		$otherRoomId = $this->createRoom('token456');
		$this->createAttendee($otherRoomId, Attendee::ACTOR_USERS, 'other-room-only');

		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$removedShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'other-room-only', $roomShare);

		$this->repairStep->run($this->output);

		$this->assertTrue($this->shareExists($roomShare));
		$this->assertFalse($this->shareExists($removedShare));
	}

	/**
	 * Only user attendees can receive shares, so an attendee entry of another
	 * actor type with the same actor id must not keep the share alive.
	 */
	public function testRunIgnoresAttendeesOfOtherActorTypes(): void {
		$roomId = $this->createRoom('token123');
		$this->createAttendee($roomId, Attendee::ACTOR_GUESTS, 'same-actor-id');
		$this->createAttendee($roomId, Attendee::ACTOR_GROUPS, 'same-actor-id');

		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$removedShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'same-actor-id', $roomShare);

		$this->repairStep->run($this->output);

		$this->assertFalse($this->shareExists($removedShare));
	}

	/**
	 * When the conversation was deleted the room is gone, so all its user-room
	 * shares are orphaned.
	 */
	public function testRunRemovesSharesOfDeletedRooms(): void {
		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'deleted-room-token');
		$removedShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'user1', $roomShare);

		$this->repairStep->run($this->output);

		$this->assertFalse($this->shareExists($removedShare));
	}

	/**
	 * User-room shares without an existing parent room share can not be
	 * resolved to a conversation anymore and are therefore orphaned as well.
	 */
	public function testRunRemovesSharesWithoutParent(): void {
		$removedShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'user1');

		$this->repairStep->run($this->output);

		$this->assertFalse($this->shareExists($removedShare));
	}

	/**
	 * The repair step must only touch user-room shares.
	 */
	public function testRunKeepsOtherShareTypes(): void {
		$userShare = $this->createShare(IShare::TYPE_USER, 'not-an-attendee');
		$groupShare = $this->createShare(IShare::TYPE_GROUP, 'not-an-attendee');
		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'deleted-room-token');

		$this->repairStep->run($this->output);

		$this->assertTrue($this->shareExists($userShare));
		$this->assertTrue($this->shareExists($groupShare));
		$this->assertTrue($this->shareExists($roomShare));
	}

	/**
	 * The progress bar is advanced for every deleted share, is closed again
	 * when there is nothing left to delete, and the summary reports the total
	 * over all chunks.
	 */
	public function testRunReportsProgress(): void {
		$roomId = $this->createRoom('token123');
		$this->createAttendee($roomId, Attendee::ACTOR_USERS, 'still-in-room');

		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'still-in-room', $roomShare);
		$this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'left-the-room', $roomShare);
		$this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'also-left-the-room', $roomShare);

		$advanced = 0;
		$this->output->expects($this->once())
			->method('startProgress');
		$this->output->expects($this->once())
			->method('finishProgress');
		$this->output->method('advance')
			->willReturnCallback(static function ($step = 1) use (&$advanced): void {
				$advanced += (int)$step;
			});
		$this->output->expects($this->once())
			->method('info')
			->with('Deleted 2 stray shares');

		$this->repairStep->run($this->output);

		$this->assertSame(2, $advanced, 'Both former attendees should have been counted');
	}

	/**
	 * When there is nothing to clean up the summary must report zero.
	 */
	public function testRunWithoutStrayShares(): void {
		$roomId = $this->createRoom('token123');
		$this->createAttendee($roomId, Attendee::ACTOR_USERS, 'still-in-room');

		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$keptShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'still-in-room', $roomShare);

		$this->output->expects($this->never())
			->method('advance');
		$this->output->expects($this->once())
			->method('info')
			->with('Deleted 0 stray shares');

		$this->repairStep->run($this->output);

		$this->assertTrue($this->shareExists($keptShare));
	}

	/**
	 * Running the repair step twice must be a no-op the second time.
	 */
	public function testRunIsIdempotent(): void {
		$roomId = $this->createRoom('token123');
		$this->createAttendee($roomId, Attendee::ACTOR_USERS, 'still-in-room');

		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$keptShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'still-in-room', $roomShare);
		$removedShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'left-the-room', $roomShare);

		$this->repairStep->run($this->output);
		$this->repairStep->run($this->output);

		$this->assertTrue($this->shareExists($keptShare));
		$this->assertFalse($this->shareExists($removedShare));
	}
}
