<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

namespace OCA\Talk\Controller;

use OCA\Talk\Exceptions\ImpossibleToKillException;
use OCA\Talk\Manager;
use OCA\Talk\BridgeManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class BridgeController extends AEnvironmentAwareController {
	/** @var string|null */
	protected $userId;
	/** @var Manager */
	protected $manager;
	/** @var BridgeManager */
	protected $bridgeManager;

	public function __construct(string $appName,
								?string $UserId,
								IRequest $request,
								Manager $manager,
								BridgeManager $bridgeManager) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->manager = $manager;
		$this->bridgeManager = $bridgeManager;
	}

	/**
	 * Get bridge information of one room
	 *
	 * @NoAdminRequired
	 * @RequireLoggedInModeratorParticipant
	 *
	 * @return DataResponse
	 */
	public function getBridgeOfRoom(string $token): DataResponse {
		$room = $this->manager->getRoomByToken($token);
		$this->bridgeManager->checkBridge($room);
		$bridge = $this->bridgeManager->getBridgeOfRoom($room);
		return new DataResponse($bridge);
	}

	/**
	 * Edit bridge information of one room
	 *
	 * @NoAdminRequired
	 * @RequireLoggedInModeratorParticipant
	 *
	 * @return DataResponse
	 */
	public function editBridgeOfRoom(string $token, bool $enabled, array $parts = []): DataResponse {
		$room = $this->manager->getRoomByToken($token);
		try {
			$success = $this->bridgeManager->editBridgeOfRoom($room, $enabled, $parts);
		} catch (ImpossibleToKillException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_ACCEPTABLE);
		}
		return new DataResponse($success);
	}

	/**
	 * Delete bridge of one room
	 *
	 * @NoAdminRequired
	 * @RequireLoggedInModeratorParticipant
	 *
	 * @return DataResponse
	 */
	public function deleteBridgeOfRoom(string $token): DataResponse {
		$room = $this->manager->getRoomByToken($token);
		try {
			$success = $this->bridgeManager->deleteBridgeOfRoom($room);
		} catch (ImpossibleToKillException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_ACCEPTABLE);
		}
		return new DataResponse($success);
	}
}
