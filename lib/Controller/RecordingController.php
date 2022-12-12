<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class RecordingController extends AEnvironmentAwareController {
	private RoomService $roomService;

	public function __construct(string $appName,
								IRequest $request,
								RoomService $roomService) {
		parent::__construct($appName, $request);
		$this->roomService = $roomService;
	}

	/**
	 * @PublicPage
	 * @RequireCallEnabled
	 * @RequireModeratorParticipant
	 */
	public function startRecording(int $status): DataResponse {
		try {
			$this->roomService->startRecording($this->room, $status)
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireCallEnabled
	 * @RequireModeratorParticipant
	 */
	public function stopRecording(): DataResponse {
		try {
			$this->roomService->stopRecording($this->room);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse();
	}
}
