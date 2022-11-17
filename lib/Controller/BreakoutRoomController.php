<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Controller;

use InvalidArgumentException;
use OCA\Talk\Service\BreakoutRoomService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class BreakoutRoomController extends AEnvironmentAwareController {
	protected BreakoutRoomService $breakoutRoomService;

	public function __construct(string $appName,
								IRequest $request,
								BreakoutRoomService $breakoutRoomService) {
		parent::__construct($appName, $request);
		$this->breakoutRoomService = $breakoutRoomService;
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInModeratorParticipant
	 *
	 * @param int $mode
	 * @param int $amount
	 * @param string $attendeeMap
	 * @return DataResponse
	 */
	public function configureBreakoutRooms(int $mode, int $amount, string $attendeeMap = '[]'): DataResponse {
		try {
			$this->breakoutRoomService->setupBreakoutRooms($this->room, $mode, $amount, $attendeeMap);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		// FIXME make a useful response?
		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInModeratorParticipant
	 *
	 * @return DataResponse
	 */
	public function removeBreakoutRooms(): DataResponse {
		$this->breakoutRoomService->removeBreakoutRooms($this->room);
		return new DataResponse();
	}
}
