<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Consent;
use OCA\Talk\Model\ConsentMapper;
use OCA\Talk\Room;
use OCP\AppFramework\Utility\ITimeFactory;

class ConsentService {
	public function __construct(
		protected ITimeFactory $timeFactory,
		protected ConsentMapper $consentMapper,
	) {
	}

	public function storeConsent(Room $room, string $actorType, string $actorId): Consent {
		$consent = new Consent();
		$consent->setToken($room->getToken());
		$consent->setActorType($actorType);
		$consent->setActorId($actorId);
		$consent->setDateTime($this->timeFactory->getDateTime());
		$this->consentMapper->insert($consent);

		return $consent;
	}

	/**
	 * @return Consent[]
	 */
	public function getConsentForRoom(Room $room): array {
		return $this->consentMapper->findForToken($room->getToken());
	}

	/**
	 * @param Attendee::ACTOR_* $actorType
	 * @return Consent[]
	 */
	public function getConsentForActor(string $actorType, string $actorId): array {
		return $this->consentMapper->findForActor($actorType, $actorId);
	}

	/**
	 * @param Attendee::ACTOR_* $actorType
	 * @return Consent[]
	 */
	public function getConsentForRoomByActor(Room $room, string $actorType, string $actorId): array {
		return $this->consentMapper->findForTokenByActor($room->getToken(), $actorType, $actorId);
	}

	public function deleteByActor(string $actorType, string $actorId): void {
		$this->consentMapper->deleteByActor($actorType, $actorId);
	}

	public function deleteByRoom(Room $room): void {
		$this->consentMapper->deleteByToken($room->getToken());
	}
}
