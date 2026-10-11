<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Model\Member;
use OCA\Talk\Matrix\Client\Util\Identifier;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\MatrixMemberMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Sync\RoomSyncService;
use OCA\Talk\Matrix\Sync\SyncService;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Join Matrix rooms: accept or decline invites, join by address and create rooms
 */
class InvitationService {
	/** Seconds to wait for the room after joining */
	public const SYNC_BUDGET = 10;

	public function __construct(
		private readonly AccountService $accountService,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly MatrixMemberMapper $memberMapper,
		private readonly RoomSyncService $roomSyncService,
		private readonly SyncService $syncService,
		private readonly Manager $manager,
	) {
	}

	/**
	 * Join the Matrix room and sync, so the conversation exists right away
	 *
	 * @param string $id Id of the MatrixRoom entity
	 * @return Room|null The conversation, null when another sync is still creating it
	 * @throws \InvalidArgumentException 'invitation' when there is no pending invite
	 * @throws MatrixException
	 * @throws DoesNotExistException when the homeserver was removed
	 */
	public function accept(Account $account, string $id): ?Room {
		$matrixRoom = $this->getInvitedRoom($account, $id);
		$serverName = Identifier::serverName($matrixRoom->getMatrixRoomId());
		$this->accountService->getClient($account)->join($matrixRoom->getMatrixRoomId(), $serverName !== '' ? [$serverName] : []);
		return $this->syncAndFindRoom($account, $matrixRoom->getMatrixRoomId());
	}

	/**
	 * @param string $reference Room id, alias or link to the room
	 * @return Room|null The conversation, null when another sync is still creating it
	 * @throws \InvalidArgumentException 'reference' when the reference is not a Matrix room
	 * @throws MatrixException
	 * @throws DoesNotExistException when the homeserver was removed
	 */
	public function join(Account $account, string $reference): ?Room {
		try {
			[$roomIdOrAlias, $servers] = Identifier::parseRoomReference($reference);
		} catch (\InvalidArgumentException) {
			throw new \InvalidArgumentException('reference');
		}
		$matrixRoomId = $this->accountService->getClient($account)->join($roomIdOrAlias, $servers);
		return $this->syncAndFindRoom($account, $matrixRoomId);
	}

	/**
	 * Create an unencrypted Matrix room, which is mirrored right away
	 *
	 * @param list<string> $invites Matrix user ids to invite
	 * @param bool $direct Whether it is a direct chat with the only invited user
	 * @return Room|null The conversation, null when another sync is still creating it
	 * @throws \InvalidArgumentException 'name' | 'invite'
	 * @throws MatrixException
	 * @throws DoesNotExistException when the homeserver was removed
	 */
	public function create(Account $account, string $name, string $topic, array $invites, bool $direct): ?Room {
		$name = trim($name);
		if ($direct ? count($invites) !== 1 : $name === '') {
			throw new \InvalidArgumentException($direct ? 'invite' : 'name');
		}
		foreach ($invites as $invite) {
			if (!Identifier::isUserId($invite)) {
				throw new \InvalidArgumentException('invite');
			}
		}

		$options = [
			'preset' => $direct ? 'trusted_private_chat' : 'private_chat',
			'visibility' => 'private',
			'invite' => array_values(array_unique($invites)),
			'is_direct' => $direct,
		];
		if ($name !== '') {
			$options['name'] = mb_substr($name, 0, 255);
		}
		if (trim($topic) !== '') {
			$options['topic'] = trim($topic);
		}

		$matrixRoomId = $this->accountService->getClient($account)->createRoom($options);
		return $this->syncAndFindRoom($account, $matrixRoomId);
	}

	protected function syncAndFindRoom(Account $account, string $matrixRoomId): ?Room {
		$this->syncService->syncAccount($account, self::SYNC_BUDGET);

		try {
			$roomId = $this->roomMapper->getByMatrixRoomId($matrixRoomId)->getRoomId();
			return $roomId !== 0 ? $this->manager->getRoomById($roomId) : null;
		} catch (DoesNotExistException|RoomNotFoundException) {
			return null;
		}
	}

	/**
	 * @param string $id Id of the MatrixRoom entity
	 * @throws \InvalidArgumentException 'invitation' when there is no pending invite
	 * @throws MatrixException
	 * @throws DoesNotExistException when the homeserver was removed
	 */
	public function decline(Account $account, string $id): void {
		$matrixRoom = $this->getInvitedRoom($account, $id);
		$this->accountService->getClient($account)->leave($matrixRoom->getMatrixRoomId());
		$this->roomSyncService->leaveRoom($account, $matrixRoom->getMatrixRoomId());
	}

	/**
	 * @throws \InvalidArgumentException 'invitation'
	 */
	protected function getInvitedRoom(Account $account, string $id): MatrixRoom {
		try {
			$matrixRoom = $this->roomMapper->getById($id);
		} catch (DoesNotExistException) {
			throw new \InvalidArgumentException('invitation');
		}
		$member = $this->memberMapper->getForRoom($id)[$account->getMxid()] ?? null;
		if ($member?->getMembership() !== Member::INVITE) {
			throw new \InvalidArgumentException('invitation');
		}
		return $matrixRoom;
	}
}
