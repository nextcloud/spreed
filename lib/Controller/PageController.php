<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
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
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
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
use OCP\IUserSession;
use OCP\Notification\IManager as INotificationManager;
use OCP\Security\Bruteforce\IThrottler;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
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
		LoggerInterface $logger,
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
	) {
		parent::__construct($appName, $request);
		$this->logger = $logger;
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
	public function showCall(string $token, string $email = '', string $access = ''): Response {
		// This is the entry point from the `/call/{token}` URL which is hardcoded in the server.
		return $this->pageHandler($token, email: $email, accessToken: $access);
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
		return $this->pageHandler($token, password: $password);
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
	 * @return TemplateResponse|RedirectResponse
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
	 * @param string $token
	 * @param string $callUser
	 * @param string $password
	 * @return TemplateResponse|RedirectResponse
	 * @throws HintException
	 */
	protected function pageHandler(
		string $token = '',
		string $callUser = '',
		string $password = '',
		string $email = '',
		#[SensitiveParameter]
		string $accessToken = '',
	): Response {
		$bruteForceToken = $token;
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return $this->guestEnterRoom($token, $password, $email, $accessToken);
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
					$password = $password !== '' ? $password : (string)$this->talkSession->getPasswordForRoom($token);

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

						$this->logger->debug('User "' . ($this->userId ?? 'ANONYMOUS') . '" throttled for accessing "' . $token . '"', ['app' => 'spreed-bfp']);
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
		$csp->addAllowedImageDomain('https://*.tile.openstreetmap.org');
		$csp->addAllowedMediaDomain('blob:');
		$csp->addAllowedWorkerSrcDomain('blob:');
		$csp->addAllowedWorkerSrcDomain("'self'");
		$csp->addAllowedChildSrcDomain('blob:');
		$csp->addAllowedChildSrcDomain("'self'");
		$csp->addAllowedScriptDomain('blob:');
		$csp->addAllowedScriptDomain("'self'");
		$csp->addAllowedScriptDomain("'wasm-unsafe-eval'");
		$csp->addAllowedConnectDomain('blob:');
		$csp->addAllowedConnectDomain("'self'");
		foreach ($this->talkConfig->getAllServerUrlsForCSP() as $server) {
			$csp->addAllowedConnectDomain($server);
		}

		$response->setContentSecurityPolicy($csp);
		if ($throttle) {
			// Logged-in user tried to access a chat they can not access
			$this->logger->debug('User "' . ($this->userId ?? 'ANONYMOUS') . '" throttled for accessing "' . $bruteForceToken . '"', ['app' => 'spreed-bfp']);
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
	#[BruteForceProtection(action: 'talkRecordingStatus')]
	public function recording(string $token): Response {
		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException $e) {
			$response = new NotFoundResponse();
			$this->logger->debug('Recording "' . ($this->userId ?? 'ANONYMOUS') . '" throttled for accessing "' . $token . '"', ['app' => 'spreed-bfp']);
			$response->throttle(['token' => $token, 'action' => 'talkRoomToken']);

			return $response;
		}

		if ($room->getCallRecording() !== Room::RECORDING_VIDEO_STARTING && $room->getCallRecording() !== Room::RECORDING_AUDIO_STARTING) {
			$response = new NotFoundResponse();
			$this->logger->debug('Recording "' . ($this->userId ?? 'ANONYMOUS') . '" throttled for accessing "' . $token . '"', ['app' => 'spreed-bfp']);
			$response->throttle(['token' => $token, 'action' => 'talkRecordingStatus']);
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
		$csp->addAllowedImageDomain('https://*.tile.openstreetmap.org');
		$csp->addAllowedMediaDomain('blob:');
		$csp->addAllowedWorkerSrcDomain('blob:');
		$csp->addAllowedWorkerSrcDomain("'self'");
		$csp->addAllowedChildSrcDomain('blob:');
		$csp->addAllowedChildSrcDomain("'self'");
		$csp->addAllowedScriptDomain('blob:');
		$csp->addAllowedScriptDomain("'self'");
		$csp->addAllowedScriptDomain("'wasm-unsafe-eval'");
		$csp->addAllowedConnectDomain('blob:');
		$csp->addAllowedConnectDomain("'self'");
		foreach ($this->talkConfig->getAllServerUrlsForCSP() as $server) {
			$csp->addAllowedConnectDomain($server);
		}
		$response->setContentSecurityPolicy($csp);

		return $response;
	}

	/**
	 * @return TemplateResponse|RedirectResponse
	 * @throws HintException
	 */
	protected function guestEnterRoom(
		string $token,
		string $password,
		string $email,
		#[SensitiveParameter]
		string $accessToken,
	): Response {
		if ($email && $accessToken) {
			return $this->invitedEmail(
				$token,
				$email,
				$accessToken,
			);
		}
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
			$password = $password !== '' ? $password : (string)$this->talkSession->getPasswordForRoom($token);

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
		$csp->addAllowedImageDomain('https://*.tile.openstreetmap.org');
		$csp->addAllowedMediaDomain('blob:');
		$csp->addAllowedWorkerSrcDomain('blob:');
		$csp->addAllowedWorkerSrcDomain("'self'");
		$csp->addAllowedChildSrcDomain('blob:');
		$csp->addAllowedChildSrcDomain("'self'");
		$csp->addAllowedScriptDomain('blob:');
		$csp->addAllowedScriptDomain("'self'");
		$csp->addAllowedScriptDomain("'wasm-unsafe-eval'");
		$csp->addAllowedConnectDomain('blob:');
		$csp->addAllowedConnectDomain("'self'");
		foreach ($this->talkConfig->getAllServerUrlsForCSP() as $server) {
			$csp->addAllowedConnectDomain($server);
		}
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @return TemplateResponse|RedirectResponse
	 * @throws HintException
	 */
	protected function invitedEmail(
		string $token,
		string $email,
		#[SensitiveParameter]
		string $accessToken,
	): Response {
		try {
			$actorId = hash('sha256', $email);
			$this->manager->getRoomByAccessToken(
				$token,
				Attendee::ACTOR_EMAILS,
				$actorId,
				$accessToken,
			);
			$this->talkSession->renewSessionId();
			$this->talkSession->setAuthedEmailActorIdForRoom($token, $actorId);
		} catch (RoomNotFoundException) {
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

		$this->publishInitialStateForGuest();
		$this->eventDispatcher->dispatchTyped(new RenderReferenceEvent());

		$response = new PublicTemplateResponse($this->appName, 'index', [
			'id-app-content' => '#content-vue',
			'id-app-navigation' => null,
		]);

		$response->setFooterVisible(false);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('https://*.tile.openstreetmap.org');
		$csp->addAllowedMediaDomain('blob:');
		$csp->addAllowedWorkerSrcDomain('blob:');
		$csp->addAllowedWorkerSrcDomain("'self'");
		$csp->addAllowedChildSrcDomain('blob:');
		$csp->addAllowedChildSrcDomain("'self'");
		$csp->addAllowedScriptDomain('blob:');
		$csp->addAllowedScriptDomain("'self'");
		$csp->addAllowedScriptDomain("'wasm-unsafe-eval'");
		$csp->addAllowedConnectDomain('blob:');
		$csp->addAllowedConnectDomain("'self'");
		foreach ($this->talkConfig->getAllServerUrlsForCSP() as $server) {
			$csp->addAllowedConnectDomain($server);
		}
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
