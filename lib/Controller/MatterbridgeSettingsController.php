<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
use OCA\Talk\Exceptions\WrongPermissionsException;
use OCA\Talk\MatterbridgeManager;
use OCP\AppFramework\Http;
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
	public function stopAllBridges(): DataResponse {
		try {
			$success = $this->bridgeManager->stopAllBridges();
		} catch (ImpossibleToKillException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_ACCEPTABLE);
		}
		return new DataResponse($success);
	}
}
