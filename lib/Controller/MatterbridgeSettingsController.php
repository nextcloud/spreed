<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Exceptions\ImpossibleToKillException;
use OCA\Talk\Exceptions\WrongPermissionsException;
use OCA\Talk\MatterbridgeManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class MatterbridgeSettingsController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		protected MatterbridgeManager $bridgeManager,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get Matterbridge version
	 *
	 * @return DataResponse<Http::STATUS_OK, array{version: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Bridge version returned
	 * 400: Getting bridge version is not possible
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION, tags: ['matterbridge'])]
	public function getMatterbridgeVersion(): DataResponse {
		try {
			$version = $this->bridgeManager->getCurrentVersionFromBinary();
			if ($version === null) {
				return new DataResponse([
					'error' => 'binary',
				], Http::STATUS_BAD_REQUEST);
			}
		} catch (WrongPermissionsException $e) {
			return new DataResponse([
				'error' => 'binary_permissions',
			], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse([
			'version' => $version,
		]);
	}

	/**
	 * Stop all bridges
	 *
	 * @return DataResponse<Http::STATUS_OK, bool, array{}>|DataResponse<Http::STATUS_NOT_ACCEPTABLE, array{error: string}, array{}>
	 *
	 * 200: All bridges stopped successfully
	 * 406: Stopping all bridges is not possible
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION, tags: ['matterbridge'])]
	public function stopAllBridges(): DataResponse {
		try {
			$success = $this->bridgeManager->stopAllBridges();
		} catch (ImpossibleToKillException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_ACCEPTABLE);
		}
		return new DataResponse($success);
	}
}
