<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\BackgroundJob;

use OCA\Talk\BackgroundJob\CopyRoomPasswordsToShares;
use OCA\Talk\Room;
use OCA\Talk\Share\RoomShareProvider;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class CopyRoomPasswordsToSharesTest extends TestCase {
	protected IDBConnection $connection;
	/** @var list<int> */
	protected array $roomIds = [];

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->connection = Server::get(IDBConnection::class);
	}

	#[\Override]
	public function tearDown(): void {
		$delete = $this->connection->getQueryBuilder();
		$delete->delete('talk_rooms')
			->where($delete->expr()->in('id', $delete->createNamedParameter($this->roomIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$delete->executeStatement();

		parent::tearDown();
	}

	protected function createRoom(string $token, string $password): void {
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('talk_rooms')
			->values([
				'name' => $insert->createNamedParameter($token),
				'type' => $insert->createNamedParameter(Room::TYPE_PUBLIC, IQueryBuilder::PARAM_INT),
				'token' => $insert->createNamedParameter($token),
				'password' => $insert->createNamedParameter($password),
			]);
		$insert->executeStatement();
		$this->roomIds[] = $insert->getLastInsertId();
	}

	public function testRun(): void {
		$this->createRoom('cprpwtoken', 'hash');
		$this->createRoom('cprnopwtoken', '');

		$calls = [];
		$roomShareProvider = $this->createMock(RoomShareProvider::class);
		$roomShareProvider->method('setPasswordInRoom')
			->willReturnCallback(static function (string $token, string $hash) use (&$calls): void {
				$calls[$token] = $hash;
			});

		$job = new CopyRoomPasswordsToShares(
			$this->createMock(ITimeFactory::class),
			$this->connection,
			$roomShareProvider,
		);
		self::invokePrivate($job, 'run', [null]);

		$this->assertSame('hash', $calls['cprpwtoken'] ?? null);
		$this->assertArrayNotHasKey('cprnopwtoken', $calls);
	}
}
