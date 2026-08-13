<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\ParticipantProperty\ParticipantTypeException;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\MembershipService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudIdManager;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use OCP\UserStatus\IManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * Covers the authorization rules of
 * {@see ParticipantService::updateParticipantTypeByModerator()}, which is the
 * only way promotions and demotions triggered by a user reach
 * {@see ParticipantService::updateParticipantType()}.
 */
class ParticipantServiceParticipantTypeTest extends TestCase {
	protected AttendeeMapper&MockObject $attendeeMapper;
	protected ParticipantService $service;

	public function setUp(): void {
		parent::setUp();

		$this->attendeeMapper = $this->createMock(AttendeeMapper::class);
		$this->service = new ParticipantService(...$this->getConstructorArgs());
	}

	protected function getConstructorArgs(): array {
		return [
			$this->createMock(Config::class),
			$this->attendeeMapper,
			$this->createMock(SessionMapper::class),
			$this->createMock(SessionService::class),
			$this->createMock(ISecureRandom::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(IUserManager::class),
			$this->createMock(ICloudIdManager::class),
			$this->createMock(IGroupManager::class),
			$this->createMock(MembershipService::class),
			$this->createMock(BackendNotifier::class),
			$this->createMock(ITimeFactory::class),
			$this->createMock(ICacheFactory::class),
			$this->createMock(IManager::class),
			$this->createMock(LoggerInterface::class),
		];
	}

	protected function createRoom(int $type = Room::TYPE_GROUP, string $objectType = ''): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getId')->willReturn(1234);
		$room->method('getToken')->willReturn('token1234');
		$room->method('getType')->willReturn($type);
		$room->method('getObjectType')->willReturn($objectType);
		$room->method('getBreakoutRoomMode')->willReturn(BreakoutRoom::MODE_NOT_CONFIGURED);
		return $room;
	}

	protected function createParticipant(Room $room, int $participantType, string $actorId, string $actorType = Attendee::ACTOR_USERS): Participant {
		$attendee = Attendee::fromRow([
			'actor_type' => $actorType,
			'actor_id' => $actorId,
			'participant_type' => $participantType,
			'permissions' => Attendee::PERMISSIONS_DEFAULT,
		]);
		return new Participant($room, $attendee, null);
	}

	/**
	 * @param int $numberOfModerators Value {@see ParticipantService::getNumberOfModerators()} should report
	 */
	protected function expectModeratorCount(int $numberOfModerators): void {
		$this->attendeeMapper->method('countActorsByParticipantType')
			->willReturn($numberOfModerators);
	}

	public function testOwnerPromotesUserToOwner(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner');
		$target = $this->createParticipant($room, Participant::USER, 'user');
		$this->expectModeratorCount(2);

		$newType = $this->service->updateParticipantTypeByModerator($room, $actor, $target, true, Participant::OWNER);

		$this->assertSame(Participant::OWNER, $newType);
		$this->assertSame(Participant::OWNER, $target->getAttendee()->getParticipantType());
	}

	public function testOwnerPromotesModeratorToOwner(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner');
		$target = $this->createParticipant($room, Participant::MODERATOR, 'moderator');
		$this->expectModeratorCount(2);

		$newType = $this->service->updateParticipantTypeByModerator($room, $actor, $target, true, Participant::OWNER);

		$this->assertSame(Participant::OWNER, $newType);
	}

	public function testOwnerDemotesOtherOwnerToModerator(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner1');
		$target = $this->createParticipant($room, Participant::OWNER, 'owner2');
		$this->expectModeratorCount(2);

		$newType = $this->service->updateParticipantTypeByModerator($room, $actor, $target, false, Participant::MODERATOR);

		$this->assertSame(Participant::MODERATOR, $newType);
	}

	public function testOwnerDemotesOtherOwnerToUser(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner1');
		$target = $this->createParticipant($room, Participant::OWNER, 'owner2');
		$this->expectModeratorCount(2);

		$newType = $this->service->updateParticipantTypeByModerator($room, $actor, $target, false, Participant::USER);

		$this->assertSame(Participant::USER, $newType);
	}

	public function testOwnerDemotesThemselvesToModerator(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner');
		$this->expectModeratorCount(2);

		$newType = $this->service->updateParticipantTypeByModerator($room, $actor, $actor, false, Participant::MODERATOR);

		$this->assertSame(Participant::MODERATOR, $newType);
	}

	public function testOwnerCanNotDemoteThemselvesToUser(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner');
		$this->expectModeratorCount(5);

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_SELF);

		$this->service->updateParticipantTypeByModerator($room, $actor, $actor, false, Participant::USER);
	}

	public function testModeratorCanNotPromoteToOwner(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::MODERATOR, 'moderator');
		$target = $this->createParticipant($room, Participant::USER, 'user');

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_MODERATOR);

		$this->service->updateParticipantTypeByModerator($room, $actor, $target, true, Participant::OWNER);
	}

	public function testModeratorCanNotDemoteOwner(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::MODERATOR, 'moderator');
		$target = $this->createParticipant($room, Participant::OWNER, 'owner');

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_MODERATOR);

		$this->service->updateParticipantTypeByModerator($room, $actor, $target, false, Participant::MODERATOR);
	}

	public function testGuestModeratorCanNotPromoteToOwner(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::GUEST_MODERATOR, 'guest', Attendee::ACTOR_GUESTS);
		$target = $this->createParticipant($room, Participant::USER, 'user');

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_MODERATOR);

		$this->service->updateParticipantTypeByModerator($room, $actor, $target, true, Participant::OWNER);
	}

	public static function dataNonUserActorTypesCanNotBecomeOwner(): array {
		return [
			'guest' => [Attendee::ACTOR_GUESTS, Participant::GUEST_MODERATOR],
			'email guest' => [Attendee::ACTOR_EMAILS, Participant::GUEST_MODERATOR],
			'federated user' => [Attendee::ACTOR_FEDERATED_USERS, Participant::MODERATOR],
			'phone' => [Attendee::ACTOR_PHONES, Participant::USER],
		];
	}

	#[DataProvider('dataNonUserActorTypesCanNotBecomeOwner')]
	public function testNonUserActorTypesCanNotBecomeOwner(string $actorType, int $participantType): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner');
		$target = $this->createParticipant($room, $participantType, 'target', $actorType);

		$this->expectException(ParticipantTypeException::class);

		$this->service->updateParticipantTypeByModerator($room, $actor, $target, true, Participant::OWNER);
	}

	public function testGroupsCanNotBeChanged(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner');
		$target = $this->createParticipant($room, Participant::USER, 'group', Attendee::ACTOR_GROUPS);

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_ACTOR_TYPE);

		$this->service->updateParticipantTypeByModerator($room, $actor, $target, true, null);
	}

	public static function dataOwnerChangeIsLimitedToRegularConversations(): array {
		return [
			'group' => [Room::TYPE_GROUP, '', true],
			'public' => [Room::TYPE_PUBLIC, '', true],
			'classified' => [Room::TYPE_GROUP, Room::OBJECT_TYPE_CLASSIFIED, true],
			'classified persist' => [Room::TYPE_GROUP, Room::OBJECT_TYPE_CLASSIFIED_PERSIST, true],
			'instant meeting' => [Room::TYPE_GROUP, Room::OBJECT_TYPE_INSTANT_MEETING, true],
			'extended conversation' => [Room::TYPE_GROUP, Room::OBJECT_TYPE_EXTENDED_CONVERSATION, true],
			'one-to-one' => [Room::TYPE_ONE_TO_ONE, '', false],
			'former one-to-one' => [Room::TYPE_ONE_TO_ONE_FORMER, '', false],
			'note to self' => [Room::TYPE_NOTE_TO_SELF, '', false],
			'changelog' => [Room::TYPE_CHANGELOG, '', false],
			'file' => [Room::TYPE_GROUP, Room::OBJECT_TYPE_FILE, false],
			'video verification' => [Room::TYPE_PUBLIC, Room::OBJECT_TYPE_VIDEO_VERIFICATION, false],
			'calendar event' => [Room::TYPE_GROUP, Room::OBJECT_TYPE_EVENT, false],
			'breakout room' => [Room::TYPE_GROUP, BreakoutRoom::PARENT_OBJECT_TYPE, false],
		];
	}

	#[DataProvider('dataOwnerChangeIsLimitedToRegularConversations')]
	public function testOwnerChangeIsLimitedToRegularConversations(int $roomType, string $objectType, bool $allowed): void {
		$room = $this->createRoom($roomType, $objectType);
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner');
		$target = $this->createParticipant($room, Participant::USER, 'user');
		$this->expectModeratorCount(2);

		if (!$allowed) {
			$this->expectException(ParticipantTypeException::class);
			$this->expectExceptionMessage(ParticipantTypeException::REASON_ROOM_TYPE);
		}

		$newType = $this->service->updateParticipantTypeByModerator($room, $actor, $target, true, Participant::OWNER);
		$this->assertSame(Participant::OWNER, $newType);
	}

	public function testLastModeratorCanNotBeDemoted(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner');
		$target = $this->createParticipant($room, Participant::MODERATOR, 'moderator');
		$this->expectModeratorCount(1);

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_LAST_MODERATOR);

		$this->service->updateParticipantTypeByModerator($room, $actor, $target, false, null);
	}

	public function testDemotingToModeratorIsNotBlockedByTheLastModeratorCheck(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner1');
		$target = $this->createParticipant($room, Participant::OWNER, 'owner2');
		$this->expectModeratorCount(2);

		$newType = $this->service->updateParticipantTypeByModerator($room, $actor, $target, false, Participant::MODERATOR);

		$this->assertSame(Participant::MODERATOR, $newType);
	}

	public static function dataRejectedParticipantTypeParameter(): array {
		return [
			'promote to user' => [true, Participant::USER],
			'promote to guest' => [true, Participant::GUEST],
			'promote to guest moderator' => [true, Participant::GUEST_MODERATOR],
			'demote to owner' => [false, Participant::OWNER],
			'demote to guest' => [false, Participant::GUEST],
			'demote to self-joined user' => [false, Participant::USER_SELF_JOINED],
		];
	}

	#[DataProvider('dataRejectedParticipantTypeParameter')]
	public function testRejectedParticipantTypeParameter(bool $promote, int $requestedType): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner');
		$target = $this->createParticipant($room, Participant::MODERATOR, 'moderator');

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_PARTICIPANT_TYPE);

		$this->service->updateParticipantTypeByModerator($room, $actor, $target, $promote, $requestedType);
	}

	public function testModeratorCanNotChangeThemselves(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::MODERATOR, 'moderator');

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_SELF);

		$this->service->updateParticipantTypeByModerator($room, $actor, $actor, false, null);
	}

	public function testOwnerCanNotBeDemotedWithoutAnExplicitType(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::OWNER, 'owner1');
		$target = $this->createParticipant($room, Participant::OWNER, 'owner2');

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_TYPE);

		$this->service->updateParticipantTypeByModerator($room, $actor, $target, false, null);
	}

	/**
	 * Owners of the parent conversation are moderators of the breakout rooms,
	 * the owner level is never taken over.
	 */
	public function testPromotionToOwnerAddsModeratorToBreakoutRooms(): void {
		$room = $this->createMock(Room::class);
		$room->method('getId')->willReturn(1234);
		$room->method('getToken')->willReturn('token1234');
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('getObjectType')->willReturn('');
		$room->method('getBreakoutRoomMode')->willReturn(BreakoutRoom::MODE_MANUAL);

		$breakoutRoom = $this->createMock(Room::class);

		$manager = $this->createMock(Manager::class);
		$manager->method('getMultipleRoomsByObject')
			->with(BreakoutRoom::PARENT_OBJECT_TYPE, 'token1234')
			->willReturn([$breakoutRoom]);
		$this->overwriteService(Manager::class, $manager);

		$service = $this->getMockBuilder(ParticipantService::class)
			->setConstructorArgs($this->getConstructorArgs())
			->onlyMethods(['addUsers', 'getParticipantByActor', 'getNumberOfModerators'])
			->getMock();
		$service->method('getNumberOfModerators')->willReturn(2);
		$service->method('getParticipantByActor')
			->willThrowException(new ParticipantNotFoundException());
		$service->expects($this->once())
			->method('addUsers')
			->with($breakoutRoom, $this->callback(static function (array $participants): bool {
				return $participants[0]['participantType'] === Participant::OWNER;
			}));

		$actor = $this->createParticipant($room, Participant::OWNER, 'owner');
		$target = $this->createParticipant($room, Participant::USER, 'user');

		$service->updateParticipantTypeByModerator($room, $actor, $target, true, Participant::OWNER);
	}

	public function testGuestIsPromotedToGuestModerator(): void {
		$room = $this->createRoom();
		$actor = $this->createParticipant($room, Participant::MODERATOR, 'moderator');
		$target = $this->createParticipant($room, Participant::GUEST, 'guest', Attendee::ACTOR_GUESTS);
		$this->expectModeratorCount(2);

		$newType = $this->service->updateParticipantTypeByModerator($room, $actor, $target, true, Participant::MODERATOR);

		$this->assertSame(Participant::GUEST_MODERATOR, $newType);
	}
}
