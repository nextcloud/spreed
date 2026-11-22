<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Talk\Listener\CircleMembershipListener;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\BanService;
use OCA\Talk\Service\ParticipantService;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CircleMembershipListenerTest extends TestCase {
	protected IUserManager&MockObject $userManager;
	protected Manager&MockObject $manager;
	protected CircleMembershipListener $listener;

	public function setUp(): void {
		parent::setUp();
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userManager->method('get')->willReturnCallback(function (string $userId): ?IUser {
			if ($userId === 'deleted') {
				return null;
			}
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($userId);
			return $user;
		});

		$this->manager = $this->createMock(Manager::class);

		$this->listener = new CircleMembershipListener(
			$this->manager,
			$this->createMock(IAppManager::class),
			$this->createMock(IGroupManager::class),
			$this->createMock(ParticipantService::class),
			$this->createMock(BanService::class),
			$this->createMock(LoggerInterface::class),
			$this->userManager,
			$this->createMock(ISession::class),
		);
	}

	private function makeMember(int $userType, string $userId, bool $local = true): Member&MockObject {
		$member = $this->createMock(Member::class);
		$member->method('getUserType')->willReturn($userType);
		$member->method('getUserId')->willReturn($userId);
		$member->method('isLocal')->willReturn($local);
		return $member;
	}

	private function removeMember(Member $removedMember): void {
		$circle = $this->createMock(Circle::class);
		$circle->method('getSingleId')->willReturn('team1');
		$event = $this->createMock(RemovingCircleMemberEvent::class);
		$event->method('getCircle')->willReturn($circle);
		$event->method('getMember')->willReturn($removedMember);

		$this->listener->handle($event);
	}

	public function testRemovedLocalUserIsRemovedFromTheRoomsOfTheTeam(): void {
		$this->manager->expects($this->once())
			->method('getRoomsForActor')
			->with(Attendee::ACTOR_CIRCLES, 'team1')
			->willReturn([]);

		$this->removeMember($this->makeMember(Member::TYPE_USER, 'alice'));
	}

	public function testRemovedRemoteUserWithTheSameUserIdIsNotALocalUser(): void {
		$this->userManager->expects($this->never())->method('get');
		$this->manager->expects($this->never())->method('getRoomsForActor');

		$this->removeMember($this->makeMember(Member::TYPE_USER, 'alice', false));
	}

	public function testRemovedGroupsAndTeamsAreLeftAsBefore(): void {
		// Only users are handled here, team conversations are synced by TeamRoomListener
		$this->userManager->expects($this->never())->method('get');
		$this->manager->expects($this->never())->method('getRoomsForActor');

		$this->removeMember($this->makeMember(Member::TYPE_GROUP, 'group1'));
		$this->removeMember($this->makeMember(Member::TYPE_CIRCLE, 'team2'));
	}
}
