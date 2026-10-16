<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Migration;

use OCA\Talk\Migration\FixHasFederationFlag;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

#[Group('DB')]
class FixHasFederationFlagTest extends TestCase {
	protected IDBConnection $connection;
	protected IOutput&MockObject $output;
	protected FixHasFederationFlag $repairStep;

	/** @var list<int> */
	protected array $roomIds = [];
	/** @var list<int> */
	protected array $attendeeIds = [];

	#[\Override]
	public function setUp(): void {
		parent::setUp();

		$this->connection = Server::get(IDBConnection::class);
		$this->output = $this->createMock(IOutput::class);

		$this->repairStep = new FixHasFederationFlag(
			$this->connection,
		);
	}

	#[\Override]
	public function tearDown(): void {
		$this->deleteRows('talk_attendees', $this->attendeeIds);
		$this->deleteRows('talk_rooms', $this->roomIds);

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

	protected function createRoom(string $token, int $hasFederation): int {
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('talk_rooms')
			->values([
				'token' => $insert->createNamedParameter($token),
				'type' => $insert->createNamedParameter(Room::TYPE_GROUP, IQueryBuilder::PARAM_INT),
				'has_federation' => $insert->createNamedParameter($hasFederation, IQueryBuilder::PARAM_INT),
			]);
		$insert->executeStatement();

		$roomId = $insert->getLastInsertId();
		$this->roomIds[] = $roomId;
		return $roomId;
	}

	protected function createAttendee(int $roomId, string $actorType, string $actorId): void {
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('talk_attendees')
			->values([
				'room_id' => $insert->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
				'actor_type' => $insert->createNamedParameter($actorType),
				'actor_id' => $insert->createNamedParameter($actorId),
			]);
		$insert->executeStatement();

		$this->attendeeIds[] = $insert->getLastInsertId();
	}

	protected function getFlag(int $roomId): int {
		$query = $this->connection->getQueryBuilder();
		$query->select('has_federation')
			->from('talk_rooms')
			->where($query->expr()->eq('id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$flag = (int)$result->fetchOne();
		$result->closeCursor();

		return $flag;
	}

	public function testRun(): void {
		$missingFlag = $this->createRoom('missing', Room::HAS_FEDERATION_NONE);
		$this->createAttendee($missingFlag, Attendee::ACTOR_USERS, 'user1');
		$this->createAttendee($missingFlag, Attendee::ACTOR_FEDERATED_USERS, 'user1@remote1');
		$this->createAttendee($missingFlag, Attendee::ACTOR_FEDERATED_USERS, 'user2@remote2');

		$staleFlag = $this->createRoom('stale', Room::HAS_FEDERATION_TALKv1);
		$this->createAttendee($staleFlag, Attendee::ACTOR_USERS, 'user1');

		$correctFlag = $this->createRoom('correct', Room::HAS_FEDERATION_TALKv1);
		$this->createAttendee($correctFlag, Attendee::ACTOR_FEDERATED_USERS, 'user1@remote1');

		$noFederation = $this->createRoom('none', Room::HAS_FEDERATION_NONE);
		$this->createAttendee($noFederation, Attendee::ACTOR_USERS, 'user1');

		$this->output->expects($this->once())
			->method('info')
			->with('Federation flag added to 1 and removed from 1 conversations');

		$this->repairStep->run($this->output);

		$this->assertSame(Room::HAS_FEDERATION_TALKv1, $this->getFlag($missingFlag));
		$this->assertSame(Room::HAS_FEDERATION_NONE, $this->getFlag($staleFlag));
		$this->assertSame(Room::HAS_FEDERATION_TALKv1, $this->getFlag($correctFlag));
		$this->assertSame(Room::HAS_FEDERATION_NONE, $this->getFlag($noFederation));
	}
}
