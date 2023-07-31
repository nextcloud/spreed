<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @author Kate Döen <kate.doeen@nextcloud.com>
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
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Middleware\Attribute\RequireLoggedInModeratorParticipant;
use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type SpreedMatterbridge from ResponseDefinitions
 * @psalm-import-type SpreedMatterbridgeParts from ResponseDefinitions
 * @psalm-import-type SpreedMatterbridgeProcessState from ResponseDefinitions
 * @psalm-import-type SpreedMatterbridgeWithProcessState from ResponseDefinitions
 */
class MatterbridgeController extends AEnvironmentAwareController {

	public function __construct(
		string $appName,
		protected ?string $userId,
		IRequest $request,
		protected Manager $manager,
		protected MatterbridgeManager $bridgeManager,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get bridge information of one room
	 *
	 * @return DataResponse<Http::STATUS_OK, SpreedMatterbridgeWithProcessState, array{}>
	 *
	 * 200: Return list of configured Matterbridges
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function getBridgeOfRoom(): DataResponse {
		$pid = $this->bridgeManager->checkBridge($this->room);
		$logContent = $this->bridgeManager->getBridgeLog($this->room);
		$bridge = $this->bridgeManager->getBridgeOfRoom($this->room);
		$bridge['running'] = ($pid !== 0);
		$bridge['log'] = $logContent;
		return new DataResponse($bridge);
	}

	/**
	 * Get bridge process information
	 *
	 * @return DataResponse<Http::STATUS_OK, SpreedMatterbridgeProcessState, array{}>
	 *
	 * 200: Return list of running processes
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function getBridgeProcessState(): DataResponse {
		$state = $this->bridgeManager->getBridgeProcessState($this->room);
		return new DataResponse($state);
	}

	/**
	 * Edit bridge information of one room
	 *
	 * @param bool $enabled If the bridge should be enabled
	 * @param SpreedMatterbridgeParts $parts New parts
	 * @return DataResponse<Http::STATUS_OK, SpreedMatterbridgeProcessState, array{}>|DataResponse<Http::STATUS_NOT_ACCEPTABLE, array{error: string}, array{}>
	 *
	 * 200: Bridge edited successfully
	 * 406: Editing bridge is not possible
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function editBridgeOfRoom(bool $enabled, array $parts = []): DataResponse {
		try {
			$state = $this->bridgeManager->editBridgeOfRoom($this->room, $this->userId, $enabled, $parts);
		} catch (ImpossibleToKillException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_ACCEPTABLE);
		}
		return new DataResponse($state);
	}

	/**
	 * Delete bridge of one room
	 *
	 * @return DataResponse<Http::STATUS_OK, bool, array{}>|DataResponse<Http::STATUS_NOT_ACCEPTABLE, array{error: string}, array{}>
	 *
	 * 200: Bridge deleted successfully
	 * 406: Deleting bridge is not possible
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function deleteBridgeOfRoom(): DataResponse {
		try {
			$success = $this->bridgeManager->deleteBridgeOfRoom($this->room);
		} catch (ImpossibleToKillException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_ACCEPTABLE);
		}
		return new DataResponse($success);
	}
}
