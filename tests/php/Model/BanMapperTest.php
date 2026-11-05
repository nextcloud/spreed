<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Model;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Ban;
use OCA\Talk\Model\BanMapper;
use OCP\Comments\ICommentsManager;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class BanMapperTest extends TestCase {
	protected BanMapper $mapper;
	protected int $roomId;

	public function setUp(): void {
		parent::setUp();

		$this->mapper = new BanMapper(Server::get(IDBConnection::class));
		$this->roomId = random_int(1_000_000, 9_000_000);
	}

	public function tearDown(): void {
		foreach ($this->mapper->findByRoomId($this->roomId) as $ban) {
			$this->mapper->delete($ban);
		}
		parent::tearDown();
	}

	protected function createBan(string $moderatorId, string $bannedId): void {
		$ban = new Ban();
		$ban->setRoomId($this->roomId);
		$ban->setModeratorActorType(Attendee::ACTOR_USERS);
		$ban->setModeratorActorId($moderatorId);
		$ban->setModeratorDisplayname($moderatorId . '-displayname');
		$ban->setBannedActorType(Attendee::ACTOR_USERS);
		$ban->setBannedActorId($bannedId);
		$ban->setBannedDisplayname($bannedId . '-displayname');
		$ban->setBannedTime(new \DateTime());
		$this->mapper->insert($ban);
	}

	public function testNeutralizeDeletedUser(): void {
		$this->createBan('moderator', 'deleted');
		$this->createBan('deleted', 'banned');
		$this->createBan('moderator', 'banned2');

		$this->mapper->neutralizeDeletedUser(Attendee::ACTOR_USERS, 'deleted');

		$bans = array_map(static fn (Ban $ban): array => [
			$ban->getModeratorActorType(),
			$ban->getModeratorActorId(),
			(string)$ban->getModeratorDisplayname(),
			$ban->getBannedActorId(),
		], $this->mapper->findByRoomId($this->roomId));

		$this->assertSame([
			[ICommentsManager::DELETED_USER, ICommentsManager::DELETED_USER, '', 'banned'],
			[Attendee::ACTOR_USERS, 'moderator', 'moderator-displayname', 'banned2'],
		], $bans);
	}
}
