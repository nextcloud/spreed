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
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\Notification\IManager;
use OCP\Security\ISecureRandom;

class PageController extends Controller {
	/** @var string */
	private $userId;
	/** @var IL10N */
	private $l10n;
	/** @var ILogger */
	private $logger;
	/** @var Manager */
	private $manager;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var IManager */
	private $notificationManager;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param string $UserId
	 * @param IL10N $l10n
	 * @param ILogger $logger
	 * @param Manager $manager
	 * @param ISecureRandom $secureRandom
	 * @param IManager $notificationManager
	 */
	public function __construct($appName,
								IRequest $request,
								$UserId,
								IL10N $l10n,
								ILogger $logger,
								Manager $manager,
								ISecureRandom $secureRandom,
								IManager $notificationManager) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->manager = $manager;
		$this->secureRandom = $secureRandom;
		$this->notificationManager = $notificationManager;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param int $roomId
	 * @return TemplateResponse
	 * @throws HintException
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

			if ($this->userId !== null) {
				$notification = $this->notificationManager->createNotification();
				try {
					$notification->setApp('spreed')
						->setUser($this->userId)
						->setObject('room', (string) $roomId);
					$this->notificationManager->markProcessed($notification);
				} catch (\InvalidArgumentException $e) {
					$this->logger->logException($e, ['app' => 'spreed']);
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
			throw new HintException($this->l10n->t('The call does not exist.'));
		}

		$newSessionId = $this->secureRandom->generate(255);
		$params = [
			'sessionId' => $newSessionId,
			'roomId' => $roomId,
		];
		$response = new TemplateResponse($this->appName, 'index-public', $params, 'base');
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedConnectDomain('*');
		$csp->addAllowedMediaDomain('blob:');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}
}
