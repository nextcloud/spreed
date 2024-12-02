<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
 * @psalm-import-type TalkMatterbridge from ResponseDefinitions
 * @psalm-import-type TalkMatterbridgeConfigFields from ResponseDefinitions
 * @psalm-import-type TalkMatterbridgeProcessState from ResponseDefinitions
 * @psalm-import-type TalkMatterbridgeWithProcessState from ResponseDefinitions
 */
class MatterbridgeController extends AEnvironmentAwareOCSController {

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
	 * @return DataResponse<Http::STATUS_OK, TalkMatterbridgeWithProcessState, array{}>
	 *
	 * 200: Return list of configured bridges
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
	 * @return DataResponse<Http::STATUS_OK, TalkMatterbridgeProcessState, array{}>
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
	 * @param TalkMatterbridgeConfigFields $parts New parts
	 * @return DataResponse<Http::STATUS_OK, TalkMatterbridgeProcessState, array{}>|DataResponse<Http::STATUS_NOT_ACCEPTABLE, array{error: string}, array{}>
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
