<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Sync;

use Nextcloud\Matrix\Model\Event;
use Nextcloud\Matrix\Model\Member;
use Nextcloud\Matrix\Model\RoomState;
use Nextcloud\Matrix\Room\NameCalculator;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\Homeserver;
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
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Turns Matrix room state into Talk state: conversation metadata, attendees
 * (linked users as `users`, others as `matrix`), participant types from power
 * levels and the system messages that describe the changes.
 */
class RoomStateApplier {
	public const SYSTEM_ACTOR = Attendee::ACTOR_ID_SYSTEM;

	private ?Homeserver $currentHomeserver = null;
	private bool $e2eeAllowed = true;

	public function __construct(
		private readonly Manager $manager,
		private readonly RoomService $roomService,
		private readonly ParticipantService $participantService,
		private readonly ChatManager $chatManager,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly MatrixMemberMapper $memberMapper,
		private readonly AccountMapper $accountMapper,
		private readonly CapabilityResolver $capabilities,
		private readonly IUserManager $userManager,
		private readonly \OCA\Talk\Service\AvatarService $avatarService,
		private readonly \OCA\Talk\Matrix\ClientFactory $clientFactory,
		private readonly ITimeFactory $timeFactory,
		private readonly IL10N $l,
		private readonly LoggerInterface $logger,
	) {
	}

	public function findMatrixRoom(string $matrixRoomId): ?MatrixRoom {
		try {
			return $this->roomMapper->getByMatrixRoomId($matrixRoomId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findTalkRoom(MatrixRoom $matrixRoom): ?Room {
		try {
			return $this->manager->getRoomById($matrixRoom->getRoomId());
		} catch (RoomNotFoundException) {
			return null;
		}
	}

	/**
	 * Create or update the Talk conversation for a joined Matrix room.
	 *
	 * @param RoomState $state complete current state (existing DB state + this batch's events applied)
	 * @param list<Event> $newStateEvents the state events of this batch, for system messages
	 * @param list<string> $heroes
	 * @return array{0: MatrixRoom, 1: Room, 2: bool} matrix room, talk room, whether the talk room was newly created
	 */
	public function apply(Account $account, Homeserver $homeserver, RoomState $state, array $newStateEvents, array $heroes, ?int $joinedCount, ?int $invitedCount, bool $isDirect): array {
		$this->currentHomeserver = $homeserver;
		$matrixRoom = $this->findMatrixRoom($state->roomId);
		$room = $matrixRoom !== null ? $this->findTalkRoom($matrixRoom) : null;
		$created = false;

		$name = $this->roomName($state, $account->getMxid(), $heroes, $joinedCount, $invitedCount);

		if ($room === null) {
			$room = $this->roomService->createConversation(
				Room::TYPE_GROUP,
				$name,
				null,
				Room::OBJECT_TYPE_MATRIX,
				$state->roomId,
			);
			$this->roomService->setDefaultPermissions($room, $this->capabilities->roomDefaultPermissions());
			if ($state->topic !== null) {
				$this->roomService->setDescription($room, mb_substr($state->topic, 0, Room::DESCRIPTION_MAXIMUM_LENGTH));
			}
			$created = true;
			if ($matrixRoom === null) {
				$matrixRoom = new MatrixRoom();
				$matrixRoom->setMatrixRoomId($state->roomId);
			}
			$matrixRoom->setRoomId($room->getId());
		}

		$isNewMatrixRoom = $matrixRoom->getId() === null;
		$this->syncRoomMetadata($room, $matrixRoom, $state, $name, $isDirect, $created);
		$this->fillMatrixRoom($matrixRoom, $state, $homeserver, $isDirect);
		$matrixRoom = $isNewMatrixRoom ? $this->roomMapper->insert($matrixRoom) : $this->roomMapper->update($matrixRoom);

		$this->syncMembers($room, $matrixRoom, $state, $created ? [] : $newStateEvents, $created);
		$this->syncAvatar($account, $room, $matrixRoom, $state);

		return [$matrixRoom, $room, $created];
	}

	/**
	 * Mirror the room avatar (or, for direct chats without one, the peer's avatar)
	 * into Talk's conversation avatar. The mxc URI is remembered in the
	 * capabilities JSON so the picture is only fetched when it changes.
	 */
	private function syncAvatar(Account $account, Room $room, MatrixRoom $matrixRoom, RoomState $state): void {
		$wanted = $state->avatarUrl;
		if ($wanted === null && $matrixRoom->getIsDirect()) {
			foreach ($state->getJoinedMembers() + $state->getInvitedMembers() as $member) {
				if ($member->userId !== $account->getMxid() && $member->avatarUrl !== null) {
					$wanted = $member->avatarUrl;
					break;
				}
			}
		}
		$capabilities = $matrixRoom->getCapabilitiesArray();
		$current = $capabilities['avatarMxc'] ?? null;
		if ($wanted === $current) {
			return;
		}
		try {
			if ($wanted === null) {
				$this->avatarService->deleteAvatar($room);
			} else {
				$client = $this->clientFactory->forAccount($account, 20);
				try {
					$response = $client->downloadThumbnail($wanted, 512, 512, 'crop');
				} catch (\Nextcloud\Matrix\Exception\MatrixException) {
					$response = $client->downloadMedia($wanted);
				}
				$image = new \OCP\Image();
				if (!$image->loadFromData((string)$response->getBody()) || !$image->valid()) {
					throw new \RuntimeException('not an image');
				}
				$this->avatarService->setAvatar($room, $image);
			}
			$capabilities['avatarMxc'] = $wanted;
			$matrixRoom->setCapabilitiesArray($capabilities);
			$this->roomMapper->update($matrixRoom);
		} catch (\Throwable $e) {
			$this->logger->info('Matrix avatar for ' . $matrixRoom->getMatrixRoomId() . ' not applied: ' . $e->getMessage());
		}
	}

	/**
	 * Name per the Matrix algorithm, translated fixed strings.
	 * @param list<string> $heroes
	 */
	public function roomName(RoomState $state, string $ownMxid, array $heroes, ?int $joinedCount, ?int $invitedCount): string {
		$name = NameCalculator::calculate($state, $ownMxid, $heroes, $joinedCount, $invitedCount, fn (string $s): string => match ($s) {
			'Empty room' => $this->l->t('Empty room'),
			'Empty room (was %s)' => $this->l->t('Empty room (was %s)'),
			'%1$s and %2$d others' => $this->l->t('%1$s and %2$d others'),
			' and ' => $this->l->t(' and '),
			default => $s,
		});
		return mb_substr($name, 0, 255);
	}

	private function syncRoomMetadata(Room $room, MatrixRoom $matrixRoom, RoomState $state, string $name, bool $isDirect, bool $created): void {
		if ($room->getName() !== $name) {
			$this->roomService->setName($room, $name, $created ? null : $room->getName());
		}
		$topic = $state->topic === null ? '' : mb_substr($state->topic, 0, Room::DESCRIPTION_MAXIMUM_LENGTH);
		if (!$created && $room->getDescription() !== $topic) {
			$this->roomService->setDescription($room, $topic);
		}
		if ($room->getDefaultPermissions() !== $this->capabilities->roomDefaultPermissions()) {
			$this->roomService->setDefaultPermissions($room, $this->capabilities->roomDefaultPermissions());
		}
		// An upgraded (tombstoned) room becomes read-only
		if ($state->isUpgraded() && $room->getReadOnly() !== Room::READ_ONLY) {
			$this->roomService->setReadOnly($room, Room::READ_ONLY);
			if ($matrixRoom->getTombstoneTarget() !== $state->tombstoneReplacement) {
				$this->systemMessage($room, 'matrix_room_upgraded', ['replacement' => $state->tombstoneReplacement], $this->l->t('This Matrix room was upgraded. Continue in the new room.'));
			}
		}
	}

	private function fillMatrixRoom(MatrixRoom $matrixRoom, RoomState $state, Homeserver $homeserver, bool $isDirect): void {
		if ($state->isEncrypted() && !$matrixRoom->getEncrypted() && $matrixRoom->getId() !== null) {
			$room = $this->findTalkRoom($matrixRoom);
			if ($room !== null) {
				$this->systemMessage($room, 'matrix_encryption_enabled', [], $this->l->t('End-to-end encryption was enabled for this Matrix room.'));
			}
		}
		$matrixRoom->setRoomVersion($state->roomVersion);
		$matrixRoom->setEncrypted($state->isEncrypted());
		$matrixRoom->setEncryptionAlgo($state->encryptionAlgorithm);
		$matrixRoom->setRotationPeriodMs($state->rotationPeriodMs);
		$matrixRoom->setRotationPeriodMsgs($state->rotationPeriodMsgs);
		$matrixRoom->setJoinRule($state->joinRule);
		$matrixRoom->setIsDirect($isDirect || $matrixRoom->getIsDirect());
		$matrixRoom->setCanonicalAlias($state->canonicalAlias);
		$matrixRoom->setPowerLevelsArray($state->getPowerLevels()->toArray());
		$matrixRoom->setCreator($state->creator);
		$matrixRoom->setTombstoneTarget($state->tombstoneReplacement);
		$capabilities = $this->capabilities->forRoom($state, $homeserver, $matrixRoom->getIsDirect());
		$capabilities['avatarMxc'] = $matrixRoom->getCapabilitiesArray()['avatarMxc'] ?? null;
		$matrixRoom->setCapabilitiesArray($capabilities);
		$matrixRoom->setStateUpdated($this->timeFactory->getDateTime());
	}

	/**
	 * Reconcile talk_matrix_members + Talk attendees with the Matrix member list.
	 * @param list<Event> $newStateEvents
	 */
	private function syncMembers(Room $room, MatrixRoom $matrixRoom, RoomState $state, array $newStateEvents, bool $created): void {
		$known = $this->memberMapper->getForRoom($matrixRoom->getMatrixRoomId());
		$members = $state->getMembers();
		$accounts = $this->accountMapper->getByMxids(array_keys($members));
		$powerLevels = $state->getPowerLevels();
		$now = $this->timeFactory->getDateTime();

		$this->e2eeAllowed = $this->currentHomeserver?->getAllowE2ee() ?? true;
		$attendees = [];
		foreach ($this->participantService->getParticipantsForRoom($room) as $participant) {
			$attendee = $participant->getAttendee();
			$attendees[$attendee->getActorType() . '/' . $attendee->getActorId()] = $participant;
		}

		$toAdd = [];
		foreach ($members as $mxid => $member) {
			$account = $accounts[$mxid] ?? null;
			$row = $known[$mxid] ?? null;
			$isNew = $row === null;
			if ($isNew) {
				$row = new MatrixMember();
				$row->setMatrixRoomId($matrixRoom->getMatrixRoomId());
				$row->setMxid($mxid);
			}
			$row->setMembership($member->membership);
			$row->setDisplayName($member->displayName !== null ? mb_substr($member->displayName, 0, 255) : null);
			$row->setAvatarUrl($member->avatarUrl);
			$row->setPowerLevel($powerLevels->getUserLevel($mxid));
			$row->setAccountId($account?->getId());
			$row->setUpdatedAt($now);

			[$actorType, $actorId] = $this->actorFor($mxid, $account);
			$key = $actorType . '/' . $actorId;
			$participant = $attendees[$key] ?? null;

			if ($member->isJoined()) {
				if ($participant === null) {
					$toAdd[] = [
						'actorType' => $actorType,
						'actorId' => $actorId,
						'displayName' => $member->getName(),
						'participantType' => $this->capabilities->participantType($powerLevels, $mxid, $state->creator),
					];
				} else {
					$this->updateParticipant($room, $participant, $powerLevels, $mxid, $state, $member);
				}
			} elseif ($participant !== null) {
				// Left, kicked or banned on Matrix → remove attendee (system message below)
				$this->participantService->removeAttendee($room, $participant, $member->membership === Member::LEAVE && $member->sender === $mxid ? AAttendeeRemovedEvent::REASON_LEFT : AAttendeeRemovedEvent::REASON_REMOVED);
				$row->setAttendeeId(null);
			}

			if ($isNew) {
				$this->memberMapper->insert($row);
			} else {
				$this->memberMapper->update($row);
			}
		}

		if ($toAdd !== []) {
			$this->participantService->addUsers($room, $toAdd);
			// Permissions + attendee ids after insert
			foreach ($this->participantService->getParticipantsForRoom($room) as $participant) {
				$attendee = $participant->getAttendee();
				$mxid = $this->mxidForAttendee($attendee, $accounts);
				if ($mxid === null || !isset($members[$mxid])) {
					continue;
				}
				$row = $this->memberMapper->get($matrixRoom->getMatrixRoomId(), $mxid);
				if ($row->getAttendeeId() !== $attendee->getId()) {
					$row->setAttendeeId($attendee->getId());
					$this->memberMapper->update($row);
				}
				$this->applyPermissions($room, $participant, $powerLevels, $mxid, $members[$mxid]->membership, $state->isEncrypted());
			}
		}

		if (!$created) {
			$this->systemMessagesForState($room, $newStateEvents, $accounts);
		}
	}

	private function updateParticipant(Room $room, Participant $participant, \Nextcloud\Matrix\Model\PowerLevels $powerLevels, string $mxid, RoomState $state, Member $member): void {
		$attendee = $participant->getAttendee();
		$type = $this->capabilities->participantType($powerLevels, $mxid, $state->creator);
		if ($attendee->getParticipantType() !== $type) {
			$this->participantService->updateParticipantType($room, $participant, $type);
		}
		if ($attendee->getActorType() === Attendee::ACTOR_MATRIX && $attendee->getDisplayName() !== $member->getName()) {
			$this->participantService->updateDisplayNameForActor(Attendee::ACTOR_MATRIX, $mxid, $member->getName());
		}
		$this->applyPermissions($room, $participant, $powerLevels, $mxid, $member->membership, $state->isEncrypted());
	}

	private function applyPermissions(Room $room, Participant $participant, \Nextcloud\Matrix\Model\PowerLevels $powerLevels, string $mxid, string $membership, bool $encrypted): void {
		if ($participant->hasModeratorPermissions(false)) {
			// Talk does not allow custom permissions for moderators (they always have all of them);
			// call endpoints are guarded separately for Matrix conversations
			return;
		}
		$wanted = $this->capabilities->attendeePermissions($powerLevels, $mxid, $membership, $encrypted, $this->e2eeAllowed);
		if ($participant->getAttendee()->getPermissions() !== $wanted) {
			$this->participantService->updatePermissions($room, $participant, Attendee::PERMISSIONS_MODIFY_SET, $wanted);
		}
	}

	/**
	 * @return array{0: string, 1: string} actor type + id
	 */
	public function actorFor(string $mxid, ?Account $account): array {
		if ($account !== null && $this->userManager->userExists($account->getUserId())) {
			return [Attendee::ACTOR_USERS, $account->getUserId()];
		}
		return [Attendee::ACTOR_MATRIX, $mxid];
	}

	/** @param array<string, Account> $accountsByMxid */
	private function mxidForAttendee(Attendee $attendee, array $accountsByMxid): ?string {
		if ($attendee->getActorType() === Attendee::ACTOR_MATRIX) {
			return $attendee->getActorId();
		}
		if ($attendee->getActorType() === Attendee::ACTOR_USERS) {
			foreach ($accountsByMxid as $mxid => $account) {
				if ($account->getUserId() === $attendee->getActorId()) {
					return $mxid;
				}
			}
			try {
				return $this->accountMapper->getByUserId($attendee->getActorId())->getMxid();
			} catch (DoesNotExistException) {
				return null;
			}
		}
		return null;
	}

	/**
	 * Membership / name / topic / avatar changes as system messages. The
	 * `users` actor add/remove messages are produced by Talk itself through
	 * the attendee events, so only Matrix-only members are handled here.
	 *
	 * @param list<Event> $events
	 * @param array<string, Account> $accounts
	 */
	private function systemMessagesForState(Room $room, array $events, array $accounts): void {
		foreach ($events as $event) {
			if ($event->type !== 'm.room.member') {
				continue;
			}
			$target = (string)$event->stateKey;
			if (isset($accounts[$target])) {
				continue; // Talk emits user_added / user_removed for linked users
			}
			$membership = (string)($event->content['membership'] ?? '');
			$previous = (string)($event->unsigned['prev_content']['membership'] ?? Member::LEAVE);
			$name = (string)($event->content['displayname'] ?? $event->unsigned['prev_content']['displayname'] ?? $target);
			if ($membership === Member::JOIN && $previous !== Member::JOIN) {
				$this->systemMessage($room, 'matrix_user_added', ['matrix_user' => $target, 'name' => $name], $this->l->t('%s joined from Matrix', [$name]), $event);
			} elseif ($membership !== Member::JOIN && $previous === Member::JOIN) {
				$this->systemMessage($room, 'matrix_user_removed', ['matrix_user' => $target, 'name' => $name], $this->l->t('%s left the Matrix room', [$name]), $event);
			}
		}
	}

	private function systemMessage(Room $room, string $message, array $parameters, string $fallback, ?Event $event = null): void {
		try {
			$this->chatManager->addSystemMessage(
				$room,
				null,
				Attendee::ACTOR_GUESTS,
				self::SYSTEM_ACTOR,
				json_encode(['message' => $message, 'parameters' => $parameters], JSON_THROW_ON_ERROR),
				$event !== null ? $this->timeFactory->getDateTime('@' . intdiv($event->originServerTs, 1000)) : $this->timeFactory->getDateTime(),
				false,
				$event?->eventId !== null ? substr($event->eventId, 0, 64) : null,
				null,
				true,
				true,
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Could not add Matrix system message ' . $message . ': ' . $e->getMessage());
		}
	}

	/**
	 * Remove a Talk conversation that no longer has any linked user in it.
	 */
	public function deleteConversation(MatrixRoom $matrixRoom): void {
		$room = $this->findTalkRoom($matrixRoom);
		$this->memberMapper->deleteForRoom($matrixRoom->getMatrixRoomId());
		$this->roomMapper->delete($matrixRoom);
		if ($room !== null) {
			$this->roomService->deleteRoom($room);
		}
	}

	/**
	 * Whether any linked, existing Nextcloud user still is a joined member.
	 */
	public function hasLinkedMembers(MatrixRoom $matrixRoom, ?int $exceptAccountId = null): bool {
		foreach ($this->memberMapper->getForRoom($matrixRoom->getMatrixRoomId()) as $member) {
			if ($member->getAccountId() !== null && $member->getAccountId() !== $exceptAccountId && $member->getMembership() === Member::JOIN) {
				return true;
			}
		}
		return false;
	}

	public function updateMatrixRoom(MatrixRoom $matrixRoom): MatrixRoom {
		return $this->roomMapper->update($matrixRoom);
	}

	/**
	 * Seed a RoomState with what we already know from the database so that an
	 * incremental, lazy-loaded sync (which only carries the changed state)
	 * still yields the full picture.
	 */
	public function seedState(RoomState $state, MatrixRoom $matrixRoom): void {
		$now = 0;
		$seed = static fn (string $type, array $content, string $stateKey = ''): Event => new Event('$seed:' . $type . ':' . $stateKey, $type, $matrixRoom->getCreator(), $now, $content, $stateKey, [], $matrixRoom->getMatrixRoomId());
		$create = ['creator' => $matrixRoom->getCreator(), 'room_version' => $matrixRoom->getRoomVersion()];
		$state->apply($seed('m.room.create', $create));
		$room = $this->findTalkRoom($matrixRoom);
		if ($room !== null) {
			$state->apply($seed('m.room.name', ['name' => $room->getName()]));
			if ($room->getDescription() !== '') {
				$state->apply($seed('m.room.topic', ['topic' => $room->getDescription()]));
			}
		}
		if ($matrixRoom->getCanonicalAlias() !== null) {
			$state->apply($seed('m.room.canonical_alias', ['alias' => $matrixRoom->getCanonicalAlias()]));
		}
		$state->apply($seed('m.room.join_rules', ['join_rule' => $matrixRoom->getJoinRule()]));
		if ($matrixRoom->getEncrypted()) {
			$state->apply($seed('m.room.encryption', array_filter([
				'algorithm' => $matrixRoom->getEncryptionAlgo() ?? 'm.megolm.v1.aes-sha2',
				'rotation_period_ms' => $matrixRoom->getRotationPeriodMs(),
				'rotation_period_msgs' => $matrixRoom->getRotationPeriodMsgs(),
			], static fn ($v) => $v !== null)));
		}
		if ($matrixRoom->getTombstoneTarget() !== null) {
			$state->apply($seed('m.room.tombstone', ['replacement_room' => $matrixRoom->getTombstoneTarget()]));
		}
		$powerLevels = $matrixRoom->getPowerLevelsArray();
		if ($powerLevels !== []) {
			$state->apply($seed('m.room.power_levels', $powerLevels));
		}
		foreach ($this->memberMapper->getForRoom($matrixRoom->getMatrixRoomId()) as $member) {
			$state->apply($seed('m.room.member', array_filter([
				'membership' => $member->getMembership(),
				'displayname' => $member->getDisplayName(),
				'avatar_url' => $member->getAvatarUrl(),
			], static fn ($v) => $v !== null), $member->getMxid()));
		}
	}

	/**
	 * Our own account left / was kicked or banned (seen via rooms.leave).
	 */
	public function removeParticipant(Room $room, Participant $participant, string $membership): void {
		$reason = $membership === Member::LEAVE ? AAttendeeRemovedEvent::REASON_LEFT : AAttendeeRemovedEvent::REASON_REMOVED;
		$this->participantService->removeAttendee($room, $participant, $reason);
	}

	/**
	 * A Matrix read receipt: move the corresponding attendee's read marker so
	 * Talk's read status shows who has seen what.
	 */
	public function applyReadReceipt(Room $room, MatrixRoom $matrixRoom, string $mxid, string $eventId, ?Account $account): void {
		$map = \OCP\Server::get(\OCA\Talk\Matrix\Model\EventMapMapper::class)->findByEventId($eventId);
		if ($map === null || $map->getCommentId() === null) {
			return;
		}
		[$actorType, $actorId] = $this->actorFor($mxid, $account);
		try {
			$participant = $this->participantService->getParticipantByActor($room, $actorType, $actorId);
		} catch (ParticipantNotFoundException) {
			return;
		}
		if ($participant->getAttendee()->getLastReadMessage() >= $map->getCommentId()) {
			return;
		}
		$this->participantService->updateLastReadMessage($participant, $map->getCommentId());
	}

	public function getParticipantForAccount(Room $room, Account $account): ?Participant {
		try {
			return $this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $account->getUserId());
		} catch (ParticipantNotFoundException) {
			return null;
		}
	}
}
