<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;

class WebinarController extends AEnvironmentAwareController {

	/** @var ITimeFactory */
	protected $timeFactory;

	public function __construct(string $appName,
								IRequest $request,
								ITimeFactory $timeFactory) {
		parent::__construct($appName, $request);
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @NoAdminRequired
	 * @RequireModeratorParticipant
	 *
	 * @param int $state
	 * @param int|null $timer
	 * @return DataResponse
	 */
	public function setLobby(int $state, ?int $timer = null): DataResponse {
		$timerDateTime = null;
		if ($timer !== null && $timer > 0) {
			try {
				$timerDateTime = $this->timeFactory->getDateTime('@' . $timer);
				$timerDateTime->setTimezone(new \DateTimeZone('UTC'));
			} catch (\Exception $e) {
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}
		}

		if (!$this->room->setLobby($state, $timerDateTime)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 * @RequireModeratorParticipant
	 *
	 * @param int $state
	 * @return DataResponse
	 */
	public function setSIPEnabled(int $state): DataResponse {
		// TODO Check if user is in "SIP groups"

		if (!$this->room->setSIPEnabled($state)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}
}
