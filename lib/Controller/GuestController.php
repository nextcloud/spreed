<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Spreed\Controller;

use Doctrine\DBAL\DBALException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Manager;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class GuestController extends OCSController {

	/** @var string */
	private $userId;

	/** @var TalkSession */
	private $session;

	/** @var Manager */
	private $roomManager;

	/** @var GuestManager */
	private $guestManager;

	/**
	 * @param string $appName
	 * @param string $UserId
	 * @param IRequest $request
	 * @param TalkSession $session
	 * @param Manager $roomManager
	 * @param GuestManager $guestManager
	 */
	public function __construct($appName,
								$UserId,
								IRequest $request,
								TalkSession $session,
								Manager $roomManager,
								GuestManager $guestManager) {
		parent::__construct($appName, $request);

		$this->userId = $UserId;
		$this->session = $session;
		$this->roomManager = $roomManager;
		$this->guestManager = $guestManager;
	}

	/**
	 * @PublicPage
	 *
	 *
	 * @param string $token
	 * @param string $displayName
	 * @return DataResponse
	 */
	public function setDisplayName($token, $displayName) {
		if ($this->userId) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$sessionId = $this->session->getSessionForRoom($token);

		try {
			$room = $this->roomManager->getRoomForSession($this->userId, $sessionId);
		} catch (RoomNotFoundException $exception) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		try {
			$this->guestManager->updateName($room, $sessionId, $displayName);
		} catch (DBALException $e) {
			return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse();
	}

}
