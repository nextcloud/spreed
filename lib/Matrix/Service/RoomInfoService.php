<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use Nextcloud\Matrix\Model\Member;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\MatrixMemberMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Sync\CapabilityResolver;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Read-side helpers used while formatting conversations and messages.
 * Caches per request because RoomFormatter is called once per room in a list.
 */
class RoomInfoService {
	/** @var array<int, MatrixRoom|null> */
	private array $rooms = [];
	/** @var array<string, string> userId → mxid */
	private array $mxids = [];

	public function __construct(
		private readonly MatrixRoomMapper $roomMapper,
		private readonly MatrixMemberMapper $memberMapper,
		private readonly AccountMapper $accountMapper,
		private readonly CapabilityResolver $capabilities,
	) {
	}

	public function getMatrixRoom(Room $room): ?MatrixRoom {
		if (!array_key_exists($room->getId(), $this->rooms)) {
			try {
				$this->rooms[$room->getId()] = $this->roomMapper->getByRoomId($room->getId());
			} catch (DoesNotExistException) {
				$this->rooms[$room->getId()] = null;
			}
		}
		return $this->rooms[$room->getId()];
	}

	/**
	 * Room capabilities merged with the viewing participant's power-level part.
	 * @return array<string, mixed>
	 */
	public function capabilitiesFor(Room $room, ?Participant $participant): array {
		$matrixRoom = $this->getMatrixRoom($room);
		if ($matrixRoom === null) {
			return ['matrixRoomId' => $room->getObjectId(), 'calls' => false, 'canSend' => false, 'canSendReason' => 'not-synced'];
		}
		$mxid = $participant !== null ? $this->mxidForAttendee($participant->getAttendee()) : null;
		$membership = Member::LEAVE;
		if ($mxid !== null) {
			try {
				$membership = $this->memberMapper->get($matrixRoom->getMatrixRoomId(), $mxid)->getMembership();
			} catch (DoesNotExistException) {
			}
		}
		$capabilities = $this->capabilities->merge($matrixRoom, $mxid, $membership);
		if ($matrixRoom->getEncrypted() && $participant !== null && $participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
			$capabilities['deviceVerified'] = $this->isDeviceVerified($participant->getAttendee()->getActorId());
		}
		return $capabilities;
	}

	/** @var array<string, bool> */
	private array $verified = [];

	/** Whether the user's Talk device was verified / cross-signed (cached per request). */
	private function isDeviceVerified(string $userId): bool {
		if (!array_key_exists($userId, $this->verified)) {
			$this->verified[$userId] = false;
			try {
				$account = $this->accountMapper->getByUserId($userId);
				$store = \OCP\Server::get(\OCA\Talk\Matrix\Service\CryptoService::class)->store($account);
				$this->verified[$userId] = $store->getSecret('verified_at') !== null || $store->getSecret('trusted_master_key') !== null;
			} catch (\Throwable) {
			}
		}
		return $this->verified[$userId];
	}

	public function mxidForAttendee(Attendee $attendee): ?string {
		if ($attendee->getActorType() === Attendee::ACTOR_MATRIX) {
			return $attendee->getActorId();
		}
		if ($attendee->getActorType() !== Attendee::ACTOR_USERS) {
			return null;
		}
		$userId = $attendee->getActorId();
		if (!array_key_exists($userId, $this->mxids)) {
			try {
				$this->mxids[$userId] = $this->accountMapper->getByUserId($userId)->getMxid();
			} catch (DoesNotExistException) {
				$this->mxids[$userId] = '';
			}
		}
		return $this->mxids[$userId] !== '' ? $this->mxids[$userId] : null;
	}

	/** Display name of a Matrix-only member, falling back to the Matrix id. */
	public function memberName(Room $room, string $mxid): string {
		$matrixRoom = $this->getMatrixRoom($room);
		if ($matrixRoom === null) {
			return $mxid;
		}
		try {
			return $this->memberMapper->get($matrixRoom->getMatrixRoomId(), $mxid)->getName();
		} catch (DoesNotExistException) {
			return $mxid;
		}
	}
}
