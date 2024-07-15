<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use DateTime;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Ban;
use OCA\Talk\Model\BanMapper;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;

class BanService {

	public function __construct(
		protected BanMapper $banMapper,
		protected Manager $manager,
		protected ParticipantService $participantService,
	) {
	}

	/**
	 * Create a new ban
	 *
	 * @throws \InvalidArgumentException
	 */
	public function createBan(Room $room, string $actorId, string $actorType, string $bannedId, string $bannedType, DateTime $bannedTime, string $internalNote): Ban {
		if (empty($bannedId) || empty($bannedType)) {
			throw new \InvalidArgumentException('bannedActor');
		}

		if (strlen($internalNote) > Ban::NOTE_MAX_LENGTH) {
			throw new \InvalidArgumentException('internalNote');
		}

		if ($bannedType === $actorType && $bannedId === $actorId) {
			throw new \InvalidArgumentException('self');
		}

		if (in_array($bannedType, [Attendee::ACTOR_GUESTS, Attendee::ACTOR_USERS, Attendee::ACTOR_FEDERATED_USERS], true)) {
			try {
				$bannedParticipant = $this->participantService->getParticipantByActor($room, $bannedType, $bannedId);
				if ($bannedParticipant->hasModeratorPermissions()) {
					throw new \InvalidArgumentException('moderator');
				}
			} catch (ParticipantNotFoundException) {
				// No failure if the banned actor is not in the room yet/anymore
			}
		}

		$ban = new Ban();
		$ban->setActorId($actorId);
		$ban->setActorType($actorType);
		$ban->setRoomId($room->getId());
		$ban->setBannedId($bannedId);
		$ban->setBannedType($bannedType);
		$ban->setBannedTime($bannedTime);
		$ban->setInternalNote($internalNote);

		return $this->banMapper->insert($ban);
	}

	/**
	 * Retrieve a ban for a specific actor and room.
	 *
	 * @throws DoesNotExistException
	 */
	public function getBanForActorAndRoom(string $actorId, string $actorType, int $roomId): Ban {
		return $this->banMapper->findForActorAndRoom($actorId, $actorType, $roomId);
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
}
