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
use OCA\Circles\Model\Member;
use OCA\Circles\Service\CircleService;
use OCA\Circles\Service\ContactService;
use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @psalm-type TalkTeamInfo = array{id: string, name: string, description: string, config: int, local: bool}
 * @psalm-type TalkTeamDirectMember = array{level: int, joined: int}
 * @psalm-type TalkTeamDirectMembers = array<string, TalkTeamDirectMember>
 * @psalm-type TalkTeamRemoteMember = array{userId: string, instance: string, displayName: string, level: int}
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
	 * Everything a resync needs, with one lookup of the team and its members
	 * (instead of getTeam(), getDirectMembers(), getRemoteMembers() and
	 * getEmailMembers() one after the other); also one consistent state
	 *
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
					$direct[self::getUserIdOf($member)] = $directEntry;
					continue;
				}
				$remoteEntry = self::getRemoteMemberEntry($member);
				if (!empty($remoteEntry)) {
					$remote[] = $remoteEntry;
					continue;
				}
				$email = $member->getLevel() >= Member::LEVEL_MEMBER ? $this->getEmailOfMember($member) : null;
				if (!empty($email)) {
					$emails[$email] = !empty(self::getDisplayNameOf($member)) ? self::getDisplayNameOf($member) : $email;
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

	protected static function getUserIdOf(Member $member): string {
		return $member->getUserId();
	}

	protected static function getDisplayNameOf(Member $member): string {
		return $member->getDisplayName();
	}

	protected static function getDirectMemberEntry(Member $member): ?array {
		if ($member->getUserType() !== Member::TYPE_USER || empty($member->getUserId())
			|| !$member->isLocal() || $member->getLevel() < Member::LEVEL_MEMBER) {
			return null;
		}
		return ['level' => $member->getLevel(), 'joined' => $member->getJoined()];
	}

	protected static function getRemoteMemberEntry(Member $member): ?array {
		if ($member->getUserType() !== Member::TYPE_USER || $member->isLocal()
			|| empty($member->getUserId()) || $member->getLevel() < Member::LEVEL_MEMBER) {
			return null;
		}
		return [
			'userId' => $member->getUserId(),
			'instance' => $member->getInstance(),
			'displayName' => $member->getDisplayName(),
			'level' => $member->getLevel(),
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
		return $this->asSuperUser(static function (CirclesManager $manager) use ($teamId): array {
			/** @var TalkTeamDirectMembers $members */
			$members = [];
			foreach ($manager->getCircle($teamId)->getMembers() as $member) {
				$entry = self::getDirectMemberEntry($member);
				if (!is_null($entry)) {
					$members[self::getUserIdOf($member)] = $entry;
				}
			}
			return $members;
		});
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
			$memberRequest = $this->getCirclesService(MemberRequest::class);
			foreach ($memberRequest->getMembersByUserId($userId) as $member) {
				if ($member->getCircleId() === $teamId
					&& $member->getUserType() === Member::TYPE_USER
					&& $member->isLocal()) {
					return $member->getLevel() >= Member::LEVEL_MEMBER
						? ['level' => $member->getLevel(), 'joined' => $member->getJoined()]
						: null;
				}
			}
			// Not a member: make sure the team still exists (throws otherwise)
			$manager->getCircle($teamId);
			return null;
		});
	}

	/**
	 * Email members of the team (and contact members, by the first address of
	 * the contact), the external members of the Teams app
	 *
	 * @return array<string, string> Email address (lower case) => display name
	 * @throws \Exception
	 */
	public function getEmailMembers(string $teamId): array {
		return $this->asSuperUser(function (CirclesManager $manager) use ($teamId): array {
			$emails = [];
			foreach ($manager->getCircle($teamId)->getMembers() as $member) {
				$email = $this->getEmailOfMember($member);
				if (!is_null($email) && $member->getLevel() >= Member::LEVEL_MEMBER) {
					$emails[$email] = !empty(self::getDisplayNameOf($member)) ? self::getDisplayNameOf($member) : $email;
				}
			}
			return $emails;
		});
	}

	/**
	 * The first direct member of the team that matches, in the session the
	 * caller started (permissions of the acting user, or super user)
	 *
	 * @param callable(Member): bool $match
	 * @throws \Exception
	 */
	protected static function findMember(CirclesManager $manager, string $teamId, callable $match): ?Member {
		foreach ($manager->getCircle($teamId)->getMembers() as $member) {
			if ($match($member)) {
				return $member;
			}
		}
		return null;
	}

	/**
	 * A local user (remote members can have the same user id)
	 */
	protected static function isLocalUser(Member $member, string $userId): bool {
		return $member->getUserType() === Member::TYPE_USER
			&& $member->isLocal()
			&& $member->getUserId() === $userId;
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
	 * @return array
	 * @throws \Exception
	 */
	public function getRemoteMembers(string $teamId): array {
		return $this->asSuperUser(static function (CirclesManager $manager) use ($teamId): array {
			$remoteMembers = [];
			foreach ($manager->getCircle($teamId)->getMembers() as $member) {
				$entry = self::getRemoteMemberEntry($member);
				if (!is_null($entry)) {
					$remoteMembers[] = $entry;
				}
			}
			return $remoteMembers;
		});
	}

	/**
	 * The user joins the team themselves (CFG_OPEN, with CFG_REQUEST only a request)
	 *
	 * @return bool True when the user is a member now, false when the request needs a moderator
	 * @throws \Exception
	 */
	public function joinTeam(string $teamId, string $userId): bool {
		return $this->asUser($userId, function (CirclesManager $manager) use ($teamId, $userId): bool {
			$circleService = $this->getCirclesService(CircleService::class);
			$circleService->circleJoin($teamId);

			$member = self::findMember($manager, $teamId, static fn (Member $member): bool => self::isLocalUser($member, $userId));
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
				$circleService->updateDisplayName($teamId, $settings['name']);
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
		$this->asUser($actorUserId, self::removeDirectUserCallback($teamId, $userId));
	}

	/**
	 * @return callable(CirclesManager): void
	 */
	protected static function removeDirectUserCallback(string $teamId, string $userId): callable {
		return static function (CirclesManager $manager) use ($teamId, $userId): void {
			$member = self::findMember($manager, $teamId, static fn (Member $member): bool => self::isLocalUser($member, $userId));
			if (is_null($member)) {
				throw new \Exception('not-direct-member');
			}
			$manager->removeMember($member->getId());
		};
	}

	/**
	 * Instances as Circles and cloud ids write them can differ in case
	 * (host names are case-insensitive), protocol and trailing slash
	 */
	public static function getNormalizedInstanceDomain(string $instanceUrl): string {
		return mb_strtolower(preg_replace('/^https?:\/\//i', '', mb_rtrim($instanceUrl, '/')));
	}

	/**
	 * @template T
	 * @param callable(CirclesManager): T $callback
	 * @return T
	 * @throws \Exception
	 */
	protected function asSuperUser(callable $callback): mixed {
		return $this->run(static function (CirclesManager $manager) use ($callback): mixed {
			$manager->startSuperSession();
			try {
				return $callback($manager);
			} finally {
				$manager->stopSession();
			}
		});
	}

	/**
	 * @template T
	 * @param callable(CirclesManager): T $callback
	 * @return T
	 * @throws \Exception
	 */
	protected function asUser(string $userId, callable $callback): mixed {
		return $this->run(static function (CirclesManager $manager) use ($userId, $callback): mixed {
			$manager->startSession($manager->getFederatedUser($userId, Member::TYPE_USER));
			try {
				return $callback($manager);
			} finally {
				$manager->stopSession();
			}
		});
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
