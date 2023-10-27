<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Kate Döen <kate.doeen@nextcloud.com>
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

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\TalkSession;
use OCA\Talk\TInitialState;
use OCA\Viewer\Event\LoadViewer;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\IgnoreOpenAPI;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\TooManyRequestsResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\HintException;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Notification\IManager as INotificationManager;
use OCP\Profile\IProfileManager;
use OCP\Security\Bruteforce\IThrottler;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Security\RateLimiting\IRateLimitExceededException;
use Psr\Log\LoggerInterface;

#[IgnoreOpenAPI]
class PageController extends Controller {
	use TInitialState;

	public function __construct(
		string $appName,
		IRequest $request,
		private IEventDispatcher $eventDispatcher,
		private RoomController $api,
		private TalkSession $talkSession,
		private IUserSession $userSession,
		private ?string $userId,
		private LoggerInterface $logger,
		private Manager $manager,
		private ParticipantService $participantService,
		private RoomService $roomService,
		private IURLGenerator $url,
		private INotificationManager $notificationManager,
		private IAppManager $appManager,
		IInitialState $initialState,
		ICacheFactory $memcacheFactory,
		private IRootFolder $rootFolder,
		private IThrottler $throttler,
		Config $talkConfig,
		IConfig $serverConfig,
		IGroupManager $groupManager,
		protected IUserManager $userManager,
		protected IProfileManager $profileManager,
		protected ILimiter $limiter,
		protected IFactory $l10nFactory,
	) {
		parent::__construct($appName, $request);
		$this->initialState = $initialState;
		$this->memcacheFactory = $memcacheFactory;
		$this->talkConfig = $talkConfig;
		$this->serverConfig = $serverConfig;
		$this->groupManager = $groupManager;
	}

	/**
	 * @param string $token
	 * @return Response
	 * @throws HintException
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[UseSession]
	#[BruteForceProtection(action: 'talkRoomToken')]
	public function showCall(string $token): Response {
		// This is the entry point from the `/call/{token}` URL which is hardcoded in the server.
		return $this->index($token);
	}

	/**
	 * @param string $token
	 * @param string $password
	 * @return Response
	 * @throws HintException
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[UseSession]
	#[BruteForceProtection(action: 'talkRoomPassword')]
	public function authenticatePassword(string $token, string $password = ''): Response {
		// This is the entry point from the `/call/{token}` URL which is hardcoded in the server.
		return $this->pageHandler($token, '', $password);
	}

	#[NoCSRFRequired]
	#[PublicPage]
	public function notFound(): Response {
		return $this->pageHandler();
	}

	#[NoCSRFRequired]
	#[PublicPage]
	public function duplicateSession(): Response {
		return $this->pageHandler();
	}

	/**
	 * @param string $token
	 * @param string $callUser
	 * @return TemplateResponse|RedirectResponse|TooManyRequestsResponse
	 * @throws HintException
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'talkRoomToken')]
	#[UseSession]
	public function index(string $token = '', string $callUser = ''): Response {
		if ($callUser !== '') {
			$token = '';
		}
		return $this->pageHandler($token, $callUser);
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	protected function createContactRequestRoom(string $targetUserId): Room {
		$user = $this->userManager->get($targetUserId);
		if (!$user instanceof IUser) {
			throw new \InvalidArgumentException('user');
		}

		if ($this->talkConfig->isNotAllowedToCreateConversations($user)) {
			throw new \InvalidArgumentException('config');
		}

		if (!$this->profileManager->isProfileFieldVisible('talk', $user, null)) {
			throw new \InvalidArgumentException('profile');
		}

		$l = $this->l10nFactory->get('spreed', $this->l10nFactory->getUserLanguage($user));

		return $this->roomService->createConversation(
			Room::TYPE_PUBLIC,
			$l->t('Contact request'),
			$user,
		);
	}

	/**
	 * @param string $token
	 * @param string $callUser
	 * @param string $password
	 * @return TemplateResponse|RedirectResponse|TooManyRequestsResponse
	 * @throws HintException
	 */
	protected function pageHandler(string $token = '', string $callUser = '', string $password = ''): Response {
		$bruteForceToken = $token;
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			if ($token === '') {
				try {
					$this->limiter->registerAnonRequest(
						'create-anonymous-conversation',
						5, // Five conversations
						60 * 60, // Per hour
						$this->request->getRemoteAddress(),
					);
				} catch (IRateLimitExceededException) {
					return new TooManyRequestsResponse();
				}

				try {
					$room = $this->createContactRequestRoom($callUser);
				} catch (\InvalidArgumentException) {
					$response = new TemplateResponse('core', '404-profile', [], TemplateResponse::RENDER_AS_GUEST);
					$response->throttle(['action' => 'callUser', 'callUser' => $callUser]);
					return $response;
				}

				return $this->redirectToConversation($room->getToken());
			}

			return $this->guestEnterRoom($token, $password);
		}

		$throttle = false;
		if ($token !== '') {
			$room = null;
			try {
				$room = $this->manager->getRoomByToken($token, $this->userId);
				$notification = $this->notificationManager->createNotification();
				$shouldFlush = $this->notificationManager->defer();
				try {
					$notification->setApp('spreed')
						->setUser($this->userId)
						->setObject('room', $room->getToken());
					$this->notificationManager->markProcessed($notification);
					$notification->setObject('call', $room->getToken());
					$this->notificationManager->markProcessed($notification);
				} catch (\InvalidArgumentException $e) {
					$this->logger->error($e->getMessage(), ['exception' => $e]);
				}

				if ($shouldFlush) {
					$this->notificationManager->flush();
				}

				// If the room is not a public room, check if the user is in the participants
				if ($room->getType() !== Room::TYPE_PUBLIC) {
					$this->manager->getRoomForUser($room->getId(), $this->userId);
				}
			} catch (RoomNotFoundException $e) {
				// Room not found, redirect to main page
				$token = '';
				$throttle = true;
			}

			if ($room instanceof Room && $room->hasPassword()) {
				// If the user joined themselves or is not found, they need the password.
				try {
					$participant = $this->participantService->getParticipant($room, $this->userId, false);
					$requirePassword = $participant->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED;
				} catch (ParticipantNotFoundException $e) {
					$requirePassword = true;
				}

				if ($requirePassword) {
					$password = $password !== '' ? $password : (string) $this->talkSession->getPasswordForRoom($token);

					$passwordVerification = $this->roomService->verifyPassword($room, $password);

					if ($passwordVerification['result']) {
						$this->talkSession->renewSessionId();
						$this->talkSession->setPasswordForRoom($token, $password);
						$this->throttler->resetDelay($this->request->getRemoteAddress(), 'talkRoomPassword', ['token' => $token, 'action' => 'talkRoomPassword']);
					} else {
						$this->talkSession->removePasswordForRoom($token);
						$showBruteForceWarning = $this->throttler->getDelay($this->request->getRemoteAddress(), 'talkRoomPassword') > 5000;

						if ($passwordVerification['url'] === '') {
							$response = new TemplateResponse($this->appName, 'authenticate', [
								'wrongpw' => $password !== '',
								'showBruteForceWarning' => $showBruteForceWarning,
							], 'guest');
						} else {
							$response = new RedirectResponse($passwordVerification['url']);
						}

						$response->throttle(['token' => $token, 'action' => 'talkRoomPassword']);
						return $response;
					}
				}
			}
		} else {
			$response = $this->api->createRoom(Room::TYPE_ONE_TO_ONE, $callUser);
			if ($response->getStatus() === Http::STATUS_OK
				|| $response->getStatus() === Http::STATUS_CREATED) {
				$data = $response->getData();
				return $this->redirectToConversation($data['token']);
			}
		}

		$this->publishInitialStateForUser($user, $this->rootFolder, $this->appManager);

		if (class_exists(LoadViewer::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadViewer());
		}

		$this->eventDispatcher->dispatchTyped(new LoadAdditionalScriptsEvent());
		$this->eventDispatcher->dispatchTyped(new RenderReferenceEvent());

		$response = new TemplateResponse($this->appName, 'index', [
			'app' => Application::APP_ID,
			'id-app-content' => '#content-vue',
			'id-app-navigation' => '#app-navigation-vue',
		]);

		$csp = new ContentSecurityPolicy();
		$csp->addAllowedConnectDomain('*');
		$csp->addAllowedMediaDomain('blob:');
		$csp->addAllowedWorkerSrcDomain('blob:');
		$csp->addAllowedWorkerSrcDomain("'self'");
		$csp->addAllowedChildSrcDomain('blob:');
		$csp->addAllowedChildSrcDomain("'self'");
		$csp->addAllowedScriptDomain('blob:');
		$csp->addAllowedScriptDomain("'self'");
		$csp->addAllowedConnectDomain('blob:');
		$csp->addAllowedConnectDomain("'self'");
		$csp->addAllowedImageDomain('https://*.tile.openstreetmap.org');
		$response->setContentSecurityPolicy($csp);
		if ($throttle) {
			// Logged-in user tried to access a chat they can not access
			$response->throttle(['token' => $bruteForceToken, 'action' => 'talkRoomToken']);
		}
		return $response;
	}

	/**
	 * @param string $token
	 * @return TemplateResponse|NotFoundResponse
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'talkRoomToken')]
	public function recording(string $token): Response {
		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException $e) {
			$response = new NotFoundResponse();
			$response->throttle(['token' => $token, 'action' => 'talkRoomToken']);

			return $response;
		}

		if (class_exists(LoadViewer::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadViewer());
		}

		$this->publishInitialStateForGuest();

		$this->eventDispatcher->dispatchTyped(new LoadAdditionalScriptsEvent());
		$this->eventDispatcher->dispatchTyped(new RenderReferenceEvent());

		$response = new PublicTemplateResponse($this->appName, 'recording', [
			'id-app-content' => '#content-vue',
			'id-app-navigation' => null,
		]);

		$response->setFooterVisible(false);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedConnectDomain('*');
		$csp->addAllowedMediaDomain('blob:');
		$csp->addAllowedWorkerSrcDomain('blob:');
		$csp->addAllowedWorkerSrcDomain("'self'");
		$csp->addAllowedChildSrcDomain('blob:');
		$csp->addAllowedChildSrcDomain("'self'");
		$csp->addAllowedScriptDomain('blob:');
		$csp->addAllowedScriptDomain("'self'");
		$csp->addAllowedConnectDomain('blob:');
		$csp->addAllowedConnectDomain("'self'");
		$csp->addAllowedImageDomain('https://*.tile.openstreetmap.org');
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
			if ($room->getType() !== Room::TYPE_PUBLIC) {
				throw new RoomNotFoundException();
			}
		} catch (RoomNotFoundException $e) {
			$redirectUrl = $this->url->linkToRoute('spreed.Page.index');
			if ($token) {
				$redirectUrl = $this->url->linkToRoute('spreed.Page.showCall', ['token' => $token]);
			}
			$response = new RedirectResponse($this->url->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $redirectUrl,
			]));
			$response->throttle(['token' => $token, 'action' => 'talkRoomToken']);
			return $response;
		}

		if ($room->hasPassword()) {
			$password = $password !== '' ? $password : (string) $this->talkSession->getPasswordForRoom($token);

			$passwordVerification = $this->roomService->verifyPassword($room, $password);
			if ($passwordVerification['result']) {
				$this->talkSession->renewSessionId();
				$this->talkSession->setPasswordForRoom($token, $password);
				$this->throttler->resetDelay($this->request->getRemoteAddress(), 'talkRoomPassword', ['token' => $token, 'action' => 'talkRoomPassword']);
			} else {
				$this->talkSession->removePasswordForRoom($token);
				$showBruteForceWarning = $this->throttler->getDelay($this->request->getRemoteAddress(), 'talkRoomPassword') > 5000;

				if ($passwordVerification['url'] === '') {
					$response = new TemplateResponse($this->appName, 'authenticate', [
						'wrongpw' => $password !== '',
						'showBruteForceWarning' => $showBruteForceWarning,
					], 'guest');
				} else {
					$response = new RedirectResponse($passwordVerification['url']);
				}
				$response->throttle(['token' => $token, 'action' => 'talkRoomPassword']);
				return $response;
			}
		}

		$this->publishInitialStateForGuest();
		$this->eventDispatcher->dispatchTyped(new RenderReferenceEvent());

		$response = new PublicTemplateResponse($this->appName, 'index', [
			'id-app-content' => '#content-vue',
			'id-app-navigation' => null,
		]);

		$response->setFooterVisible(false);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedConnectDomain('*');
		$csp->addAllowedMediaDomain('blob:');
		$csp->addAllowedWorkerSrcDomain('blob:');
		$csp->addAllowedWorkerSrcDomain("'self'");
		$csp->addAllowedChildSrcDomain('blob:');
		$csp->addAllowedChildSrcDomain("'self'");
		$csp->addAllowedScriptDomain('blob:');
		$csp->addAllowedScriptDomain("'self'");
		$csp->addAllowedConnectDomain('blob:');
		$csp->addAllowedConnectDomain("'self'");
		$csp->addAllowedImageDomain('https://*.tile.openstreetmap.org');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @param string $token
	 * @return RedirectResponse
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	protected function redirectToConversation(string $token): RedirectResponse {
		// These redirects are already done outside of this method
		if ($this->userId === null) {
			try {
				$room = $this->manager->getRoomByToken($token);
				if ($room->getType() !== Room::TYPE_PUBLIC) {
					throw new RoomNotFoundException();
				}
				return new RedirectResponse($this->url->linkToRoute('spreed.Page.showCall', ['token' => $token]));
			} catch (RoomNotFoundException $e) {
				return new RedirectResponse($this->url->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->url->linkToRoute('spreed.Page.showCall', ['token' => $token]),
				]));
			}
		}
		return new RedirectResponse($this->url->linkToRoute('spreed.Page.showCall', ['token' => $token]));
	}
}
