<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use Nextcloud\Matrix\Exception\MatrixException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\MatrixMemberMapper;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Sync\RoomStateApplier;
use OCA\Talk\Matrix\Sync\SyncService;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUser;

/**
 * Membership changes initiated from Talk (accept/reject invite, leave) and
 * the clean-up when an account is unlinked or a user is deleted.
 */
class LifecycleService {
	public function __construct(
		private readonly AccountService $accountService,
		private readonly InvitationService $invitations,
		private readonly SyncService $syncService,
		private readonly RoomStateApplier $stateApplier,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly MatrixMemberMapper $memberMapper,
		private readonly Manager $manager,
		private readonly \OCA\Talk\Matrix\ClientFactory $clientFactory,
	) {
	}

	/**
	 * @throws \InvalidArgumentException 'account' | 'invitation'
	 * @throws MatrixException
	 */
	public function acceptInvitation(IUser $user, Invitation $invitation): Participant {
		$account = $this->requireAccount($user);
		if ($invitation->getUserId() !== $user->getUID()) {
			throw new \InvalidArgumentException('invitation');
		}
		$this->invitations->accept($user, $account, $invitation);
		// Pull the joined room in right away so the client gets a complete conversation back
		$this->syncService->syncAccount($account, 8, 0);

		try {
			$matrixRoom = $this->roomMapper->getByMatrixRoomId($invitation->getRemoteToken());
			$room = $this->manager->getRoomById($matrixRoom->getRoomId());
		} catch (DoesNotExistException|RoomNotFoundException) {
			throw new \InvalidArgumentException('invitation');
		}
		$participant = $this->stateApplier->getParticipantForAccount($room, $account);
		if ($participant === null) {
			throw new \InvalidArgumentException('invitation');
		}
		return $participant;
	}

	/**
	 * @throws \InvalidArgumentException 'account' | 'invitation'
	 * @throws MatrixException
	 */
	public function rejectInvitation(IUser $user, Invitation $invitation): void {
		$account = $this->requireAccount($user);
		if ($invitation->getUserId() !== $user->getUID()) {
			throw new \InvalidArgumentException('invitation');
		}
		$this->invitations->reject($account, $invitation);
	}

	/**
	 * Unlink: log the device out, drop the account and remove the user from all
	 * Matrix conversations; conversations without any remaining linked member
	 * are deleted.
	 */
	public function unlink(Account $account): void {
		$memberships = $this->memberMapper->getForAccount($account->getId());
		$this->accountService->unlink($account);
		foreach ($memberships as $membership) {
			try {
				$matrixRoom = $this->roomMapper->getByMatrixRoomId($membership->getMatrixRoomId());
			} catch (DoesNotExistException) {
				continue;
			}
			$room = $this->stateApplier->findTalkRoom($matrixRoom);
			if ($room !== null) {
				$participant = $this->stateApplier->getParticipantForAccount($room, $account);
				if ($participant !== null) {
					$this->stateApplier->removeParticipant($room, $participant, 'leave');
				}
			}
			if (!$this->stateApplier->hasLinkedMembers($matrixRoom)) {
				$this->stateApplier->deleteConversation($matrixRoom);
			}
		}
	}

	/**
	 * Create a Matrix room from Talk and return the (synced) conversation.
	 *
	 * @param list<string> $inviteMxids
	 * @param list<string> $inviteUserIds Nextcloud users (must have linked accounts)
	 * @throws \InvalidArgumentException 'account' | 'name' | 'no-matrix-account:<uid>' | 'e2ee-disabled'
	 * @throws MatrixException
	 */
	public function createRoom(IUser $user, string $name, string $topic, bool $encrypted, bool $public, array $inviteMxids, array $inviteUserIds, bool $isDirect = false): Room {
		$account = $this->requireAccount($user);
		$homeserver = $this->clientFactory->getHomeserver($account->getHomeserverId());
		if ($encrypted && !$homeserver->getAllowE2ee()) {
			throw new \InvalidArgumentException('e2ee-disabled');
		}
		$name = trim($name);
		if (!$isDirect && $name === '') {
			throw new \InvalidArgumentException('name');
		}
		$invite = [];
		foreach ($inviteMxids as $mxid) {
			$mxid = \Nextcloud\Matrix\Util\Identifier::normalizeUserId($mxid, $homeserver->getServerName());
			if ($mxid !== $account->getMxid()) {
				$invite[] = $mxid;
			}
		}
		foreach ($inviteUserIds as $userId) {
			$other = $this->accountService->getForUser($userId);
			if ($other === null) {
				throw new \InvalidArgumentException('no-matrix-account:' . $userId);
			}
			if ($other->getMxid() !== $account->getMxid()) {
				$invite[] = $other->getMxid();
			}
		}
		$invite = array_values(array_unique($invite));

		$options = [
			'preset' => $public ? 'public_chat' : ($isDirect ? 'trusted_private_chat' : 'private_chat'),
			'visibility' => $public ? 'public' : 'private',
			'invite' => $invite,
			'is_direct' => $isDirect,
		];
		if ($name !== '') {
			$options['name'] = $name;
		}
		if (trim($topic) !== '') {
			$options['topic'] = trim($topic);
		}
		if ($encrypted) {
			$options['initial_state'] = [['type' => 'm.room.encryption', 'state_key' => '', 'content' => ['algorithm' => 'm.megolm.v1.aes-sha2']]];
		}
		$client = $this->clientFactory->forAccount($account, 30);
		$roomId = $client->createRoom($options);
		if ($isDirect && $invite !== []) {
			try {
				$client->setDirectRoom($account->getMxid(), $invite[0], $roomId);
			} catch (MatrixException) {
				// m.direct is cosmetic
			}
		}
		return $this->syncAndFind($account, $roomId);
	}

	/**
	 * Join a room by id, alias, matrix.to link or matrix: URI (knocks when the room requires it).
	 * @throws \InvalidArgumentException 'account' | 'reference'
	 * @throws MatrixException
	 */
	public function join(IUser $user, string $reference): Room {
		$account = $this->requireAccount($user);
		try {
			$parsed = \Nextcloud\Matrix\Util\Identifier::parseRoomReference($reference);
		} catch (\InvalidArgumentException) {
			throw new \InvalidArgumentException('reference');
		}
		if ($parsed['type'] === \Nextcloud\Matrix\Util\Identifier::TYPE_USER) {
			return $this->createRoom($user, '', '', true, false, [$parsed['id']], [], true);
		}
		$client = $this->clientFactory->forAccount($account, 30);
		try {
			$roomId = $client->join($parsed['id'], $parsed['via']);
		} catch (\Nextcloud\Matrix\Exception\ForbiddenException $e) {
			// Knock-only rooms answer 403 to /join; knock instead and report it
			try {
				$client->knock($parsed['id'], $parsed['via']);
			} catch (MatrixException) {
				throw $e;
			}
			throw new \InvalidArgumentException('knocked');
		}
		return $this->syncAndFind($account, $roomId);
	}

	/**
	 * Browse the homeserver's public room directory.
	 * @return array{chunk: list<array<string, mixed>>, next_batch: ?string, total: ?int}
	 * @throws \InvalidArgumentException 'account'
	 * @throws MatrixException
	 */
	public function publicRooms(IUser $user, string $search, ?string $since, ?string $server = null): array {
		$account = $this->requireAccount($user);
		$result = $this->clientFactory->forAccount($account, 20)->getPublicRooms($server, $since, 30, $search !== '' ? $search : null);
		$joined = [];
		foreach ($this->memberMapper->getForAccount($account->getId()) as $member) {
			if ($member->getMembership() === 'join') {
				$joined[$member->getMatrixRoomId()] = true;
			}
		}
		$chunk = [];
		foreach ((is_array($result['chunk'] ?? null) ? $result['chunk'] : []) as $room) {
			if (!is_array($room)) {
				continue;
			}
			$chunk[] = [
				'roomId' => (string)($room['room_id'] ?? ''),
				'name' => (string)($room['name'] ?? $room['canonical_alias'] ?? $room['room_id'] ?? ''),
				'alias' => isset($room['canonical_alias']) ? (string)$room['canonical_alias'] : null,
				'topic' => isset($room['topic']) ? (string)$room['topic'] : null,
				'members' => (int)($room['num_joined_members'] ?? 0),
				'joined' => isset($joined[(string)($room['room_id'] ?? '')]),
			];
		}
		return ['chunk' => $chunk, 'next_batch' => isset($result['next_batch']) ? (string)$result['next_batch'] : null, 'total' => isset($result['total_room_count_estimate']) ? (int)$result['total_room_count_estimate'] : null];
	}

	/**
	 * Sync until the room appears locally (it usually does with the first batch after a join).
	 * @throws \InvalidArgumentException 'room'
	 */
	private function syncAndFind(Account $account, string $matrixRoomId): Room {
		for ($attempt = 0; $attempt < 3; $attempt++) {
			$this->syncService->syncAccount($account, 8, 0);
			try {
				$matrixRoom = $this->roomMapper->getByMatrixRoomId($matrixRoomId);
				$room = $this->manager->getRoomById($matrixRoom->getRoomId());
				if ($this->stateApplier->getParticipantForAccount($room, $account) !== null) {
					return $room;
				}
			} catch (DoesNotExistException|RoomNotFoundException) {
			}
			usleep(500000);
		}
		throw new \InvalidArgumentException('room');
	}

	public function onUserDeleted(string $userId): void {
		$account = $this->accountService->getForUser($userId);
		if ($account !== null) {
			$this->unlink($account);
		}
	}

	/**
	 * @throws \InvalidArgumentException 'account'
	 */
	private function requireAccount(IUser $user): Account {
		$account = $this->accountService->getForUser($user->getUID());
		if ($account === null || !$account->isActive()) {
			throw new \InvalidArgumentException('account');
		}
		return $account;
	}
}
