<?php
declare(strict_types=1);
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
use OCA\Spreed\Config;
use OCA\Spreed\Manager;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Notification\IManager;

class PageController extends Controller {
	/** @var string|null */
	private $userId;
	/** @var RoomController */
	private $api;
	/** @var TalkSession */
	private $talkSession;
	/** @var IUserSession */
	private $userSession;
	/** @var ILogger */
	private $logger;
	/** @var Manager */
	private $manager;
	/** @var IURLGenerator */
	private $url;
	/** @var IManager */
	private $notificationManager;
	/** @var Config */
	private $config;

	public function __construct(string $appName,
								IRequest $request,
								RoomController $api,
								TalkSession $session,
								IUserSession $userSession,
								?string $UserId,
								ILogger $logger,
								Manager $manager,
								IURLGenerator $url,
								IManager $notificationManager,
								Config $config) {
		parent::__construct($appName, $request);
		$this->api = $api;
		$this->talkSession = $session;
		$this->userSession = $userSession;
		$this->userId = $UserId;
		$this->logger = $logger;
		$this->manager = $manager;
		$this->url = $url;
		$this->notificationManager = $notificationManager;
		$this->config = $config;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * @param string $token
	 * @return Response
	 * @throws HintException
	 */
	public function showCall(string $token): Response {
		// This is the entry point from the `/call/{token}` URL which is hardcoded in the server.
		return $this->index($token);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * @param string $token
	 * @param string $password
	 * @return Response
	 * @throws HintException
	 */
	public function authenticatePassword(string $token, string $password = ''): Response {
		// This is the entry point from the `/call/{token}` URL which is hardcoded in the server.
		return $this->index($token, '', $password);
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
	public function index(string $token = '', string $callUser = '', string $password = ''): Response {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return $this->guestEnterRoom($token, $password);
		}

		if ($token !== '') {
			$room = null;
			try {
				$room = $this->manager->getRoomByToken($token);
				$notification = $this->notificationManager->createNotification();
				try {
					$notification->setApp('spreed')
						->setUser($this->userId)
						->setObject('room', $room->getToken());
					$this->notificationManager->markProcessed($notification);
					$notification->setObject('call', $room->getToken());
					$this->notificationManager->markProcessed($notification);
				} catch (\InvalidArgumentException $e) {
					$this->logger->logException($e, ['app' => 'spreed']);
				}

				// If the room is not a public room, check if the user is in the participants
				if ($room->getType() !== Room::PUBLIC_CALL) {
					$this->manager->getRoomForParticipant($room->getId(), $this->userId);
				}
			} catch (RoomNotFoundException $e) {
				// Room not found, redirect to main page
				$token = '';
			}

			if ($room instanceof Room && $room->hasPassword()) {
				// If the user joined themselves or is not found, they need the password.
				try {
					$participant = $room->getParticipant($this->userId);
					$requirePassword = $participant->getParticipantType() === Participant::USER_SELF_JOINED;
				} catch (ParticipantNotFoundException $e) {
					$requirePassword = true;
				}

				if ($requirePassword) {
					$password = $password !== '' ? $password : (string) $this->talkSession->getPasswordForRoom($token);

					$passwordVerification = $room->verifyPassword($password);

					if ($passwordVerification['result']) {
						$this->talkSession->setPasswordForRoom($token, $password);
					} else {
						$this->talkSession->removePasswordForRoom($token);
						if ($passwordVerification['url'] === '') {
							return new TemplateResponse($this->appName, 'authenticate', [
								'wrongpw' => $password !== '',
							], 'guest');
						}

						return new RedirectResponse($passwordVerification['url']);
					}
				}
			}
		} else {
			$response = $this->api->createRoom(Room::ONE_TO_ONE_CALL, $callUser);
			if ($response->getStatus() !== Http::STATUS_NOT_FOUND) {
				$data = $response->getData();
				return $this->redirectToConversation($data['token']);
			}
		}

		$params = [
			'token' => $token,
			'signaling-settings' => $this->config->getSettings($this->userId),
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
	protected function guestEnterRoom(string $token, string $password): Response {
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

		if ($room->hasPassword()) {
			$password = $password !== '' ? $password : (string) $this->talkSession->getPasswordForRoom($token);

			$passwordVerification = $room->verifyPassword($password);
			if ($passwordVerification['result']) {
				$this->talkSession->setPasswordForRoom($token, $password);
			} else {
				$this->talkSession->removePasswordForRoom($token);
				if ($passwordVerification['url'] === '') {
					return new TemplateResponse($this->appName, 'authenticate', [
						'wrongpw' => $password !== '',
					], 'guest');
				}

				return new RedirectResponse($passwordVerification['url']);
			}
		}

		$params = [
			'token' => $token,
			'signaling-settings' => $this->config->getSettings($this->userId),
		];
		$response = new PublicTemplateResponse($this->appName, 'index-public', $params);
		$response->setFooterVisible(false);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedConnectDomain('*');
		$csp->addAllowedMediaDomain('blob:');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $token
	 * @return RedirectResponse
	 */
	protected function redirectToConversation(string $token): RedirectResponse {
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
