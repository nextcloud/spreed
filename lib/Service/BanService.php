<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use DateTime;
use OCA\Talk\Model\Ban;
use OCA\Talk\Model\BanMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class BanService {

	public function __construct(
		protected BanMapper $banMapper,
	) {
	}

	/**
	 * Create a new ban
	 *
	 * @throws \InvalidArgumentException
	 */
	public function createBan(string $actorId, string $actorType, int $roomId, string $bannedId, string $bannedType, DateTime $bannedTime, string $internalNote): Ban {
		if (empty($bannedId) || empty($bannedType)) {
			throw new \InvalidArgumentException('bannedActor');
		}

		if (empty($internalNote)) {
			throw new \InvalidArgumentException('internalNote');
		}

		$ban = new Ban();
		$ban->setActorId($actorId);
		$ban->setActorType($actorType);
		$ban->setRoomId($roomId);
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
