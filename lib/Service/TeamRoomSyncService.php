<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Circles\Model\Member;
use OCA\Talk\BackgroundJob\ResyncTeamRoom;
use OCA\Talk\BackgroundJob\ResyncTeamRoomsNow;
use OCA\Talk\Config;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\FederationRestrictionException;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkTeamDirectMember from CirclesAdapter
 * @psalm-import-type TalkTeamRemoteMember from CirclesAdapter
 * @psalm-import-type TalkTeamDirectMembers from CirclesAdapter
 * @psalm-type TalkTeamRoomMarkers = array{left?: array<string, bool>, wait?: array<string, array{count: int, until: int}>, emailLeft?: array<string, bool>}
 */
class TeamRoomSyncService {
	/** Pause after the first failed invitation of a federated member, doubled with every further failure */
	public const INVITATION_RETRY_PAUSE = 300;
	/** Longest pause between two invitations: unreachable servers are still tried once a day */
	public const INVITATION_RETRY_PAUSE_MAX = 86400;

	/** Changes of the team that Talk makes right now, see isChangingTeamFromTalk() */
	protected int $changesFromTalk = 0;

	/**
	 * @var array<int, TalkTeamRoomMarkers>|null
	 */
	protected ?array $markerBatch = null;

	public function __construct(
		protected readonly CirclesAdapter $circlesAdapter,
		protected readonly Manager $manager,
		protected readonly ParticipantService $participantService,
		protected readonly TeamRoomSettingsService $settingsService,
		protected readonly BanService $banService,
		protected readonly Config $talkConfig,
		protected readonly ICloudIdManager $cloudIdManager,
		protected readonly FederationManager $federationManager,
		protected readonly IUserManager $userManager,
		protected readonly ITimeFactory $timeFactory,
		protected readonly LoggerInterface $logger,
		protected readonly RoomService $roomService,
		protected readonly IEventDispatcher $dispatcher,
		protected readonly IJobList $jobList,
		protected readonly IAppConfig $appConfig,
		protected readonly GuestManager $guestManager,
		protected readonly IConfig $serverConfig,
	) {
	}

	/**
	 * Resync team rooms with an id higher than $afterId until the time budget is used up
	 *
	 * @return int|null The id of the last synced room, 0 when the full pass is done,
	 *                  null when teams are unavailable and nothing more was touched
	 */
	public function resyncFrom(int $afterId, int $timeBudget): ?int {
		if (!$this->circlesAdapter->isAvailable()) {
			return null;
		}

		$start = $this->timeFactory->getTime();
		$cursor = $afterId;

		while ($this->timeFactory->getTime() - $start < $timeBudget) {
			$rooms = $this->manager->getRoomsByObjectType(Room::OBJECT_TYPE_TEAM, $cursor, 50);
			if ($rooms === []) {
				return 0;
			}

			foreach ($rooms as $room) {
				try {
					$this->resyncRoom($room, true);
				} catch (\Exception $e) {
					// Circles broke mid-run: don't touch any further room
					$this->logger->warning('Teams unavailable, team room resync stopped before room ' . $room->getToken(), ['exception' => $e]);
					return null;
				} catch (\Throwable $e) {
					// One broken room must not stop the resync of all the others
					$this->logger->error('Resync of team room ' . $room->getToken() . ' failed', ['exception' => $e]);
				}
				$cursor = $room->getId();
			}
		}

		return $cursor;
	}

	/**
	 * Sync in the background, e.g. to invite the federated members after a
	 * resync in a request
	 */
	public function queueResync(Room $room): void {
		$this->queueResyncOfTeam($room->getObjectId());
	}

	public function queueResyncOfTeam(string $teamId): void {
		$argument = ['teamId' => $teamId];
		if (!$this->jobList->has(ResyncTeamRoom::class, $argument)) {
			$this->jobList->add(ResyncTeamRoom::class, $argument);
		}
	}

	/**
	 * Start a resync pass over all team rooms, unless one is still running
	 * (ResyncTeamRoomsNow queues itself again with its cursor until it is done)
	 */
	public function queueFullResync(): void {
		foreach ($this->jobList->getJobsIterator(ResyncTeamRoomsNow::class, 1, 0) as $job) {
			return;
		}
		$this->jobList->add(ResyncTeamRoomsNow::class, ['afterId' => 0]);
	}

	public function resyncRoom(Room $room, bool $inviteFederated): void {
		$teamId = $room->getObjectId();

		try {
			// One lookup of the team for the whole resync
			$snapshot = $this->circlesAdapter->getTeamSnapshot($teamId);
		} catch (\Exception $e) {
			if (!CirclesAdapter::isTeamNotFound($e)) {
				// Circles is unavailable or failed: nothing is changed, the next resync tries again
				throw $e;
			}
			$this->handleDeletedTeam($room);
			return;
		}
		$team = $snapshot['team'];
		$direct = $snapshot['direct'];

		// Same as CircleEditedListener and TeamRoomSettingsListener, in case
		// the events were missed (or Circles is too old to fire them for settings)
		$this->participantService->updateDisplayNameForActor(Attendee::ACTOR_CIRCLES, $teamId, $team['name']);
		$this->settingsService->applyTeamToRoom($room, $team);

		/** @var array<string, int> $expected User id => participant type */
		$expected = [];
		foreach ($direct as $userId => $member) {
			$expected[(string)$userId] = self::getParticipantTypeForLevel($member['level']);
		}

		/** @var array<string, Participant> $current */
		$current = [];
		foreach ($this->participantService->getParticipantsByActorType($room, Attendee::ACTOR_USERS) as $participant) {
			$current[$participant->getAttendee()->getActorId()] = $participant;
		}

		// Promote before demote, add before remove: the room never lacks a moderator in between
		$added = $this->addMissing($room, $expected, $current);
		$this->updateTypes($room, $expected, $current);
		$this->removeFormer($room, $expected, $current);

		// Marker changes of all invitations are written once
		$this->withMarkerBatch(function () use ($room, $direct, $inviteFederated, $snapshot): void {
			$this->syncFederatedMembers($room, $direct, $inviteFederated, $snapshot['remote']);
			$this->syncEmailMembers($room, $inviteFederated, $snapshot['emails']);
		});
	}

	/**
	 * @param array<string, string>|null $expectedEmails Email members of the team, loaded when null
	 */
	protected function syncEmailMembers(Room $room, bool $inviteEmails = true, #[\SensitiveParameter] ?array $expectedEmails = null): void {
		try {
			$expectedEmails ??= $this->circlesAdapter->getEmailMembers($room->getObjectId());
		} catch (\Exception) {
			return;
		}

		/** @var array<string, Participant> $current Email (lower case) => participant */
		$current = [];
		foreach ($this->participantService->getParticipantsByActorType($room, Attendee::ACTOR_EMAILS) as $participant) {
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_EMAILS) {
				$current[mb_strtolower($participant->getAttendee()->getInvitedCloudId())] = $participant;
			}
		}

		$left = $this->getAllFederatedMarkers()[$room->getId()]['emailLeft'] ?? [];
		$formerLeft = array_diff_key($left, array_flip(array_map($this->hashMember(...), array_keys($expectedEmails))));
		if ($formerLeft !== []) {
			// Removed from the team: invited again when added again
			$this->updateFederatedMarkers($room, static function (array $markers) use ($formerLeft): array {
				$markers['emailLeft'] = array_diff_key($markers['emailLeft'] ?? [], $formerLeft);
				return $markers;
			});
		}

		if ($inviteEmails) {
			foreach ($expectedEmails as $email => $displayName) {
				if (!isset($current[$email]) && !isset($left[$this->hashMember($email)])) {
					$this->inviteEmailMember($room, $email, $displayName);
				}
			}
		}

		$toRemove = array_diff_key($current, $expectedEmails);
		if (empty($toRemove)) {
			return;
		}
		foreach ($toRemove as $participant) {
			$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);
		}
	}

	protected function inviteEmailMember(Room $room, #[\SensitiveParameter] string $email, #[\SensitiveParameter] string $displayName): void {
		$actorId = hash('sha256', $email);
		if ($this->banService->isEmailBanned($room, $email)) {
			// Also when the account with this address is banned
			return;
		}
		try {
			$participant = $this->participantService->inviteEmailAddress($room, $actorId, $email, $displayName !== $email ? $displayName : null);
			$this->guestManager->sendEmailInvitation($room, $participant);
		} catch (\Throwable $e) {
			$this->logger->warning('Email member of team conversation ' . $room->getToken() . ' could not be invited', ['exception' => $e]);
		}
	}

	public function rememberEmailLeave(Room $room, #[\SensitiveParameter] string $email): void {
		// Same key as in syncEmailMembers(), never the address itself in the app config
		$hash = $this->hashMember(mb_strtolower(mb_trim($email)));
		$this->updateFederatedMarkers($room, static function (array $markers) use ($hash): array {
			$markers['emailLeft'][$hash] = true;
			return $markers;
		});
	}

	/**
	 * @param array<array-key, TalkTeamDirectMember> $directMembers Local direct members, for the inviter
	 * @param list<TalkTeamRemoteMember>|null $remoteMembers Members on other servers, loaded when null
	 * @throws \Exception
	 */
	protected function syncFederatedMembers(Room $room, array $directMembers, bool $inviteFederated = true, ?array $remoteMembers = null): void {
		if (!$this->talkConfig->isFederationEnabled()) {
			return;
		}

		try {
			$remoteMembers ??= $this->circlesAdapter->getRemoteMembers($room->getObjectId());
		} catch (\Exception) {
			return;
		}

		/** @var array<string, array{cloudId: ICloudId, displayName: string, participantType: int}> $expected Normalized cloud id => member */
		$expected = [];
		foreach ($remoteMembers as $member) {
			try {
				$cloudId = $this->cloudIdManager->getCloudId($member['userId'], $member['instance']);
			} catch (\InvalidArgumentException) {
				continue;
			}
			$expected[$this->getNormalizedCloudId($cloudId)] = [
				'cloudId' => $cloudId,
				'displayName' => $member['displayName'],
				// Federated users can not be owners
				'participantType' => max(Participant::MODERATOR, self::getParticipantTypeForLevel($member['level'] ?? Member::LEVEL_MEMBER)),
			];
		}

		/** @var array<string, Participant> $current Normalized cloud id => participant */
		$current = [];
		foreach ($this->participantService->getParticipantsByActorType($room, Attendee::ACTOR_FEDERATED_USERS) as $participant) {
			try {
				$current[$this->getNormalizedCloudId($this->cloudIdManager->resolveCloudId($participant->getAttendee()->getActorId()))] = $participant;
			} catch (\InvalidArgumentException) {
				$current[$participant->getAttendee()->getActorId()] = $participant;
			}
		}

		$markers = $this->getFederatedMarkers($room, array_keys($expected));

		$inviter = null;
		foreach ($expected as $key => $member) {
			if (isset($current[$key])) {
				if ($current[$key]->getAttendee()->getParticipantType() !== $member['participantType']) {
					$this->participantService->updateParticipantType($room, $current[$key], $member['participantType']);
				}
				continue;
			}

			if (!$inviteFederated || isset($markers['left'][$key])
				|| ($markers['wait'][$key]['until'] ?? 0) > $this->timeFactory->getTime()) {
				continue;
			}

			$inviter ??= $this->getInviter($directMembers);
			if (is_null($inviter)) {
				$this->logger->warning('Team conversation ' . $room->getToken() . ' has no local owner or admin to invite the federated members of the team');
				break;
			}
			$this->inviteFederatedMember($room, $member['cloudId'], $member['displayName'], $member['participantType'], $inviter, $markers['wait'][$key]['count'] ?? 0);
		}

		$toRemove = array_diff_key($current, $expected);
		if (empty($toRemove)) {
			return;
		}
		foreach ($toRemove as $participant) {
			// The remote instance is notified, with retries when it is unreachable
			$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);
		}
	}

	/**
	 * @param int $failures Failed invitations so far, for the pause before the next try
	 */
	protected function inviteFederatedMember(Room $room, #[\SensitiveParameter] ICloudId $cloudId, #[\SensitiveParameter] string $displayName, int $participantType, IUser $inviter, int $failures = 0): bool {
		if ($this->banService->isActorBanned($room, Attendee::ACTOR_FEDERATED_USERS, $cloudId->getId())) {
			return false;
		}
		if (is_null($this->participantService->getHighestPermissionAttendee($room))) {
			// The invitation is sent in the name of a local owner or moderator
			$this->logger->warning('Team conversation ' . $room->getToken() . ' has no local owner or moderator to invite a federated member from ' . $cloudId->getRemote());
			return false;
		}

		$key = $this->getNormalizedCloudId($cloudId);
		try {
			$this->federationManager->isAllowedToInvite($inviter, $cloudId, $room);
			$this->participantService->addUsers($room, [[
				'actorType' => Attendee::ACTOR_FEDERATED_USERS,
				'actorId' => $cloudId->getId(),
				'displayName' => !empty($displayName) ? $displayName : $cloudId->getDisplayId(),
				'participantType' => $participantType,
			]], $inviter);
			$hash = $this->hashMember($key);
			$this->updateFederatedMarkers($room, static function (array $markers) use ($hash): array {
				unset($markers['wait'][$hash]);
				return $markers;
			});
			return true;
		} catch (FederationRestrictionException $e) {
			$this->logger->info('Federated team member from ' . $cloudId->getRemote() . ' can not be invited to ' . $room->getToken() . ': ' . $e->getReason());
		} catch (CannotReachRemoteException) {
			// Not removed or given up: tried again after a pause
			$this->logger->info('Federated team member from ' . $cloudId->getRemote() . ' could not be invited to ' . $room->getToken() . ', remote not reachable');
		} catch (\Throwable $e) {
			$this->logger->warning('Federated team member from ' . $cloudId->getRemote() . ' could not be invited to ' . $room->getToken(), ['exception' => $e]);
		}

		$failures++;
		$until = $this->timeFactory->getTime() + self::getInvitationRetryPause($failures);
		$hash = $this->hashMember($key);
		$this->updateFederatedMarkers($room, static function (array $markers) use ($hash, $failures, $until): array {
			$markers['wait'][$hash] = ['count' => $failures, 'until' => $until];
			return $markers;
		});
		return false;
	}

	/**
	 * 5 minutes after the first failure, then doubled up to a day: servers that
	 * are down for longer do not slow down every resync with timeouts
	 */
	public static function getInvitationRetryPause(int $failures): int {
		$doublings = min(max($failures, 1) - 1, 20);
		return min(self::INVITATION_RETRY_PAUSE << $doublings, self::INVITATION_RETRY_PAUSE_MAX);
	}

	/**
	 */
	public function rememberFederatedLeave(Room $room, #[\SensitiveParameter] string $cloudId): void {
		try {
			$key = $this->getNormalizedCloudId($this->cloudIdManager->resolveCloudId($cloudId));
		} catch (\InvalidArgumentException) {
			$key = $cloudId;
		}
		$hash = $this->hashMember($key);
		$this->updateFederatedMarkers($room, static function (array $markers) use ($hash): array {
			$markers['left'][$hash] = true;
			return $markers;
		});
	}

	/**
	 * @return array<int, TalkTeamRoomMarkers>
	 */
	protected function getAllFederatedMarkers(): array {
		if ($this->markerBatch !== null) {
			return $this->markerBatch;
		}
		/** @var array<int, TalkTeamRoomMarkers> */
		return $this->appConfig->getAppValueArray(Config::TEAM_FEDERATED_MEMBERS);
	}

	/***
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	protected function withMarkerBatch(callable $callback): mixed {
		if (!is_null($this->markerBatch)) {
			return $callback();
		}
		$before = $this->getAllFederatedMarkers();
		$this->markerBatch = $before;
		try {
			return $callback();
		} finally {
			$batch = $this->markerBatch;
			$this->markerBatch = null;
			if ($batch !== $before) {
				$this->appConfig->setAppValueArray(Config::TEAM_FEDERATED_MEMBERS, $batch);
			}
		}
	}

	/**
	 * Key of a member in the markers: HMAC-SHA256 with the secret of the instance
	 */
	protected function hashMember(string $memberKey): string {
		return hash_hmac('sha256', $memberKey, $this->serverConfig->getSystemValueString('secret'));
	}

	/**
	 * @param callable(TalkTeamRoomMarkers): TalkTeamRoomMarkers $change
	 */
	protected function updateFederatedMarkers(Room $room, callable $change): void {
		$all = $this->getAllFederatedMarkers();
		$roomMarkers = $change($all[$room->getId()] ?? []);
		$roomMarkers = array_filter($roomMarkers);
		if (empty($roomMarkers)) {
			if (!isset($all[$room->getId()])) {
				return;
			}
			unset($all[$room->getId()]);
		} else {
			$all[$room->getId()] = $roomMarkers;
		}
		if (!is_null($this->markerBatch)) {
			$this->markerBatch = $all;
			return;
		}
		$this->appConfig->setAppValueArray(Config::TEAM_FEDERATED_MEMBERS, $all);
	}

	public function deleteFederatedMarkers(Room $room): void {
		$this->updateFederatedMarkers($room, static fn (array $markers): array => []);
	}

	/**
	 * @param list<string> $memberKeys Normalized cloud ids of the remote members of the team
	 * @return array{left: array<string, true>, wait: array<string, array{count: int, until: int}>}
	 */
	protected function getFederatedMarkers(Room $room, #[\SensitiveParameter] array $memberKeys): array {
		$roomMarkers = $this->getAllFederatedMarkers()[$room->getId()] ?? [];
		if (empty($roomMarkers)) {
			return ['left' => [], 'wait' => []];
		}

		$hashes = [];
		foreach ($memberKeys as $memberKey) {
			$hashes[$this->hashMember($memberKey)] = $memberKey;
		}

		$markers = ['left' => [], 'wait' => []];
		foreach ($roomMarkers['left'] ?? [] as $hash => $_) {
			if (isset($hashes[$hash])) {
				$markers['left'][$hashes[$hash]] = true;
			}
		}
		foreach ($roomMarkers['wait'] ?? [] as $hash => $wait) {
			if (isset($hashes[$hash])) {
				$markers['wait'][$hashes[$hash]] = ['count' => (int)$wait['count'], 'until' => (int)$wait['until']];
			}
		}

		$current = ['left' => array_intersect_key($roomMarkers['left'] ?? [], $hashes), 'wait' => array_intersect_key($roomMarkers['wait'] ?? [], $hashes)];
		if ($current !== ['left' => $roomMarkers['left'] ?? [], 'wait' => $roomMarkers['wait'] ?? []]) {
			// Former members of the team: invited again when added again
			$this->updateFederatedMarkers($room, static function (array $markers) use ($hashes): array {
				// Only the federated markers, the email markers stay (syncEmailMembers())
				$markers['left'] = array_intersect_key($markers['left'] ?? [], $hashes);
				$markers['wait'] = array_intersect_key($markers['wait'] ?? [], $hashes);
				return $markers;
			});
		}
		return $markers;
	}

	/**
	 * @param array<array-key, TalkTeamDirectMember> $directMembers
	 */
	protected function getInviter(array $directMembers): ?IUser {
		$candidates = array_filter($directMembers, static fn (array $member): bool => $member['level'] >= Member::LEVEL_ADMIN);
		uasort($candidates, static fn (array $a, array $b): int => [$b['level'], $a['joined']] <=> [$a['level'], $b['joined']]);
		foreach (array_keys($candidates) as $userId) {
			$user = $this->userManager->get((string)$userId);
			if (!is_null($user)) {
				return $user;
			}
		}
		return null;
	}

	protected function getNormalizedCloudId(#[\SensitiveParameter] ICloudId $cloudId): string {
		return $cloudId->getUser() . '@' . CirclesAdapter::getNormalizedInstanceDomain($cloudId->getRemote());
	}

	/**
	 * @return Participant|null The participant after the sync, null when the user is not a member of the team
	 * @throws ForbiddenException When the user is a member, but banned from the room
	 * @throws \Exception
	 */
	public function syncUserInTeamRoom(Room $room, IUser $user, bool $withTeamSettings = false): ?Participant {
		$teamId = $room->getObjectId();
		$userId = $user->getUID();

		if ($withTeamSettings) {
			$this->settingsService->applyTeamToRoom($room, $this->circlesAdapter->getTeam($teamId));
		}

		// Only this user: cheap also for large teams
		$member = $this->circlesAdapter->getDirectMember($teamId, $userId);

		try {
			$participant = $this->participantService->getParticipant($room, $userId, false);
		} catch (ParticipantNotFoundException) {
			$participant = null;
		}

		if (is_null($member)) {
			if (!is_null($participant)) {
				$this->participantService->removeUser($room, $user, AAttendeeRemovedEvent::REASON_REMOVED);
			}
			return null;
		}

		$this->banService->throwIfActorIsBanned($room, $userId);

		$participantType = self::getParticipantTypeForLevel($member['level']);
		if (is_null($participant)) {
			$this->participantService->addUsers($room, [[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $userId,
				'displayName' => $user->getDisplayName(),
				'participantType' => $participantType,
			]], bansAlreadyChecked: true);
			return $this->participantService->getParticipant($room, $userId, false);
		}

		if ($participant->getAttendee()->getParticipantType() !== $participantType) {
			$this->participantService->updateParticipantType($room, $participant, $participantType);
		}
		return $participant;
	}

	/**
	 * @return Participant::OWNER|Participant::MODERATOR|Participant::USER
	 */
	public static function getParticipantTypeForLevel(int $level): int {
		return match (true) {
			$level >= Member::LEVEL_ADMIN => Participant::OWNER,
			$level >= Member::LEVEL_MODERATOR => Participant::MODERATOR,
			default => Participant::USER,
		};
	}

	/**
	 * @return list<string> Added users
	 */
	protected function addMissing(Room $room, array $expected, array $current): array {
		$toAdd = [];
		foreach ($expected as $userId => $participantType) {
			$userId = (string)$userId;
			if (isset($current[$userId])) {
				continue;
			}

			$user = $this->userManager->get($userId);
			if (is_null($user)) {
				// e.g. LDAP remnant: unknown, skip instead of failing
				continue;
			}

			$toAdd[] = [
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $userId,
				'displayName' => $user->getDisplayName(),
				'participantType' => $participantType,
			];
		}

		if (empty($toAdd)) {
			return [];
		}
		// Bans are checked by addUsers()
		$attendees = $this->participantService->addUsers($room, $toAdd);
		return array_map(static fn (Attendee $attendee): string => $attendee->getActorId(), $attendees);
	}

	/**
	 * @param array<array-key, int> $expected User id => participant type
	 * @param array<string, Participant> $current
	 */
	protected function updateTypes(Room $room, array $expected, array $current): void {
		$promotions = $demotions = [];
		foreach ($expected as $userId => $participantType) {
			$participant = $current[(string)$userId] ?? null;
			if ($participant === null || $participant->getAttendee()->getParticipantType() === $participantType) {
				continue;
			}

			if ($participantType === Participant::OWNER
				|| ($participantType === Participant::MODERATOR && !$participant->hasModeratorPermissions(false))) {
				$promotions[] = [$participant, $participantType];
			} else {
				$demotions[] = [$participant, $participantType];
			}
		}

		foreach ([...$promotions, ...$demotions] as [$participant, $participantType]) {
			$this->participantService->updateParticipantType($room, $participant, $participantType);
		}
	}

	protected function removeFormer(Room $room, array $expected, array $current): void {
		$toRemove = [];
		foreach (array_keys($current) as $userId) {
			if (!isset($expected[$userId])) {
				$toRemove[] = (string)$userId;
			}
		}

		if (empty($toRemove)) {
			return;
		}

		foreach ($toRemove as $userId) {
			$user = $this->userManager->get($userId);
			if (!is_null($user)) {
				$this->participantService->removeUser($room, $user, AAttendeeRemovedEvent::REASON_REMOVED);
			} else {
				// Deleted user that is still listed: remove the attendee directly
				$this->participantService->removeAttendee($room, $current[$userId], AAttendeeRemovedEvent::REASON_REMOVED);
			}
		}
	}

	/**
	 * @param Room $room
	 * @return void
	 * @throws \Exception
	 */
	protected function handleDeletedTeam(Room $room): void {
		// Missed CircleDestroyedEvent (e.g. Circles was disabled meanwhile)
		$this->archiveForDeletedTeam($room);
	}

	/**
	 * @param Room $room
	 * @return void
	 * @throws \Exception
	 */
	public function archiveForDeletedTeam(Room $room): void {
		if (!$room->isTeamManaged()
			|| ($room->getReadOnly() === Room::READ_ONLY && $this->participantService->getNumberOfModerators($room) === 0)) {
			return;
		}

		$teamId = $room->getObjectId();

		$this->roomService->setReadOnly($room, Room::READ_ONLY);

		foreach ([Attendee::ACTOR_USERS, Attendee::ACTOR_FEDERATED_USERS] as $actorType) {
			foreach ($this->participantService->getParticipantsByActorType($room, $actorType) as $participant) {
				if (in_array($participant->getAttendee()->getParticipantType(), [Participant::OWNER, Participant::MODERATOR], true)) {
					$this->participantService->updateParticipantType($room, $participant, Participant::USER);
				}
				if ($actorType === Attendee::ACTOR_USERS && !$participant->getAttendee()->isArchived()) {
					$this->participantService->archiveConversation($participant);
				}
			}
		}

	}

	/**
	 * Whether the attendee is still a member of the team of the room: removing
	 * them only from the room would be undone by the next resync
	 *
	 * Users are checked as direct members, federated users as members of
	 * their server, email guests by their address. When the team was deleted
	 * nobody is a member anymore, when Circles is unavailable everybody stays one.
	 */
	public function isMemberOfTeam(Room $room, #[\SensitiveParameter] Attendee $attendee): bool {
		$teamId = $room->getObjectId();
		try {
			return match ($attendee->getActorType()) {
				Attendee::ACTOR_USERS => !is_null($this->circlesAdapter->getDirectMember($teamId, $attendee->getActorId())),
				Attendee::ACTOR_FEDERATED_USERS => $this->isRemoteMemberOfTeam($teamId, $attendee->getActorId()),
				Attendee::ACTOR_EMAILS => isset($this->circlesAdapter->getEmailMembers($teamId)[mb_strtolower($attendee->getInvitedCloudId())]),
				default => false,
			};
		} catch (\Exception $e) {
			return !CirclesAdapter::isTeamNotFound($e);
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function isRemoteMemberOfTeam(string $teamId, #[\SensitiveParameter] string $cloudId): bool {
		try {
			$key = $this->getNormalizedCloudId($this->cloudIdManager->resolveCloudId($cloudId));
		} catch (\InvalidArgumentException) {
			return false;
		}
		foreach ($this->circlesAdapter->getRemoteMembers($teamId) as $member) {
			try {
				if ($this->getNormalizedCloudId($this->cloudIdManager->getCloudId($member['userId'], $member['instance'])) === $key) {
					return true;
				}
			} catch (\InvalidArgumentException) {
			}
		}
		return false;
	}

	/**
	 * Leaving a team conversation means leaving the team, otherwise the next
	 * resync would add the user again. The user leaves themselves, so Circles
	 * checks it like "Leave team" in the Teams app. When the owner leaves,
	 * Circles picks the new owner and the resync makes them the room owner in Talk.
	 *
	 * @throws \Exception When the team refused it or Circles is unavailable
	 */
	public function leaveTeamOfRoom(Room $room, string $userId): void {
		$teamId = $room->getObjectId();
		try {
			if ($this->circlesAdapter->getDirectMember($teamId, $userId) === null) {
				// Stale participant: leaving the conversation is enough
				return;
			}
			$this->changeTeamFromTalk(fn () => $this->circlesAdapter->leaveTeam($teamId, $userId));
		} catch (\Exception $e) {
			if (!CirclesAdapter::isTeamNotFound($e)) {
				throw $e;
			}
			// The team was deleted: leaving the conversation is enough
		}
	}

	/**
	 * @template T
	 * @param callable(): T $change
	 * @return T
	 */
	protected function changeTeamFromTalk(callable $change): mixed {
		$this->changesFromTalk++;
		try {
			return $change();
		} finally {
			$this->changesFromTalk--;
		}
	}

	/**
	 * Whether Talk is changing the team in this process and updates the
	 * conversation itself: the listeners of the Circles events skip it then.
	 * In web requests Circles may apply the change later (asynchronously),
	 * then the removal arrives after Talk removed the participant already and
	 * changes nothing.
	 */
	public function isChangingTeamFromTalk(): bool {
		return $this->changesFromTalk > 0;
	}

	/**
	 * @param bool $left Left the team themselves, otherwise removed
	 */
	public function removeFormerTeamMember(Room $room, #[\SensitiveParameter] Member $member, bool $left): void {
		if ($member->getUserType() === Member::TYPE_USER && $member->isLocal()) {
			try {
				$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $member->getUserId());
			} catch (ParticipantNotFoundException) {
				return;
			}
			$this->participantService->removeAttendee($room, $participant, $left ? AAttendeeRemovedEvent::REASON_LEFT : AAttendeeRemovedEvent::REASON_REMOVED);
			return;
		}

		if ($member->getUserType() === Member::TYPE_USER) {
			try {
				$key = $this->getNormalizedCloudId($this->cloudIdManager->getCloudId($member->getUserId(), $member->getInstance()));
			} catch (\InvalidArgumentException) {
				return;
			}
			foreach ($this->participantService->getParticipantsByActorType($room, Attendee::ACTOR_FEDERATED_USERS) as $participant) {
				try {
					$participantKey = $this->getNormalizedCloudId($this->cloudIdManager->resolveCloudId($participant->getAttendee()->getActorId()));
				} catch (\InvalidArgumentException) {
					continue;
				}
				if ($participantKey === $key) {
					// Removed, not left: a left marker would only block a later invitation.
					// The remote server is told, with retries when it is unreachable.
					$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);
					return;
				}
			}
			return;
		}

		$email = $this->circlesAdapter->getEmailOfMember($member);
		if (is_null($email)) {
			// Groups and teams as members: their users are not participants
			return;
		}
		try {
			$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_EMAILS, hash('sha256', $email));
		} catch (ParticipantNotFoundException) {
			return;
		}
		$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);
	}

}
