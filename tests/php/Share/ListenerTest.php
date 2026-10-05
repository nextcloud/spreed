<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Share;

use OC\Share20\Share;
use OCA\Files_Sharing\Event\ShareMountedEvent;
use OCA\Files_Sharing\SharedMount;
use OCA\Talk\Config;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Share\Listener;
use OCA\Talk\Share\RoomShareProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ListenerTest extends TestCase {
	/** Node id of the shared file */
	protected const SHARED_NODE_ID = 42;
	/** Node id of another file with the same name */
	protected const OTHER_NODE_ID = 23;

	protected Config&MockObject $config;
	protected RoomShareProvider&MockObject $roomShareProvider;
	protected IMountManager&MockObject $mountManager;
	protected IMountProviderCollection&MockObject $mountProviderCollection;
	protected IUserMountCache&MockObject $userMountCache;
	protected LoggerInterface&MockObject $logger;
	protected Listener $listener;
	/** @var list<string> */
	protected array $mountPoints = [];

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(Config::class);
		$this->roomShareProvider = $this->createMock(RoomShareProvider::class);
		$this->mountManager = $this->createMock(IMountManager::class);
		$this->mountProviderCollection = $this->createMock(IMountProviderCollection::class);
		$this->userMountCache = $this->createMock(IUserMountCache::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new Listener(
			$this->config,
			$this->roomShareProvider,
			$this->mountManager,
			$this->mountProviderCollection,
			$this->userMountCache,
			$this->logger,
		);
	}

	private function makeAttendee(string $actorType, string $actorId): Attendee {
		$attendee = new Attendee();
		$attendee->setActorType($actorType);
		$attendee->setActorId($actorId);
		return $attendee;
	}

	private function makeRoom(string $token): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getToken')->willReturn($token);
		return $room;
	}

	/**
	 * Only user attendees can have received shares, so the other actor types
	 * must not be forwarded to the share provider. The remaining actor ids must
	 * be passed on as a list, not with the keys of the original array.
	 */
	public function testRoomAttendeesRemovedOnlyHandlesUserAttendees(): void {
		$room = $this->makeRoom('token123');
		$event = new AttendeesRemovedEvent($room, [
			$this->makeAttendee(Attendee::ACTOR_GROUPS, 'group1'),
			$this->makeAttendee(Attendee::ACTOR_USERS, 'alice'),
			$this->makeAttendee(Attendee::ACTOR_GUESTS, 'guest1'),
			$this->makeAttendee(Attendee::ACTOR_USERS, 'bob'),
			$this->makeAttendee(Attendee::ACTOR_CIRCLES, 'circle1'),
			$this->makeAttendee(Attendee::ACTOR_FEDERATED_USERS, 'carol@remote.test'),
		]);

		$this->roomShareProvider->expects($this->once())
			->method('deleteReceivedSharesInRoom')
			->with('token123', ['alice', 'bob']);

		$this->listener->handle($event);
	}

	public function testRoomAttendeesRemovedWithoutUserAttendees(): void {
		$room = $this->makeRoom('token123');
		$event = new AttendeesRemovedEvent($room, [
			$this->makeAttendee(Attendee::ACTOR_GUESTS, 'guest1'),
		]);

		$this->roomShareProvider->expects($this->once())
			->method('deleteReceivedSharesInRoom')
			->with('token123', []);

		$this->listener->handle($event);
	}

	public function testRoomAttendeesRemovedWithoutAttendees(): void {
		$room = $this->makeRoom('token123');
		$event = new AttendeesRemovedEvent($room, []);

		$this->roomShareProvider->expects($this->once())
			->method('deleteReceivedSharesInRoom')
			->with('token123', []);

		$this->listener->handle($event);
	}
}
