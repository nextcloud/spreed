<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use DateTime;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Ban;
use OCA\Talk\Model\BanMapper;
use OCA\Talk\Room;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class BanService {

	public function __construct(
		protected BanMapper $banMapper,
		protected Manager $manager,
		protected ParticipantService $participantService,
		protected IUserManager $userManager,
		protected TalkSession $talkSession,
		protected IRequest $request,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * Create a new ban
	 *
	 * @throws \InvalidArgumentException
	 */
	public function createBan(Room $room, string $moderatorActorType, string $moderatorActorId, string $moderatorDisplayname, string $bannedActorType, string $bannedActorId, DateTime $bannedTime, string $internalNote): Ban {
		if (!in_array($bannedActorType, ['users', 'guests', 'ip'], true)) {
			throw new \InvalidArgumentException('bannedActor');
		}

		if (empty($bannedActorId)) {
			throw new \InvalidArgumentException('bannedActor');
		}

		// Fix missing IP and range validation

		if (strlen($internalNote) > Ban::NOTE_MAX_LENGTH) {
			throw new \InvalidArgumentException('internalNote');
		}

		if ($bannedActorType === $moderatorActorType && $bannedActorId === $moderatorActorId) {
			throw new \InvalidArgumentException('self');
		}

		/** @var ?string $displayname */
		$displayname = null;
		if (in_array($bannedActorType, [Attendee::ACTOR_GUESTS, Attendee::ACTOR_USERS, Attendee::ACTOR_FEDERATED_USERS], true)) {
			try {
				$bannedParticipant = $this->participantService->getParticipantByActor($room, $bannedActorType, $bannedActorId);
				$displayname = $bannedParticipant->getAttendee()->getDisplayName();
				if ($bannedParticipant->hasModeratorPermissions()) {
					throw new \InvalidArgumentException('moderator');
				}
			} catch (ParticipantNotFoundException) {
				// No failure if the banned actor is not in the room yet/anymore
				if ($bannedActorType === Attendee::ACTOR_USERS) {
					$displayname = $this->userManager->getDisplayName($bannedActorId);
				}
			}
		}

		if ($displayname === null || $displayname === '') {
			$displayname = $bannedActorId;
		}

		$ban = new Ban();
		$ban->setModeratorActorType($moderatorActorType);
		$ban->setModeratorActorId($moderatorActorId);
		$ban->setModeratorDisplayname($moderatorDisplayname);
		$ban->setRoomId($room->getId());
		$ban->setBannedActorType($bannedActorType);
		$ban->setBannedActorId($bannedActorId);
		$ban->setBannedDisplayname($displayname);
		$ban->setBannedTime($bannedTime);
		$ban->setInternalNote($internalNote);

		return $this->banMapper->insert($ban);
	}

	public function copyBanForRemoteAddress(Ban $ban, string $remoteAddress): void {
		$this->logger->info('Banned guest detected, banning IP address: ' . $remoteAddress . ' to prevent rejoining.');

		$newBan = new Ban();
		$newBan->setModeratorActorType($ban->getModeratorActorType());
		$newBan->setModeratorActorId($ban->getModeratorActorId());
		$newBan->setModeratorDisplayname($ban->getModeratorDisplayname());
		$newBan->setRoomId($ban->getRoomId());
		$newBan->setBannedTime($ban->getBannedTime());
		$newBan->setInternalNote($ban->getInternalNote());

		$newBan->setBannedActorType('ip');
		$newBan->setBannedActorId($remoteAddress);

		try {
			$this->banMapper->insert($newBan);
		} catch (Exception $e) {
			if ($e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				return;
			}
			throw $e;
		}
	}

	/**
	 * @throws ForbiddenException
	 */
	public function throwIfActorIsBanned(Room $room, ?string $userId): void {
		if ($userId !== null) {
			$actorType = Attendee::ACTOR_USERS;
			$actorId = $userId;
		} else {
			$actorType = Attendee::ACTOR_GUESTS;
			$actorId = $this->talkSession->getGuestActorIdForRoom($room->getToken());
		}

		if ($actorId !== null) {
			try {
				$ban = $this->banMapper->findForBannedActorAndRoom($actorType, $actorId, $room->getId());
				if ($actorType === Attendee::ACTOR_GUESTS) {
					$this->copyBanForRemoteAddress($ban, $this->request->getRemoteAddress());
				}
				throw new ForbiddenException('actor');
			} catch (DoesNotExistException) {
			}
		}

		if ($actorType !== Attendee::ACTOR_GUESTS) {
			return;
		}

		try {
			$this->banMapper->findForBannedActorAndRoom($this->request->getRemoteAddress(), 'ip', $room->getId());
			throw new ForbiddenException('ip');
		} catch (DoesNotExistException) {
		}
	}

	/**
	 * Retrieve all bans for a specific room.
	 *
	 * @return Ban[]
	 */
	public function getBansForRoom(int $roomId): array {
		return $this->banMapper->findByRoomId($roomId);
	}

	/**
	 * Retrieve a ban by its ID and delete it.
	 */
	public function findAndDeleteBanByIdForRoom(int $banId, int $roomId): void {
		try {
			$ban = $this->banMapper->findByBanIdAndRoom($banId, $roomId);
			$this->banMapper->delete($ban);
		} catch (DoesNotExistException) {
			// Ban does not exist
		}
	}

	public function updateDisplayNameForActor(string $actorType, string $actorId, string $displayName): void {
		$this->banMapper->updateDisplayNameForActor($actorType, $actorId, $displayName);
	}
}
