<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Talk\Service\CirclesAdapter;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CirclesAdapterTest extends TestCase {
	protected IAppManager&MockObject $appManager;
	protected ContainerInterface&MockObject $container;
	protected CirclesManager&MockObject $circlesManager;
	protected CirclesAdapter $adapter;

	public function setUp(): void {
		parent::setUp();
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->method('isEnabledForAnyone')->with('circles')->willReturn(true);
		$this->circlesManager = $this->createMock(CirclesManager::class);
		$this->container = $this->createMock(ContainerInterface::class);
		$this->container->method('get')->with(CirclesManager::class)->willReturn($this->circlesManager);

		$this->adapter = new CirclesAdapter($this->appManager, $this->container, $this->createMock(LoggerInterface::class));
	}

	private function makeMember(int $userType, string $userId, int $level, bool $local = true, int $joined = 1): Member&MockObject {
		$member = $this->createMock(Member::class);
		$member->method('getUserType')->willReturn($userType);
		$member->method('getUserId')->willReturn($userId);
		$member->method('getLevel')->willReturn($level);
		$member->method('isLocal')->willReturn($local);
		$member->method('getInstance')->willReturn($local ? '' : 'remote.example');
		$member->method('getDisplayName')->willReturn(ucfirst($userId));
		$member->method('getJoined')->willReturn($joined);
		return $member;
	}

	/**
	 * @param list<Member> $members
	 */
	private function mockTeam(array $members): void {
		$circle = $this->createMock(Circle::class);
		$circle->method('getSingleId')->willReturn('team1');
		$circle->method('getDisplayName')->willReturn('Team');
		$circle->method('getDescription')->willReturn('');
		$circle->method('getConfig')->willReturn(0);
		$circle->method('hasOwner')->willReturn(true);
		$circle->method('isLocal')->willReturn(true);
		$circle->method('getMembers')->willReturn($members);
		$this->circlesManager->method('getCircle')->with('team1')->willReturn($circle);
	}

	private function mockMixedTeam(): void {
		$this->mockTeam([
			$this->makeMember(Member::TYPE_USER, 'alice', Member::LEVEL_OWNER, true, 1),
			$this->makeMember(Member::TYPE_USER, 'bob', Member::LEVEL_MEMBER, true, 2),
			// Invited, not a member yet
			$this->makeMember(Member::TYPE_USER, 'carol', Member::LEVEL_NONE),
			// Same user id on another server: a different user
			$this->makeMember(Member::TYPE_USER, 'alice', Member::LEVEL_MODERATOR, false),
			$this->makeMember(Member::TYPE_GROUP, 'group1', Member::LEVEL_MEMBER),
			$this->makeMember(Member::TYPE_MAIL, 'Guest@Example.TLD', Member::LEVEL_MEMBER),
		]);
	}

	public function testDirectMembersAreTheLocalUsersWithTheirLevel(): void {
		$this->mockMixedTeam();

		$this->assertSame([
			'alice' => ['level' => Member::LEVEL_OWNER, 'joined' => 1],
			'bob' => ['level' => Member::LEVEL_MEMBER, 'joined' => 2],
		], $this->adapter->getDirectMembers('team1'));
	}

	public function testSnapshotHasTheSameShapeAsTheSingleLookups(): void {
		$this->mockMixedTeam();

		$snapshot = $this->adapter->getTeamSnapshot('team1');

		$this->assertSame(['id' => 'team1', 'name' => 'Team', 'description' => '', 'config' => 0, 'local' => true], $snapshot['team']);
		$this->assertSame($this->adapter->getDirectMembers('team1'), $snapshot['direct']);
		$this->assertSame([
			['userId' => 'alice', 'instance' => 'remote.example', 'displayName' => 'Alice', 'level' => Member::LEVEL_MODERATOR],
		], $snapshot['remote']);
		$this->assertSame(['guest@example.tld' => 'Guest@Example.TLD'], $snapshot['emails']);
	}

	public static function dataEmailOfMember(): array {
		return [
			'valid address, lower case' => [' Guest@Example.TLD ', 'guest@example.tld'],
			'invalid address' => ['not an address', null],
			'empty' => ['', null],
		];
	}

	#[DataProvider('dataEmailOfMember')]
	public function testEmailOfEmailMember(string $userId, ?string $expected): void {
		$this->assertSame($expected, $this->adapter->getEmailOfMember($this->makeMember(Member::TYPE_MAIL, $userId, Member::LEVEL_MEMBER)));
	}

	public function testOtherMembersHaveNoEmail(): void {
		$this->assertNull($this->adapter->getEmailOfMember($this->makeMember(Member::TYPE_USER, 'alice', Member::LEVEL_MEMBER)));
	}

	public function testReadsAreDoneInASuperSessionThatIsStoppedAgain(): void {
		$this->mockTeam([]);
		$this->circlesManager->expects($this->once())->method('startSuperSession');
		$this->circlesManager->expects($this->once())->method('stopSession');

		$this->adapter->getTeam('team1');
	}

	public function testNothingIsDoneWhenCirclesIsDisabled(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willReturn(false);
		$this->container->expects($this->never())->method('get');
		$adapter = new CirclesAdapter($appManager, $this->container, $this->createMock(LoggerInterface::class));

		$this->assertFalse($adapter->isAvailable());
		$this->expectException(\Exception::class);
		$adapter->getTeam('team1');
	}

	public function testMissingTeamIsRecognized(): void {
		$this->circlesManager->method('getCircle')->willThrowException(new CircleNotFoundException('Circle not found'));

		try {
			$this->adapter->getTeam('team1');
			$this->fail('Exception expected');
		} catch (\Exception $e) {
			$this->assertTrue(CirclesAdapter::isTeamNotFound($e));
		}
	}

	public function testFailingCirclesIsNoMissingTeam(): void {
		$this->circlesManager->method('getCircle')->willThrowException(new \RuntimeException('Deadlock'));

		try {
			$this->adapter->getTeam('team1');
			$this->fail('Exception expected');
		} catch (\Exception $e) {
			$this->assertFalse(CirclesAdapter::isTeamNotFound($e));
		}
	}
}
