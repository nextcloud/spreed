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
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Notification\IManager;

class PageController extends Controller {
	/** @var string */
	private $userId;
	/** @var RoomController */
	private $api;
	/** @var ISession */
	private $session;
	/** @var ILogger */
	private $logger;
	/** @var Manager */
	private $manager;
	/** @var IURLGenerator */
	private $url;
	/** @var IManager */
	private $notificationManager;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param RoomController $api
	 * @param ISession $session
	 * @param string $UserId
	 * @param ILogger $logger
	 * @param Manager $manager
	 * @param IURLGenerator $url
	 * @param IManager $notificationManager
	 */
	public function __construct($appName,
								IRequest $request,
								RoomController $api,
								ISession $session,
								$UserId,
								ILogger $logger,
								Manager $manager,
								IURLGenerator $url,
								IManager $notificationManager) {
		parent::__construct($appName, $request);
		$this->api = $api;
		$this->session = $session;
		$this->userId = $UserId;
		$this->logger = $logger;
		$this->manager = $manager;
		$this->url = $url;
		$this->notificationManager = $notificationManager;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * @param string $token
	 * @param string $callUser
	 * @param string $password
	 * @return TemplateResponse|RedirectResponse
	 * @throws HintException
	 */
	public function index($token = '', $callUser = '', $password = '') {
		if ($this->userId === null) {
			return $this->guestEnterRoom($token, $password);
		}

		if ($token !== '') {
			$room = null;
			try {
				$room = $this->manager->getRoomByToken($token);
				if ($this->userId !== null) {
					$notification = $this->notificationManager->createNotification();
					try {
						$notification->setApp('spreed')
							->setUser($this->userId)
							->setObject('room', (string) $room->getId());
						$this->notificationManager->markProcessed($notification);
					} catch (\InvalidArgumentException $e) {
						$this->logger->logException($e, ['app' => 'spreed']);
					}
				}

				// If the room is not a public room, check if the user is in the participants
				if ($room->getType() !== Room::PUBLIC_CALL) {
					$this->manager->getRoomForParticipant($room->getId(), $this->userId);
				}
			} catch (RoomNotFoundException $e) {
				// Room not found, redirect to main page
				$token = '';
			}

			$this->session->remove('spreed-password');

			if ($room->hasPassword()) {
				// If the user joined themselves or is not found, they need the password.
				try {
					$participant = $room->getParticipant($this->userId);
					$requirePassword = $participant->getParticipantType() === Participant::USER_SELF_JOINED;
				} catch (ParticipantNotFoundException $e) {
					$requirePassword = true;
				}

				if ($requirePassword) {
					if ($password !== '' && $room->verifyPassword($password)) {
						$this->session->set('spreed-password', $token);
					} else {
						return new TemplateResponse($this->appName, 'authenticate', [], 'guest');
					}
				}
			}
		} else {
			$response = $this->api->createRoom(Room::ONE_TO_ONE_CALL, $callUser);
			if ($response->getStatus() !== Http::STATUS_NOT_FOUND) {
				$data = $response->getData();
				return new RedirectResponse($this->url->linkToRoute('spreed.Page.showCall', ['token' => $data['token']]));
			}
		}

		$params = [
			'token' => $token,
		];
		$response = new TemplateResponse($this->appName, 'index', $params);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedConnectDomain('*');
		$csp->addAllowedMediaDomain('blob:');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @param string $token
	 * @param string $password
	 * @return TemplateResponse|RedirectResponse
	 * @throws HintException
	 */
	protected function guestEnterRoom($token, $password) {
		try {
			$room = $this->manager->getRoomByToken($token);
			if ($room->getType() !== Room::PUBLIC_CALL) {
				throw new RoomNotFoundException();
			}
		} catch (RoomNotFoundException $e) {
			return new RedirectResponse($this->url->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $this->url->linkToRoute('spreed.Page.index', ['token' => $token]),
			]));
		}

		$this->session->remove('spreed-password');
		if ($room->hasPassword()) {
			if ($password !== '' && $room->verifyPassword($password)) {
				$this->session->set('spreed-password', $token);
			} else {
				return new TemplateResponse($this->appName, 'authenticate', [], 'guest');
			}
		}

		$params = [
			'token' => $token,
		];
		$response = new TemplateResponse($this->appName, 'index-public', $params, 'base');
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedConnectDomain('*');
		$csp->addAllowedMediaDomain('blob:');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @param string $token
	 * @return RedirectResponse
	 */
	protected function showCall($token) {
		// These redirects are already done outside of this method
		if ($this->userId === null) {
			try {
				$room = $this->manager->getRoomByToken($token);
				if ($room->getType() !== Room::PUBLIC_CALL) {
					throw new RoomNotFoundException();
				}
				return new RedirectResponse($this->url->linkToRoute('spreed.Page.index', ['token' => $token]));
			} catch (RoomNotFoundException $e) {
				return new RedirectResponse($this->url->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->url->linkToRoute('spreed.Page.index', ['token' => $token]),
				]));
			}
		}
		return new RedirectResponse($this->url->linkToRoute('spreed.Page.index', ['token' => $token]));
	}
}
