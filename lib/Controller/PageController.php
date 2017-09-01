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

use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use OCP\Security\ISecureRandom;
use OC\HintException;

class PageController extends Controller {
	/** @var string */
	private $userId;
	/** @var RoomController */
	private $api;
	/** @var ILogger */
	private $logger;
	/** @var Manager */
	private $manager;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var IURLGenerator */
	private $url;
	/** @var IManager */
	private $notificationManager;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param RoomController $api
	 * @param string $UserId
	 * @param ILogger $logger
	 * @param Manager $manager
	 * @param ISecureRandom $secureRandom
	 * @param IURLGenerator $url
	 * @param IManager $notificationManager
	 */
	public function __construct($appName,
								IRequest $request,
								RoomController $api,
								$UserId,
								ILogger $logger,
								Manager $manager,
								ISecureRandom $secureRandom,
								IURLGenerator $url,
								IManager $notificationManager) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->api = $api;
		$this->logger = $logger;
		$this->manager = $manager;
		$this->secureRandom = $secureRandom;
		$this->url = $url;
		$this->notificationManager = $notificationManager;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $token
	 * @param string $callUser
	 * @return TemplateResponse|RedirectResponse
	 * @throws HintException
	 */
	public function index($token = '', $callUser = '') {
		if ($this->userId === null) {
			return $this->guestEnterRoom($token);
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
		} else {
			$response = $this->api->createRoom(Room::ONE_TO_ONE_CALL, $callUser);
			if ($response->getStatus() !== Http::STATUS_NOT_FOUND) {
				$data = $response->getData();
				return new RedirectResponse($this->url->linkToRoute('spreed.Page.showCall', ['token' => $data['token']]));
			}
		}

		$params = [
			'sessionId' => $this->userId,
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
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse|RedirectResponse
	 * @throws HintException
	 */
	public function presentationsSandbox() {
		// TODO(leon): ..
		// Dear reviewer, please let me know how to properly handle this. I honestly don't know how :(
		$rootDir = '../../../../';
		$appDir = $rootDir . 'apps/spreed/';
		$cssDir = $appDir . 'css/';
		$jsDir = $appDir . 'js/';
		$params = array(
			'cssfiles' => array(
				$cssDir . 'presentation/sandbox.css',
			),
			'jsfiles' => array(
				$rootDir . 'core/vendor/underscore/underscore.js',
				$rootDir . 'core/vendor/jquery/dist/jquery.min.js',
				$jsDir . 'vendor/pdfjs-dist/build/pdf.combined.js',
				$jsDir . 'postmessage.js',
				$jsDir . 'presentation/consts.js',
				$jsDir . 'presentation/type-base.js',
				$jsDir . 'presentation/type-pdf.js',
				$jsDir . 'presentation/sandbox.js',
			),
		);
		$response = new TemplateResponse($this->appName, 'sandbox-presentations', $params, 'blank');
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedConnectDomain('blob:');
		$csp->addAllowedFontDomain('data:');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @param string $token
	 * @return TemplateResponse|RedirectResponse
	 * @throws HintException
	 */
	protected function guestEnterRoom($token) {
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

		$newSessionId = $this->secureRandom->generate(255);
		$params = [
			'sessionId' => $newSessionId,
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
