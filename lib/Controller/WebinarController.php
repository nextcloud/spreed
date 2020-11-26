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

use OCA\Talk\Config;
use OCA\Talk\Participant;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;

class WebinarController extends AEnvironmentAwareController {

	/** @var ITimeFactory */
	protected $timeFactory;
	/** @var ParticipantService */
	protected $participantService;
	/** @var Config */
	protected $talkConfig;
	/** @var IUserManager */
	protected $userManager;
	/** @var string|null */
	protected $userId;

	public function __construct(string $appName,
								IRequest $request,
								ITimeFactory $timeFactory,
								ParticipantService $participantService,
								Config $talkConfig,
								IUserManager $userManager,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->timeFactory = $timeFactory;
		$this->participantService = $participantService;
		$this->talkConfig = $talkConfig;
		$this->userManager = $userManager;
		$this->userId = $userId;
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

		if ($state === Webinary::LOBBY_NON_MODERATORS) {
			$participants = $this->participantService->getParticipantsInCall($this->room);
			foreach ($participants as $participant) {
				if ($participant->hasModeratorPermissions()) {
					continue;
				}

				$this->participantService->changeInCall($this->room, $participant, Participant::FLAG_DISCONNECTED);
			}
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
		$user = $this->userManager->get($this->userId);
		if (!$user instanceof IUser) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}

		if (!$this->talkConfig->canUserEnableSIP($user)) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}

		if (!$this->talkConfig->isSIPConfigured()) {
			return new DataResponse([], Http::STATUS_PRECONDITION_FAILED);
		}

		if (!$this->room->setSIPEnabled($state)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}
}
