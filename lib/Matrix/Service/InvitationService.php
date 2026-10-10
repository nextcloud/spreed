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
 * Accept or decline invites to Matrix rooms
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
		$this->syncService->syncAccount($account, self::SYNC_BUDGET);

		try {
			$roomId = $this->roomMapper->getById($id)->getRoomId();
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
