<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Sync;

use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Matrix\Client\Model\Event;
use OCA\Talk\Matrix\Client\Model\JoinedRoom;
use OCA\Talk\Matrix\Client\Model\Member;
use OCA\Talk\Matrix\Client\Model\PowerLevels;
use OCA\Talk\Matrix\Client\Model\RoomState;
use OCA\Talk\Matrix\Client\Model\SyncBatch;
use OCA\Talk\Matrix\Client\Room\NameCalculator;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixMember;
use OCA\Talk\Matrix\Model\MatrixMemberMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Mirrors the joined Matrix rooms of linked accounts as read-only
 * conversations: name, description, the joined members as attendees and the
 * timeline as chat messages. Members with a linked account are added as
 * users, everyone else as Matrix actor.
 */
class RoomSyncService {
	/** Chat and reactions are mirrored, calls are not supported */
	public const DEFAULT_PERMISSIONS = Attendee::PERMISSIONS_CUSTOM | Attendee::PERMISSIONS_CHAT | Attendee::PERMISSIONS_REACT;

	public function __construct(
		private readonly Manager $manager,
		private readonly RoomService $roomService,
		private readonly ParticipantService $participantService,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly MatrixMemberMapper $memberMapper,
		private readonly AccountMapper $accountMapper,
		private readonly EventMapMapper $eventMapMapper,
		private readonly MessageSyncService $messageSyncService,
		private readonly IL10N $l,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @param bool $initial Whether the batch is from an initial sync, so the messages are history
	 * @return array{rooms: int, messages: int, failed: int}
	 */
	public function process(Account $account, SyncBatch $batch, bool $initial): array {
		$stats = ['rooms' => 0, 'messages' => 0, 'failed' => 0];
		foreach ($batch->joined as $roomId => $joined) {
			try {
				$stats['messages'] += $this->applyJoinedRoom($account, $joined, $initial);
				$stats['rooms']++;
			} catch (\Throwable $e) {
				$stats['failed']++;
				$this->logger->error('Matrix room ' . $roomId . ' could not be synced for ' . $account->getMxid(), ['exception' => $e]);
			}
		}

		foreach ($batch->left as $roomId) {
			try {
				$this->leaveRoom($account, $roomId);
			} catch (\Throwable $e) {
				$stats['failed']++;
				$this->logger->error('Leaving Matrix room ' . $roomId . ' failed for ' . $account->getMxid(), ['exception' => $e]);
			}
		}
		return $stats;
	}

	/**
	 * Remove the user of the account from all mirrored rooms and delete the
	 * conversations no other linked account is in
	 */
	public function removeAccount(Account $account): void {
		foreach ($this->memberMapper->getForAccount((string)$account->getId()) as $member) {
			$member->setAccountId(null);
			$this->memberMapper->update($member);
			$this->removeUserFromRoom($account, $member->getMatrixRoomId());
		}
	}

	/**
	 * @return int Number of new messages
	 */
	protected function applyJoinedRoom(Account $account, JoinedRoom $joined, bool $initial): int {
		$matrixRoom = $this->findMatrixRoom($joined->roomId);
		$state = new RoomState($joined->roomId);
		$members = [];
		if ($matrixRoom !== null) {
			$members = $this->memberMapper->getForRoom((string)$matrixRoom->getId());
			$this->seedState($state, $matrixRoom, $members);
		}
		$state->applyAll($joined->stateEvents);

		if ($state->isSpace()) {
			return 0;
		}

		if ($matrixRoom === null) {
			$matrixRoom = new MatrixRoom();
			$matrixRoom->setMatrixRoomId($joined->roomId);
			// Unique on the Matrix room id, so a concurrent sync of another account fails here and retries later
			$matrixRoom = $this->roomMapper->insert($matrixRoom);
		}
		$matrixRoom->setName($state->name !== null ? mb_substr($state->name, 0, 255) : null);
		$matrixRoom->setTopic($state->topic);
		$matrixRoom->setCanonicalAlias($state->canonicalAlias !== null ? mb_substr($state->canonicalAlias, 0, 255) : null);
		$matrixRoom->setCreator($state->creator !== '' ? mb_substr($state->creator, 0, 255) : null);
		$matrixRoom->setEncrypted($state->encrypted);
		$matrixRoom->setPowerLevels($state->powerLevels !== [] ? json_encode($state->powerLevels, JSON_THROW_ON_ERROR) : null);

		$members = $this->updateMembers($matrixRoom, $state, $members);
		$accounts = $this->accountMapper->getByMxids(array_values(array_filter(array_map(
			static fn (MatrixMember $member): ?string => $member->getAccountId() !== null ? $member->getMxid() : null,
			$members,
		))));

		$name = $this->getName($state, $joined, $account, $members);
		$description = mb_substr($state->topic ?? '', 0, Room::DESCRIPTION_MAXIMUM_LENGTH);
		// Messages can not be sent to encrypted rooms yet
		$readOnly = $state->encrypted ? Room::READ_ONLY : Room::READ_WRITE;
		$room = $this->findTalkRoom($matrixRoom);
		$created = $room === null;
		if ($room === null) {
			$room = $this->roomService->createConversation(
				Room::TYPE_GROUP,
				$name,
				objectType: Room::OBJECT_TYPE_MATRIX,
				objectId: (string)$matrixRoom->getId(),
				readOnly: $readOnly,
				permissions: self::DEFAULT_PERMISSIONS,
				description: $description,
			);
			$matrixRoom->setRoomId($room->getId());
		} else {
			$this->roomService->setName($room, $name);
			$this->roomService->setDescription($room, $description);
			$this->roomService->setReadOnly($room, $readOnly);
			if ($room->getDefaultPermissions() !== self::DEFAULT_PERMISSIONS) {
				$this->roomService->setDefaultPermissions($room, self::DEFAULT_PERMISSIONS);
			}
		}
		$this->roomMapper->update($matrixRoom);

		$this->updateAttendees($room, $members, $accounts);
		$this->updatePermissions($room, $state->getPowerLevels(), $accounts);

		return $this->messageSyncService->apply($room, $matrixRoom, $joined->timeline, $members, $accounts, $initial || $created);
	}

	protected function leaveRoom(Account $account, string $matrixRoomId): void {
		$matrixRoom = $this->findMatrixRoom($matrixRoomId);
		if ($matrixRoom === null) {
			return;
		}

		$member = $this->memberMapper->getForRoom((string)$matrixRoom->getId())[$account->getMxid()] ?? null;
		if ($member !== null) {
			$member->setMembership(Member::LEAVE);
			$this->memberMapper->update($member);
		}
		$this->removeUserFromRoom($account, (string)$matrixRoom->getId());
	}

	protected function removeUserFromRoom(Account $account, string $matrixRoomId): void {
		try {
			$matrixRoom = $this->roomMapper->getById($matrixRoomId);
		} catch (DoesNotExistException) {
			return;
		}

		$room = $this->findTalkRoom($matrixRoom);
		if ($room !== null) {
			try {
				$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $account->getUserId());
				$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_LEFT);
			} catch (ParticipantNotFoundException) {
			}
		}

		if (!$this->memberMapper->hasLinkedMembers($matrixRoomId)) {
			$this->memberMapper->deleteForRoom($matrixRoomId);
			$this->eventMapMapper->deleteForRoom($matrixRoomId);
			$this->roomMapper->delete($matrixRoom);
			if ($room !== null) {
				$this->roomService->deleteRoom($room);
			}
		}
	}

	/**
	 * Restore the state known from earlier syncs, incremental syncs only
	 * contain the changes
	 *
	 * @param array<string, MatrixMember> $members
	 */
	protected function seedState(RoomState $state, MatrixRoom $matrixRoom, array $members): void {
		$events = [];
		if ($matrixRoom->getName() !== null) {
			$events[] = new Event('', 'm.room.name', '', ['name' => $matrixRoom->getName()], '');
		}
		if ($matrixRoom->getTopic() !== null) {
			$events[] = new Event('', 'm.room.topic', '', ['topic' => $matrixRoom->getTopic()], '');
		}
		if ($matrixRoom->getCanonicalAlias() !== null) {
			$events[] = new Event('', 'm.room.canonical_alias', '', ['alias' => $matrixRoom->getCanonicalAlias()], '');
		}
		if ($matrixRoom->getCreator() !== null) {
			$events[] = new Event('', 'm.room.create', $matrixRoom->getCreator(), ['creator' => $matrixRoom->getCreator()], '');
		}
		if ($matrixRoom->getEncrypted()) {
			$events[] = new Event('', 'm.room.encryption', '', [], '');
		}
		if ($matrixRoom->getPowerLevels() !== null) {
			$events[] = new Event('', 'm.room.power_levels', '', $matrixRoom->getPowerLevelsArray(), '');
		}
		foreach ($members as $member) {
			$events[] = new Event('', 'm.room.member', '', array_filter([
				'membership' => $member->getMembership(),
				'displayname' => $member->getDisplayName(),
			], static fn (?string $value): bool => $value !== null), $member->getMxid());
		}
		$state->applyAll($events);
	}

	/**
	 * @param array<string, MatrixMember> $members
	 * @return array<string, MatrixMember>
	 */
	protected function updateMembers(MatrixRoom $matrixRoom, RoomState $state, array $members): array {
		$stateMembers = $state->getMembers();
		$accounts = $this->accountMapper->getByMxids(array_map('strval', array_keys($stateMembers)));

		foreach ($stateMembers as $mxid => $stateMember) {
			$mxid = (string)$mxid;
			$member = $members[$mxid] ?? null;
			if ($member === null) {
				$member = new MatrixMember();
				$member->setMatrixRoomId((string)$matrixRoom->getId());
				$member->setMxid($mxid);
			}
			$member->setMembership($stateMember->membership);
			$member->setDisplayName($stateMember->displayName !== null ? mb_substr($stateMember->displayName, 0, 255) : null);
			$member->setAccountId(isset($accounts[$mxid]) ? (string)$accounts[$mxid]->getId() : null);

			if ($member->getId() === null) {
				$members[$mxid] = $this->memberMapper->insert($member);
			} elseif ($member->getUpdatedFields() !== []) {
				$this->memberMapper->update($member);
			}
		}
		return $members;
	}

	/**
	 * Unnamed rooms are named after their members as seen by the first linked
	 * member, so the name does not change depending on which account synced
	 *
	 * @param array<string, MatrixMember> $members
	 */
	protected function getName(RoomState $state, JoinedRoom $joined, Account $account, array $members): string {
		$linked = array_keys(array_filter($members, static fn (MatrixMember $member): bool => $member->getAccountId() !== null && $member->getMembership() === Member::JOIN));
		$linked = array_map('strval', $linked);
		sort($linked);

		$name = NameCalculator::calculate(
			$state,
			$linked[0] ?? $account->getMxid(),
			$joined->heroes,
			$joined->joinedMemberCount,
			$joined->invitedMemberCount,
			fn (string $text): string => match ($text) {
				'Empty room' => $this->l->t('Empty room'),
				// TRANSLATORS %s is the list of former members of a Matrix room
				'Empty room (was %s)' => $this->l->t('Empty room (was %s)'),
				// TRANSLATORS %1$s is a list of names, %2$d the number of further members of a Matrix room
				'%1$s and %2$d others' => $this->l->t('%1$s and %2$d others'),
				// TRANSLATORS Separator before the last name of a list, e.g. "Alice, Bob and Carol"
				' and ' => $this->l->t(' and '),
				default => $text,
			},
		);
		return mb_substr($name, 0, 255);
	}

	/**
	 * @param array<string, MatrixMember> $members
	 * @param array<string, Account> $accounts
	 */
	protected function updateAttendees(Room $room, array $members, array $accounts): void {
		$expected = [];
		foreach ($members as $mxid => $member) {
			if ($member->getMembership() !== Member::JOIN) {
				continue;
			}
			$account = $accounts[$mxid] ?? null;
			if ($account !== null) {
				$expected[Attendee::ACTOR_USERS . '/' . $account->getUserId()] = [Attendee::ACTOR_USERS, $account->getUserId(), $member];
			} else {
				$expected[Attendee::ACTOR_MATRIX . '/' . $mxid] = [Attendee::ACTOR_MATRIX, (string)$mxid, $member];
			}
		}

		foreach ($this->participantService->getParticipantsForRoom($room) as $participant) {
			$attendee = $participant->getAttendee();
			$key = $attendee->getActorType() . '/' . $attendee->getActorId();
			if (isset($expected[$key])) {
				$this->updateDisplayName($attendee, $expected[$key][2]);
				unset($expected[$key]);
			} elseif (in_array($attendee->getActorType(), [Attendee::ACTOR_USERS, Attendee::ACTOR_MATRIX], true)) {
				$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);
			}
		}

		$this->participantService->addUsers($room, array_values(array_map(static fn (array $entry): array => [
			'actorType' => $entry[0],
			'actorId' => $entry[1],
			'displayName' => self::getMemberName($entry[2]),
			'participantType' => Participant::USER,
		], $expected)));
	}

	/**
	 * Users who may not send messages or reactions in the Matrix room lose the permission in the conversation
	 *
	 * @param array<string, Account> $accounts
	 */
	protected function updatePermissions(Room $room, PowerLevels $powerLevels, array $accounts): void {
		$mxids = [];
		foreach ($accounts as $mxid => $account) {
			$mxids[$account->getUserId()] = (string)$mxid;
		}

		foreach ($this->participantService->getParticipantsForRoom($room) as $participant) {
			$attendee = $participant->getAttendee();
			if ($attendee->getActorType() !== Attendee::ACTOR_USERS || !isset($mxids[$attendee->getActorId()])) {
				continue;
			}

			$mxid = $mxids[$attendee->getActorId()];
			$permissions = Attendee::PERMISSIONS_CUSTOM;
			if ($powerLevels->canSendEvent($mxid, 'm.room.message')) {
				$permissions |= Attendee::PERMISSIONS_CHAT;
			}
			if ($powerLevels->canSendEvent($mxid, 'm.reaction')) {
				$permissions |= Attendee::PERMISSIONS_REACT;
			}
			if ($permissions === self::DEFAULT_PERMISSIONS) {
				$permissions = Attendee::PERMISSIONS_DEFAULT;
			}

			if ($attendee->getPermissions() !== $permissions) {
				$this->participantService->updatePermissions($room, $participant, Attendee::PERMISSIONS_MODIFY_SET, $permissions);
			}
		}
	}

	protected function updateDisplayName(Attendee $attendee, MatrixMember $member): void {
		$name = self::getMemberName($member);
		if ($attendee->getActorType() === Attendee::ACTOR_MATRIX && $attendee->getDisplayName() !== $name) {
			$this->participantService->updateDisplayNameForActor(Attendee::ACTOR_MATRIX, $attendee->getActorId(), $name);
		}
	}

	protected static function getMemberName(MatrixMember $member): string {
		$name = trim((string)$member->getDisplayName());
		return $name !== '' ? $name : $member->getMxid();
	}

	protected function findMatrixRoom(string $matrixRoomId): ?MatrixRoom {
		try {
			return $this->roomMapper->getByMatrixRoomId($matrixRoomId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	protected function findTalkRoom(MatrixRoom $matrixRoom): ?Room {
		if ($matrixRoom->getRoomId() === 0) {
			return null;
		}
		try {
			return $this->manager->getRoomById($matrixRoom->getRoomId());
		} catch (RoomNotFoundException) {
			return null;
		}
	}
}
