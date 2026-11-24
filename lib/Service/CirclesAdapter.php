<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Db\MemberRequest;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Member;
use OCA\Circles\Service\CircleService;
use OCA\Circles\Service\ContactService;
use OCA\Circles\Service\FederatedUserService;
use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @psalm-type TalkTeamInfo = array{id: string, name: string, description: string, config: int, local: bool}
 * @psalm-type TalkTeamDirectMember = array{level: int, joined: int}
 * @psalm-type TalkTeamDirectMembers = array<string, TalkTeamDirectMember>
 * @psalm-type TalkTeamRemoteMember = array{userId: string, instance: string, displayName: string, level: int, invitedBy?: string}
 */
class CirclesAdapter {

	protected ?bool $available = null;

	public function __construct(
		protected readonly IAppManager $appManager,
		protected readonly ContainerInterface $container,
		protected readonly LoggerInterface $logger,
	) {
	}

	public function isAvailable(): bool {
		$this->available ??= $this->appManager->isEnabledForAnyone('circles')
			&& class_exists(CirclesManager::class);
		return $this->available;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function getTeam(string $teamId): array {
		return $this->asSuperUser(static fn (CirclesManager $manager): array => self::getTeamInfo($manager->getCircle($teamId)));
	}

	/**
	 * @return array
	 */
	protected static function getTeamInfo(Circle $circle): array {
		return [
			'id' => $circle->getSingleId(),
			'name' => $circle->getDisplayName(),
			'description' => $circle->getDescription(),
			'config' => $circle->getConfig(),
			'local' => $circle->hasOwner() && $circle->isLocal(),
		];
	}

	/**
	 * Everything a resync needs, with one lookup of the team and its members,
	 * also one consistent state. getDirectMembers(), getRemoteMembers() and
	 * getEmailMembers() return its parts.
	 *
	 * @return array{team: array, direct: TalkTeamDirectMembers, remote: list<TalkTeamRemoteMember>, emails: array<string, string>}
	 * @throws \Exception
	 */
	public function getTeamSnapshot(string $teamId): array {
		return $this->asSuperUser(function (CirclesManager $manager) use ($teamId): array {
			$circle = $manager->getCircle($teamId);
			/** @var array<string, TalkTeamDirectMember> $direct */
			$direct = [];
			/** @var list<TalkTeamRemoteMember> $remote */
			$remote = [];
			/** @var array<string, string> $emails */
			$emails = [];
			foreach ($circle->getMembers() as $member) {
				$directEntry = self::getDirectMemberEntry($member);
				if (!empty($directEntry)) {
					$direct[(string)$member->getUserId()] = $directEntry;
					continue;
				}
				$remoteEntry = self::getRemoteMemberEntry($member);
				if (!empty($remoteEntry)) {
					$remote[] = $remoteEntry;
					continue;
				}
				$email = $member->getLevel() >= Member::LEVEL_MEMBER ? $this->getEmailOfMember($member) : null;
				if (!empty($email)) {
					$displayName = (string)$member->getDisplayName();
					$emails[$email] = $displayName !== '' ? $displayName : $email;
				}
			}
			return [
				'team' => self::getTeamInfo($circle),
				'direct' => $direct,
				'remote' => $remote,
				'emails' => $emails,
			];
		});
	}

	/**
	 * @return TalkTeamDirectMember|null
	 */
	protected static function getDirectMemberEntry(Member $member): ?array {
		if ($member->getUserType() !== Member::TYPE_USER || empty($member->getUserId())
			|| !$member->isLocal() || $member->getLevel() < Member::LEVEL_MEMBER) {
			return null;
		}
		return ['level' => $member->getLevel(), 'joined' => $member->getJoined()];
	}

	/**
	 * @return TalkTeamRemoteMember|null
	 */
	protected static function getRemoteMemberEntry(Member $member): ?array {
		if ($member->getUserType() !== Member::TYPE_USER || $member->isLocal()
			|| empty($member->getUserId()) || $member->getLevel() < Member::LEVEL_MEMBER) {
			return null;
		}
		// The user of this server who added the member: sends the invitation
		$invitedBy = '';
		if ($member->hasInvitedBy()) {
			$by = $member->getInvitedBy();
			if ($by->getUserType() === Member::TYPE_USER && $by->isLocal()) {
				$invitedBy = (string)$by->getUserId();
			}
		}
		return [
			'userId' => $member->getUserId(),
			'instance' => $member->getInstance(),
			'displayName' => $member->getDisplayName(),
			'level' => $member->getLevel(),
			'invitedBy' => $invitedBy,
		];
	}

	/**
	 * Direct local user members of the team with their level (Member::LEVEL_*)
	 * and when they joined. Users that are only members via groups or nested
	 * teams are not included: their level is the one of the group/team entry.
	 **
	 * @return TalkTeamDirectMembers User id => direct membership
	 * @throws \Exception
	 */
	public function getDirectMembers(string $teamId): array {
		return $this->getTeamSnapshot($teamId)['direct'];
	}

	/**
	 * The direct membership of one local user, without loading all members of
	 * the team (requests that change only this user)
	 *
	 * CirclesManager has no public method for this, so the internal
	 * MemberRequest is used (memberships of the user, few rows).
	 *
	 * @return array|null Null when the user is no direct member
	 * @throws \Exception
	 */
	public function getDirectMember(string $teamId, string $userId): ?array {
		return $this->asSuperUser(function (CirclesManager $manager) use ($teamId, $userId): ?array {
			$member = $this->findDirectMember($teamId, $userId);
			if (!is_null($member)) {
				return $member->getLevel() >= Member::LEVEL_MEMBER
					? ['level' => $member->getLevel(), 'joined' => $member->getJoined()]
					: null;
			}
			// Not a member: make sure the team still exists (throws otherwise)
			$manager->getCircle($teamId);
			return null;
		});
	}

	/**
	 * The direct membership of a local user, of any level (also invited or
	 * requesting), without loading all members of the team. Remote members
	 * can have the same user id, they are not matched.
	 *
	 * CirclesManager has no public method for this, so the internal
	 * MemberRequest is used (memberships of the user, few rows).
	 */
	protected function findDirectMember(string $teamId, string $userId): ?Member {
		foreach ($this->getCirclesService(MemberRequest::class)->getMembersByUserId($userId) as $member) {
			if ($member->getCircleId() === $teamId
				&& $member->getUserType() === Member::TYPE_USER
				&& $member->isLocal()) {
				return $member;
			}
		}
		return null;
	}

	/**
	 * Email members of the team (and contact members, by the first address of
	 * the contact), the external members of the Teams app
	 *
	 * @return array<string, string> Email address (lower case) => display name
	 * @throws \Exception
	 */
	public function getEmailMembers(string $teamId): array {
		return $this->getTeamSnapshot($teamId)['emails'];
	}

	/**
	 * Lower case email address of an email or contact member, null for other members
	 */
	public function getEmailOfMember(Member $member): ?string {
		if ($member->getUserType() === Member::TYPE_MAIL) {
			$email = $member->getUserId();
		} elseif ($member->getUserType() === Member::TYPE_CONTACT) {
			// Only contacts of this server: the contact of a member added on another
			// server is in an address book there (the path names a user of that server)
			$contactService = $this->getCirclesService(ContactService::class);
			try {
				$email = $contactService->getMailAddressesFromMember($member)[0] ?? '';
			} catch (\Throwable) {
				return null;
			}
		} else {
			return null;
		}

		$email = mb_strtolower(mb_trim((string)$email));
		return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
	}

	/**
	 * Direct members of the team on other instances (Circles federation)
	 *
	 * @return list<TalkTeamRemoteMember>
	 * @throws \Exception
	 */
	public function getRemoteMembers(string $teamId): array {
		return $this->getTeamSnapshot($teamId)['remote'];
	}

	/**
	 * Add a user as direct member of the team, acting as $actorUserId: Circles
	 * checks the permissions (moderator) and the team settings (with
	 * CFG_INVITE the user is only invited and needs to accept).
	 *
	 * @return bool True when the user is a member now, false when only invited
	 * @throws \Exception
	 */
	public function addUser(string $teamId, #[\SensitiveParameter] string $userId, string $actorUserId): bool {
		return $this->asUser($actorUserId, static function (CirclesManager $manager) use ($teamId, $userId): bool {
			$member = $manager->addMember($teamId, $manager->getFederatedUser($userId, Member::TYPE_USER));
			return $member->getLevel() >= Member::LEVEL_MEMBER;
		});
	}

	/**
	 * Add a user of another server as direct member of the team, acting as
	 * $actorUserId: Circles checks the permissions and the team settings (the
	 * team needs to allow members of other servers, with CFG_INVITE the user is
	 * only invited).
	 *
	 * Same as the Teams app: when looking up the user on their server fails
	 * (e.g. the server is not a known instance of Circles), Circles creates the
	 * member from the user id and the server.
	 *
	 * The user is looked up before acting as $actorUserId: the lookup switches
	 * Circles to the interface for other servers, which changes the instance of
	 * this server from the loopback address to the public one. Circles requires
	 * the acting user (the patron) to have the same instance as the team, so
	 * both have to be created after the switch, otherwise Circles refuses the
	 * member with "Member is invited by an entity that does not belongs to the
	 * instance at the origin of the request".
	 *
	 * @param string $server Server of the user, without protocol
	 * @return bool True when the user is a member now, false when only invited
	 * @throws \Exception
	 */
	public function addRemoteUser(string $teamId, #[\SensitiveParameter] string $userId, string $server, string $actorUserId): bool {
		$federatedUser = $this->run(fn (): FederatedUser => $this->getCirclesService(FederatedUserService::class)->generateFederatedUser($userId . '@' . $server, Member::TYPE_USER));
		return $this->asUser($actorUserId, static function (CirclesManager $manager) use ($teamId, $federatedUser): bool {
			$member = $manager->addMember($teamId, $federatedUser);
			return $member->getLevel() >= Member::LEVEL_MEMBER;
		});
	}

	/**
	 * Add an email guest member to the team, acting as $actorUserId: Circles checks
	 * the permissions. Email members are members right away, also in invite-only
	 * teams (CFG_INVITE only invites users). The Teams app mails the member links
	 * to what is shared with the team.
	 *
	 * @return bool True when the address is a member now
	 * @throws \Exception
	 */
	public function addEmailMember(string $teamId, #[\SensitiveParameter] string $email, string $actorUserId): bool {
		return $this->asUser($actorUserId, static function (CirclesManager $manager) use ($teamId, $email): bool {
			$member = $manager->addMember($teamId, $manager->getFederatedUser($email, Member::TYPE_MAIL));
			return $member->getLevel() >= Member::LEVEL_MEMBER;
		});
	}

	/**
	 * Set the level of a local direct member, acting as $actorUserId: Circles
	 * checks the permissions (only the owner can make someone else owner, the
	 * former owner becomes an admin then).
	 *
	 * @param int $level One of Member::LEVEL_MEMBER, _MODERATOR, _ADMIN or _OWNER
	 * @throws \Exception When the user is no direct member, the team refused it or Circles is unavailable
	 */
	public function setMemberLevel(string $teamId, string $userId, int $level, string $actorUserId): void {
		$this->asUser($actorUserId, function (CirclesManager $manager) use ($teamId, $userId, $level): void {
			$member = $this->findDirectMember($teamId, $userId);
			if (is_null($member)) {
				throw new \Exception('Not a direct member of the team');
			}
			if ($member->getLevel() !== $level) {
				$manager->levelMember($member->getId(), $level);
			}
		});
	}

	/**
	 * The user leaves the team themselves, Circles checks it like "Leave team"
	 * in the Teams app. When the owner leaves, Circles decides the new owner.
	 *
	 * @throws \Exception
	 */
	public function leaveTeam(string $teamId, string $userId): void {
		// TODO: check circleLeave() for outcome message to return status here
		$this->asUser($userId, function () use ($teamId): void {
			$this->getCirclesService(CircleService::class)->circleLeave($teamId);
		});
	}

	/**
	 * The user joins the team themselves (CFG_OPEN, with CFG_REQUEST only a request)
	 *
	 * @return bool True when the user is a member now, false when the request needs a moderator
	 * @throws \Exception
	 */
	public function joinTeam(string $teamId, string $userId): bool {
		return $this->asUser($userId, function () use ($teamId, $userId): bool {
			$circleService = $this->getCirclesService(CircleService::class);
			$circleService->circleJoin($teamId);

			$member = $this->findDirectMember($teamId, $userId);
			return !is_null($member) && $member->getLevel() >= Member::LEVEL_MEMBER;
		});
	}

	/**
	 * Settings of the team, acting as $actorUserId: Circles checks the
	 * permissions (admins only).
	 *
	 * @param array{name?: string, description?: string, config?: int} $settings
	 * @throws \Exception
	 */
	public function updateTeam(string $teamId, array $settings, string $actorUserId): void {
		$this->asUser($actorUserId, $this->updateTeamCallback($teamId, $settings));
	}

	/**
	 * @param array{name?: string, description?: string, config?: int} $settings
	 * @return callable(CirclesManager): void
	 */
	protected function updateTeamCallback(string $teamId, array $settings): callable {
		return function () use ($teamId, $settings): void {
			$circleService = $this->getCirclesService(CircleService::class);
			if (isset($settings['name'])) {
				// Same as the Teams app: Circles derives the display name from the
				// name, a display name set alone is replaced by the old name again
				$circleService->updateName($teamId, $settings['name']);
			}
			if (isset($settings['description'])) {
				$circleService->updateDescription($teamId, $settings['description']);
			}
			if (isset($settings['config'])) {
				$circleService->updateConfig($teamId, $settings['config']);
			}
		};
	}

	/**
	 * Remove the user's direct membership, acting as $actorUserId: Circles
	 * checks the permissions.
	 *
	 * @throws \Exception
	 */
	public function removeDirectUser(string $teamId, string $userId, string $actorUserId): void {
		$this->asUser($actorUserId, function (CirclesManager $manager) use ($teamId, $userId): void {
			$member = $this->findDirectMember($teamId, $userId);
			if (is_null($member)) {
				throw new \Exception('Not a direct member of the team');
			}
			$manager->removeMember($member->getId());
		});
	}

	/**
	 * Remove the first direct member that matches (a member on another server,
	 * an email or contact member), acting as $actorUserId: Circles checks the
	 * permissions. Loads all members of the team, for removals in requests only.
	 *
	 * @param callable(Member): bool $match
	 * @return bool False when no member matched
	 * @throws \Exception
	 */
	public function removeMatchingMember(string $teamId, callable $match, string $actorUserId): bool {
		return $this->asUser($actorUserId, static function (CirclesManager $manager) use ($teamId, $match): bool {
			foreach ($manager->getCircle($teamId)->getMembers() as $member) {
				if ($match($member)) {
					$manager->removeMember($member->getId());
					return true;
				}
			}
			return false;
		});
	}

	/**
	 * Set the level of the first direct member that matches (a member on another
	 * server), acting as $actorUserId: Circles checks the permissions.
	 *
	 * @param callable(Member): bool $match
	 * @param int $level One of Member::LEVEL_MEMBER, _MODERATOR or _ADMIN
	 * @return bool False when no member matched
	 * @throws \Exception
	 */
	public function setMatchingMemberLevel(string $teamId, callable $match, int $level, string $actorUserId): bool {
		return $this->asUser($actorUserId, static function (CirclesManager $manager) use ($teamId, $match, $level): bool {
			foreach ($manager->getCircle($teamId)->getMembers() as $member) {
				if ($match($member)) {
					if ($member->getLevel() !== $level) {
						$manager->levelMember($member->getId(), $level);
					}
					return true;
				}
			}
			return false;
		});
	}

	/**
	 * @template T
	 * @param callable(CirclesManager): T $callback
	 * @return T
	 * @throws \Exception
	 */
	protected function asSuperUser(callable $callback): mixed {
		return $this->run(fn (CirclesManager $manager): mixed => $this->inSession(
			$manager,
			static fn () => $manager->startSuperSession(),
			$callback,
		));
	}

	/**
	 * @template T
	 * @param callable(CirclesManager): T $callback
	 * @return T
	 * @throws \Exception
	 */
	protected function asUser(string $userId, callable $callback): mixed {
		return $this->run(fn (CirclesManager $manager): mixed => $this->inSession(
			$manager,
			function () use ($manager, $userId): void {
				$manager->startSession($manager->getFederatedUser($userId, Member::TYPE_USER));
				// Never as super user, also when called inside a super session of Circles or another app
				$this->getCirclesService(FederatedUserService::class)->bypassCurrentUserCondition(false);
			},
			$callback,
		));
	}

	/**
	 * @template T
	 * @param callable(): void $start
	 * @param callable(CirclesManager): T $callback
	 * @return T
	 */
	protected function inSession(CirclesManager $manager, callable $start, callable $callback): mixed {
		$federatedUserService = $this->getCirclesService(FederatedUserService::class);
		$previousUser = $federatedUserService->getCurrentUser();
		$previousBypass = $federatedUserService->canBypassCurrentUserCondition();

		$start();
		try {
			return $callback($manager);
		} finally {
			$manager->stopSession();
			if ($previousUser !== null) {
				$federatedUserService->setCurrentUser($previousUser);
			}
			$federatedUserService->bypassCurrentUserCondition($previousBypass);
		}
	}

	/**
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T
	 */
	protected function getCirclesService(string $class): object {
		/** @var T */
		return $this->container->get($class);
	}

	/**
	 * @template T
	 * @param callable(CirclesManager): T $callback
	 * @return T
	 * @throws \Exception
	 */
	protected function run(callable $callback): mixed {
		if (!$this->isAvailable()) {
			throw new \Exception('Circles App unavailable');
		}

		try {
			$manager = $this->getCirclesService(CirclesManager::class);
			return $callback($manager);
		} catch (\Exception $e) {
			// The original exception is kept, see isTeamNotFound()
			throw new \Exception($e->getMessage(), 0, $e);
		} catch (\Throwable $e) {
			$this->logger->warning('Circles App unavailable', ['exception' => $e]);
			$this->available = false;
			throw new \Exception($e->getMessage(), 0, $e);
		}
	}

	/**
	 * Whether the exception of a call says that the team does not exist (deleted),
	 * not that Circles is unavailable or failed
	 */
	public static function isTeamNotFound(\Exception $e): bool {
		return $e->getPrevious() instanceof CircleNotFoundException;
	}
}
