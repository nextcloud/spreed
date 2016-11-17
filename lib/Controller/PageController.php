<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

use OC\HintException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IURLGenerator;

class PageController extends Controller {
	/** @var string */
	private $userId;
	/** @var IDBConnection */
	private $dbConnection;
	/** @var IURLGenerator */
	private $url;
	/** @var Manager */
	private $manager;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param string $UserId
	 * @param IDBConnection $dbConnection
	 * @param IURLGenerator $url
	 * @param Manager $manager
	 */
	public function __construct($appName,
								IRequest $request,
								$UserId,
								IDBConnection $dbConnection,
								IURLGenerator $url,
								Manager $manager) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->dbConnection = $dbConnection;
		$this->url = $url;
		$this->manager = $manager;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param int $roomId
	 * @return TemplateResponse
	 */
	public function index($roomId = 0) {
		if ($this->userId === null) {
			return $this->guestEnterRoom($roomId);
		}

		if ($roomId !== 0) {
			try {
				$this->manager->getRoomForParticipant($roomId, $this->userId);
			} catch (RoomNotFoundException $e) {
				// Room not found - try if it is a public room
				try {
					$room = $this->manager->getRoomById($roomId);
					if ($room->getType() !== Room::PUBLIC_CALL) {
						throw new RoomNotFoundException();
					}
				} catch (RoomNotFoundException $e) {
					// Room not found, redirect to main page
					$roomId = 0;
				}
			}
		}

		$params = [
			'sessionId' => $this->userId,
			'roomId' => $roomId,
		];
		$response = new TemplateResponse($this->appName, 'index', $params);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedConnectDomain('*');
		$csp->addAllowedMediaDomain('blob:');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @param $roomId
	 * @return TemplateResponse
	 * @throws HintException
	 */
	protected function guestEnterRoom($roomId) {
		try {
			$room = $this->manager->getRoomById($roomId);
			if ($room->getType() !== Room::PUBLIC_CALL) {
				throw new RoomNotFoundException();
			}
		} catch (RoomNotFoundException $e) {
			throw new HintException('Room not found');
		}

		throw new HintException('Room found');
	}
}
