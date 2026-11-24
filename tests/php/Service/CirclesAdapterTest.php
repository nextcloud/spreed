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
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Member;
use OCA\Circles\Service\CircleService;
use OCA\Circles\Service\FederatedUserService;
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
	protected FederatedUserService&MockObject $federatedUserService;
	protected CircleService&MockObject $circleService;
	/** Session state of Circles, as FederatedUserService keeps it */
	protected ?FederatedUser $currentUser = null;
	protected bool $bypass = false;
	protected CirclesAdapter $adapter;

	public function setUp(): void {
		parent::setUp();
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->method('isEnabledForAnyone')->with('circles')->willReturn(true);
		$this->circlesManager = $this->createMock(CirclesManager::class);
		$this->container = $this->createMock(ContainerInterface::class);
		$this->federatedUserService = $this->createMock(FederatedUserService::class);
		$this->circleService = $this->createMock(CircleService::class);
		$this->container->method('get')->willReturnCallback(fn (string $class): object => match ($class) {
			CirclesManager::class => $this->circlesManager,
			FederatedUserService::class => $this->federatedUserService,
			CircleService::class => $this->circleService,
		});
		$this->mockSessionState();

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

	/**
	 * The session methods of CirclesManager and FederatedUserService change the same state
	 */
	private function mockSessionState(): void {
		$this->circlesManager->method('startSuperSession')->willReturnCallback(function (): void {
			$this->currentUser = null;
			$this->bypass = true;
		});
		$this->circlesManager->method('startSession')->willReturnCallback(function (?FederatedUser $federatedUser = null): void {
			$this->currentUser = $federatedUser;
		});
		$this->circlesManager->method('stopSession')->willReturnCallback(function (): void {
			$this->currentUser = null;
			$this->bypass = false;
		});
		$this->federatedUserService->method('getCurrentUser')->willReturnCallback(fn (): ?FederatedUser => $this->currentUser);
		$this->federatedUserService->method('setCurrentUser')->willReturnCallback(function (FederatedUser $federatedUser): void {
			$this->currentUser = $federatedUser;
		});
		$this->federatedUserService->method('canBypassCurrentUserCondition')->willReturnCallback(fn (): bool => $this->bypass);
		$this->federatedUserService->method('bypassCurrentUserCondition')->willReturnCallback(function (bool $bypass): void {
			$this->bypass = $bypass;
		});
	}

	public function testSuperSessionRestoresTheSessionOfTheCaller(): void {
		// E.g. Talk reads the team in a listener while Circles runs a change of a user
		$caller = $this->createMock(FederatedUser::class);
		$this->currentUser = $caller;

		$during = null;
		$this->circlesManager->method('getCircle')->willReturnCallback(function () use (&$during): Circle {
			$during = [$this->currentUser, $this->bypass];
			$circle = $this->createMock(Circle::class);
			$circle->method('getMembers')->willReturn([]);
			return $circle;
		});

		$this->adapter->getDirectMembers('team1');

		$this->assertSame([null, true], $during);
		$this->assertSame($caller, $this->currentUser);
		$this->assertFalse($this->bypass);
	}

	public function testUserSessionInsideASuperSessionIsNoSuperUser(): void {
		// E.g. Talk writes as a user while Circles or another app runs a super session
		$this->bypass = true;
		$alice = $this->createMock(FederatedUser::class);
		$this->circlesManager->method('getFederatedUser')->with('alice', Member::TYPE_USER)->willReturn($alice);

		$during = self::invokePrivate($this->adapter, 'asUser', ['alice', fn (): array => [$this->currentUser, $this->bypass]]);

		$this->assertSame([$alice, false], $during);
		// The super session of the caller is back
		$this->assertNull($this->currentUser);
		$this->assertTrue($this->bypass);
	}

	public function testSessionIsRestoredWhenCirclesFails(): void {
		$caller = $this->createMock(FederatedUser::class);
		$this->currentUser = $caller;
		$this->circlesManager->method('getCircle')->willThrowException(new \RuntimeException('Deadlock'));

		try {
			$this->adapter->getTeam('team1');
			$this->fail('Exception expected');
		} catch (\Exception) {
		}

		$this->assertSame($caller, $this->currentUser);
		$this->assertFalse($this->bypass);
	}

	public function testRemoteUserIsAddedLikeInTheTeamsApp(): void {
		// Looked up before acting as alice: the lookup changes the instance of
		// this server, alice and the team must be created after it
		$calls = [];
		$alice = $this->createMock(FederatedUser::class);
		$bob = $this->createMock(FederatedUser::class);
		$this->circlesManager->method('getFederatedUser')->with('alice', Member::TYPE_USER)
			->willReturnCallback(function () use (&$calls, $alice): FederatedUser {
				$calls[] = 'actor';
				return $alice;
			});
		$this->federatedUserService->expects($this->once())
			->method('generateFederatedUser')
			->with('bob@remote.example', Member::TYPE_USER)
			->willReturnCallback(function () use (&$calls, $bob): FederatedUser {
				$calls[] = 'lookup';
				return $bob;
			});
		$this->circlesManager->expects($this->once())
			->method('addMember')
			->with('team1', $bob)
			->willReturn($this->makeMember(Member::TYPE_USER, 'bob', Member::LEVEL_MEMBER, false));

		$this->assertTrue($this->adapter->addRemoteUser('team1', 'bob', 'remote.example', 'alice'));
		$this->assertSame(['lookup', 'actor'], $calls);
	}

	public function testRenamingTheTeamChangesItsName(): void {
		// Circles derives the display name from the name: changing only the
		// display name would be replaced by the old name again
		$this->circlesManager->method('getFederatedUser')->willReturn($this->createMock(FederatedUser::class));
		$this->circleService->expects($this->once())->method('updateName')->with('team1', 'New name');
		$this->circleService->expects($this->never())->method('updateDisplayName');

		$this->adapter->updateTeam('team1', ['name' => 'New name'], 'alice');
	}
}
