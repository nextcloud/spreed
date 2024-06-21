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
use OCP\AppFramework\Utility\ITimeFactory;

class BanService {
	
	public function __construct(
		protected BanMapper $banMapper,
		protected ITimeFactory $timeFactory,
	) {
	}

	/**
	 * Validate the ban data.
	 */
	private function validateBanData(string $actorId, string $actorType, int $roomId, string $bannedByActorId, string $bannedByActorType, ?DateTime $bannedAt, ?string $reason): void {
		if (empty($actorId) || empty($actorType) || empty($roomId) || empty($bannedByActorId) || empty($bannedByActorType)) {
			throw new \InvalidArgumentException("Invalid ban data provided.");
		}

		if ($bannedAt !== null && !$bannedAt instanceof DateTime) {
			throw new \InvalidArgumentException("Invalid date format for bannedAt.");
		}
	}

	/**
	 * Create a new ban
	 */
	public function createBan(string $actorId, string $actorType, int $roomId, string $bannedByActorId, string $bannedByActorType, ?DateTime $bannedAt, ?string $reason): Ban {
		$this->validateBanData($actorId, $actorType, $roomId, $bannedByActorId, $bannedByActorType, $bannedAt, $reason);

		$ban = new Ban();
		$ban->setActorId($actorId);
		$ban->setActorType($actorType);
		$ban->setRoomId($roomId);
		$ban->setBannedByActorId($bannedByActorId);
		$ban->setBannedByActorType($bannedByActorType);
		$ban->setBannedAt($bannedAt ?? $this->timeFactory->getTime());
		$ban->setReason($reason);

		return $this->banMapper->insert($ban);
	}

	/**
	 * Retrieve a ban for a specific actor and room.
	 */
	public function getBanForActorAndRoom(string $actorId, string $actorType, int $roomId): Ban {
		return $this->banMapper->findForActorAndRoom($actorId, $actorType, $roomId);
	}

	/**
	 * Delete a ban for a specific actor and room.
	 */
	public function deleteBanForActorAndRoom(string $actorId, string $actorType, int $roomId): void {
		$this->banMapper->deleteBanForActorAndRoom($actorId, $actorType, $roomId);
	}

	/**
	 * Find and delete a ban for a specific actor and room.
	 */
	public function findAndDeleteBanForActorAndRoom(string $actorId, string $actorType, int $roomId): void {
		try {
			$ban = $this->getBanForActorAndRoom($actorId, $actorType, $roomId);
			$this->deleteBanForActorAndRoom($actorId, $actorType, $roomId);
		} catch (DoesNotExistException $e) {
			// Ban does not exist
		}
	}

	/**
	 * Retrieve all bans for a specific room.
	 */
	public function getBansForRoom(int $roomId): array {
		return $this->banMapper->findByRoomId($roomId);
	}
}
