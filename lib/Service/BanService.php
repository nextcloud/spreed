<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
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
	private function validateBanData(string $actorId, string $actorType, int $roomId, string $bannedId, string $bannedType, ?DateTime $bannedTime, ?string $internalNote): void {
		if (empty($actorId) || empty($actorType) || empty($roomId) || empty($bannedId) || empty($bannedType)) {
			throw new \InvalidArgumentException("Invalid ban data provided.");
		}

		if ($bannedTime !== null && !$bannedTime instanceof DateTime) {
			throw new \InvalidArgumentException("Invalid date format for bannedTime.");
		}
	}

	/**
	 * Create a new ban
	 */
	public function createBan(string $actorId, string $actorType, int $roomId, string $bannedId, string $bannedType, ?DateTime $bannedTime, ?string $internalNote): Ban {
		$this->validateBanData($actorId, $actorType, $roomId, $bannedId, $bannedType, $bannedTime, $internalNote);

		$ban = new Ban();
		$ban->setActorId($actorId);
		$ban->setActorType($actorType);
		$ban->setRoomId($roomId);
		$ban->setBannedId($bannedId);
		$ban->setBannedType($bannedType);
		$ban->setBannedTime($bannedTime ?? $this->timeFactory->getTime());
		$ban->setInternalNote($internalNote);

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

	/**
	 * Retrieve a ban by its ID and delete it.
	 */
	public function findAndDeleteBanById(int $banId): void {
		try {
			$ban = $this->banMapper->findByBanId($banId);
			$this->banMapper->delete($ban);
		} catch (DoesNotExistException $e) {
			// Ban does not exist
		}
	}
}
