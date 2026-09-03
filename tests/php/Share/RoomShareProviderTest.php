<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Share;

use OCA\Talk\Config;
use OCA\Talk\Manager;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Share\RoomShareProvider;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IMimeTypeLoader;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use OCP\Server;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class RoomShareProviderTest extends TestCase {
	protected IDBConnection $connection;
	protected RoomShareProvider $provider;

	/** @var list<int> */
	protected array $shareIds = [];

	#[\Override]
	public function setUp(): void {
		parent::setUp();

		$this->connection = Server::get(IDBConnection::class);

		$this->provider = new RoomShareProvider(
			$this->connection,
			$this->createMock(ISecureRandom::class),
			$this->createMock(IShareManager::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(Manager::class),
			$this->createMock(ParticipantService::class),
			$this->createMock(RoomService::class),
			$this->createMock(ITimeFactory::class),
			$this->createMock(IL10N::class),
			$this->createMock(IMimeTypeLoader::class),
			$this->createMock(IUserManager::class),
			$this->createMock(Config::class),
		);
	}

	#[\Override]
	public function tearDown(): void {
		if (!empty($this->shareIds)) {
			$delete = $this->connection->getQueryBuilder();
			$delete->delete('share')
				->where($delete->expr()->in('id', $delete->createNamedParameter($this->shareIds, IQueryBuilder::PARAM_INT_ARRAY)));
			$delete->executeStatement();
			$this->shareIds = [];
		}

		parent::tearDown();
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

	/**
	 * Only the received shares of the given users in the given conversation
	 * must be removed, the room shares themselves stay untouched.
	 */
	public function testDeleteReceivedSharesInRoom(): void {
		$roomShare1 = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$roomShare2 = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$otherRoomShare = $this->createShare(IShare::TYPE_ROOM, 'token456');

		$aliceShare1 = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $roomShare1);
		$aliceShare2 = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $roomShare2);
		$bobShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'bob', $roomShare1);
		$aliceOtherRoomShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $otherRoomShare);

		$this->provider->deleteReceivedSharesInRoom('token123', ['alice']);

		$this->assertFalse($this->shareExists($aliceShare1));
		$this->assertFalse($this->shareExists($aliceShare2));
		$this->assertTrue($this->shareExists($bobShare), 'Shares of other users must be kept');
		$this->assertTrue($this->shareExists($aliceOtherRoomShare), 'Shares in other conversations must be kept');

		$this->assertTrue($this->shareExists($roomShare1), 'The room shares must be kept');
		$this->assertTrue($this->shareExists($roomShare2), 'The room shares must be kept');
		$this->assertTrue($this->shareExists($otherRoomShare), 'The room shares must be kept');
	}

	public function testDeleteReceivedSharesInRoomForMultipleUsers(): void {
		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');

		$aliceShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $roomShare);
		$bobShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'bob', $roomShare);
		$carolShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'carol', $roomShare);

		$this->provider->deleteReceivedSharesInRoom('token123', ['alice', 'bob']);

		$this->assertFalse($this->shareExists($aliceShare));
		$this->assertFalse($this->shareExists($bobShare));
		$this->assertTrue($this->shareExists($carolShare));
	}

	/**
	 * Removing attendees that are not users at all must not delete anything.
	 */
	public function testDeleteReceivedSharesInRoomWithoutUsers(): void {
		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$aliceShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $roomShare);

		$this->provider->deleteReceivedSharesInRoom('token123', []);

		$this->assertTrue($this->shareExists($aliceShare));
		$this->assertTrue($this->shareExists($roomShare));
	}

	/**
	 * A conversation without any share must not delete shares of other
	 * conversations that happen to have the same recipients.
	 */
	public function testDeleteReceivedSharesInRoomWithoutRoomShares(): void {
		$otherRoomShare = $this->createShare(IShare::TYPE_ROOM, 'token456');
		$aliceShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $otherRoomShare);

		$this->provider->deleteReceivedSharesInRoom('token123', ['alice']);

		$this->assertTrue($this->shareExists($aliceShare));
	}

	/**
	 * Shares of other types must not be deleted, even when they are children of
	 * the room share and belong to the given user.
	 */
	public function testDeleteReceivedSharesInRoomKeepsOtherShareTypes(): void {
		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$userShare = $this->createShare(IShare::TYPE_USER, 'alice', $roomShare);
		$userRoomShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $roomShare);

		$this->provider->deleteReceivedSharesInRoom('token123', ['alice']);

		$this->assertTrue($this->shareExists($userShare));
		$this->assertFalse($this->shareExists($userRoomShare));
	}
}
