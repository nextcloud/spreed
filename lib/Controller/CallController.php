<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

use OCA\Talk\Participant;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;

class CallController extends AEnvironmentAwareController {

	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(string $appName,
								IRequest $request,
								ITimeFactory $timeFactory) {
		parent::__construct($appName, $request);
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * @return DataResponse
	 */
	public function getPeersForCall(): DataResponse {
		$timeout = $this->timeFactory->getTime() - 30;
		$result = [];
		$participants = $this->room->getParticipantsInCall();
		foreach ($participants as $participant) {
			if ($participant->getLastPing() < $timeout) {
				// User is not active in call
				continue;
			}

			$result[] = [
				'userId' => $participant->getUser(),
				'token' => $this->room->getToken(),
				'lastPing' => $participant->getLastPing(),
				'sessionId' => $participant->getSessionId(),
			];
		}

		return new DataResponse($result);
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * @param int|null $flags
	 * @return DataResponse
	 */
	public function joinCall(?int $flags): DataResponse {
		$this->room->ensureOneToOneRoomIsFilled();

		$sessionId = $this->participant->getSessionId();
		if ($sessionId === '0') {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($flags === null) {
			// Default flags: user is in room with audio/video.
			$flags = Participant::FLAG_IN_CALL | Participant::FLAG_WITH_AUDIO | Participant::FLAG_WITH_VIDEO;
		}

		$this->room->changeInCall($this->participant, $flags);

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 *
	 * @return DataResponse
	 */
	public function leaveCall(): DataResponse {
		$sessionId = $this->participant->getSessionId();
		if ($sessionId === '0') {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$this->room->changeInCall($this->participant, Participant::FLAG_DISCONNECTED);

		return new DataResponse();
	}
}
