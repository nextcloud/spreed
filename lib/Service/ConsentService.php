<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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

	/**
	 * @psalm-param Attendee::ACTOR_* $actorType
	 */
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
