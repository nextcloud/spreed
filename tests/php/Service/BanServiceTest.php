<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Circles\Model\Member;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Ban;
use OCA\Talk\Model\BanMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\BanService;
use OCA\Talk\Service\CirclesAdapter;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Security\Ip\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BanServiceTest extends TestCase {
	protected CirclesAdapter&MockObject $circlesAdapter;
	protected BanMapper&MockObject $banMapper;
	protected ParticipantService&MockObject $participantService;
	protected IUserManager&MockObject $userManager;
	protected IEventDispatcher&MockObject $dispatcher;
	protected BanService $service;

	public function setUp(): void {
		parent::setUp();

		$this->banMapper = $this->createMock(BanMapper::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->circlesAdapter = $this->createMock(CirclesAdapter::class);
		$this->service = new BanService(
			$this->banMapper,
			$this->participantService,
			$this->userManager,
			$this->createMock(TalkSession::class),
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IFactory::class),
			$this->dispatcher,
			$this->circlesAdapter,
		);
	}

	public function testCreateBanLogsCriticalAction(): void {
		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('getToken')->willReturn('abcdef');
		$this->participantService->method('getParticipantByActor')
			->willThrowException(new ParticipantNotFoundException());
		$this->banMapper->method('insert')
			->willReturnCallback(static function (Ban $ban): Ban {
				$ban->setId(12);
				return $ban;
			});

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.ban.created'
				&& $event->getLogMessage() === '{bannedActorType} "{bannedActorId}" was banned from conversation "{token}" (ban #{banId})'
				&& $event->getParameters() === ['bannedActorType' => 'users', 'bannedActorId' => 'bob', 'token' => 'abcdef', 'banId' => 12]));

		$this->service->createBan($room, Attendee::ACTOR_USERS, 'alice', 'Alice', Attendee::ACTOR_USERS, 'bob', new \DateTime(), 'Internal note');
	}

	private function makeTeamRoom(): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('getToken')->willReturn('teamroom');
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_TEAM);
		$room->method('isTeamManaged')->willReturn(true);
		$room->method('getObjectId')->willReturn('team1');
		$this->participantService->method('getParticipantByActor')
			->willThrowException(new ParticipantNotFoundException());
		$this->circlesAdapter->method('getDirectMember')
			->willReturnCallback(static fn (string $teamId, string $userId): ?array => $userId === 'bob' ? ['level' => 4, 'joined' => 1] : null);
		return $room;
	}

	public function testBanInTeamRoomRemovesFromTheTeamAsTheModerator(): void {
		$room = $this->makeTeamRoom();
		$this->banMapper->method('insert')->willReturnArgument(0);

		$this->circlesAdapter->expects($this->once())
			->method('removeDirectUser')
			->with('team1', 'bob', 'alice');

		$this->service->createBan($room, Attendee::ACTOR_USERS, 'alice', 'Alice', Attendee::ACTOR_USERS, 'bob', new \DateTime(), 'Note');
	}

	public function testBanInTeamRoomIsRefusedWhenTheTeamRefusesTheRemoval(): void {
		$room = $this->makeTeamRoom();
		// e.g. bob has the same team level as alice
		$this->circlesAdapter->method('removeDirectUser')->willThrowException(new \InvalidArgumentException('level'));
		$this->banMapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('moderator');
		$this->service->createBan($room, Attendee::ACTOR_USERS, 'alice', 'Alice', Attendee::ACTOR_USERS, 'bob', new \DateTime(), 'Note');
	}

	public function testBanOfEmailGuestInTeamRoomRemovesTheEmailMemberFromTheTeam(): void {
		$room = $this->makeTeamRoom();
		$this->banMapper->method('insert')->willReturnArgument(0);
		$this->circlesAdapter->method('getEmailOfMember')
			->willReturnCallback(static fn (Member $member): ?string => $member->getUserId() === 'guest@example.tld' ? 'guest@example.tld' : null);

		$other = $this->createMock(Member::class);
		$other->method('getUserId')->willReturn('other@example.tld');
		$guest = $this->createMock(Member::class);
		$guest->method('getUserId')->willReturn('guest@example.tld');

		$this->circlesAdapter->expects($this->once())
			->method('removeMatchingMember')
			->willReturnCallback(function (string $teamId, callable $match, string $actorUserId) use ($other, $guest): bool {
				$this->assertSame('team1', $teamId);
				$this->assertSame('alice', $actorUserId);
				$this->assertFalse($match($other));
				$this->assertTrue($match($guest));
				return true;
			});

		$this->service->createBan($room, Attendee::ACTOR_USERS, 'alice', 'Alice', Attendee::ACTOR_EMAILS, hash('sha256', 'guest@example.tld'), new \DateTime(), 'Note');
	}

	public function testBanOfEmailGuestInTeamRoomIsRefusedWhenTheTeamRefusesTheRemoval(): void {
		$room = $this->makeTeamRoom();
		$this->circlesAdapter->method('removeMatchingMember')->willThrowException(new \InvalidArgumentException('level'));
		$this->banMapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('moderator');
		$this->service->createBan($room, Attendee::ACTOR_USERS, 'alice', 'Alice', Attendee::ACTOR_EMAILS, hash('sha256', 'guest@example.tld'), new \DateTime(), 'Note');
	}

	public function testDeleteBanLogsCriticalAction(): void {
		$ban = new Ban();
		$ban->setBannedActorType(Attendee::ACTOR_USERS);
		$ban->setBannedActorId('bob');
		$this->banMapper->method('findByBanIdAndRoom')
			->with(12, 34)
			->willReturn($ban);

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.ban.deleted'
				&& $event->getParameters() === ['banId' => 12, 'bannedActorType' => 'users', 'bannedActorId' => 'bob', 'roomId' => 34]));

		$this->service->findAndDeleteBanByIdForRoom(12, 34);
	}

	public function testDeleteUnknownBanIsNotLogged(): void {
		$this->banMapper->method('findByBanIdAndRoom')
			->willThrowException(new DoesNotExistException('not found'));

		$this->dispatcher->expects($this->never())
			->method('dispatchTyped');

		$this->service->findAndDeleteBanByIdForRoom(12, 34);
	}

	public function testBannedEmailGuestIsFoundByTheAddress(): void {
		// Email guests are banned with their actor id, the hash of the address
		$room = $this->createMock(Room::class);
		$room->method('getId')->willReturn(42);
		$this->banMapper->method('findForBannedActorAndRoom')
			->willReturnCallback(static function (string $actorType, string $actorId, int $roomId): Ban {
				if ($actorType === Attendee::ACTOR_EMAILS && $actorId === hash('sha256', 'guest@example.tld') && $roomId === 42) {
					return new Ban();
				}
				throw new DoesNotExistException('');
			});
		$this->userManager->method('getByEmail')->willReturn([]);

		$this->assertTrue($this->service->isEmailBanned($room, ' Guest@Example.TLD '));
		$this->assertFalse($this->service->isEmailBanned($room, 'other@example.tld'));
	}

	public static function dataBannedEmailGuestDisplayName(): array {
		return [
			'team conversation shows the address' => [Room::OBJECT_TYPE_TEAM, 'guest@example.tld'],
			'other conversations keep the name' => ['', 'Guest'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('dataBannedEmailGuestDisplayName')]
	public function testBannedEmailGuestDisplayName(string $objectType, string $expected): void {
		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('getObjectType')->willReturn($objectType);
		$room->method('isTeamManaged')->willReturn($objectType === Room::OBJECT_TYPE_TEAM);
		$actorId = hash('sha256', 'guest@example.tld');
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn(Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_EMAILS,
			'actor_id' => $actorId,
			'display_name' => 'Guest',
			'invited_cloud_id' => 'guest@example.tld',
		]));
		$this->participantService->method('getParticipantByActor')->willReturn($participant);
		$this->banMapper->method('insert')->willReturnArgument(0);

		$ban = $this->service->createBan($room, Attendee::ACTOR_USERS, 'alice', 'Alice', Attendee::ACTOR_EMAILS, $actorId, new \DateTime(), 'Note');

		$this->assertSame($expected, $ban->getBannedDisplayname());
		// Still banned by the actor id, as email guests are checked when joining
		$this->assertSame($actorId, $ban->getBannedActorId());
	}
}
