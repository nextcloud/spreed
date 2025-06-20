<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware;

use OCA\Talk\Controller\AEnvironmentAwareOCSController;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\PermissionsException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Federation\Authenticator;
use OCA\Talk\Manager;
use OCA\Talk\Middleware\Attribute\AllowWithoutParticipantWhenPendingInvitation;
use OCA\Talk\Middleware\Attribute\FederationSupported;
use OCA\Talk\Middleware\Attribute\RequireAuthenticatedParticipant;
use OCA\Talk\Middleware\Attribute\RequireFederatedParticipant;
use OCA\Talk\Middleware\Attribute\RequireLoggedInModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireLoggedInParticipant;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipantOrLoggedInAndListedConversation;
use OCA\Talk\Middleware\Attribute\RequirePermission;
use OCA\Talk\Middleware\Attribute\RequireReadWriteConversation;
use OCA\Talk\Middleware\Attribute\RequireRoom;
use OCA\Talk\Middleware\Exceptions\FederationUnsupportedFeatureException;
use OCA\Talk\Middleware\Exceptions\LobbyException;
use OCA\Talk\Middleware\Exceptions\NotAModeratorException;
use OCA\Talk\Middleware\Exceptions\ReadOnlyException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\InvitationMapper;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\BanService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCA\Talk\Webinary;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\Federation\ICloudIdManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Security\Bruteforce\IThrottler;
use OCP\Security\Bruteforce\MaxDelayReached;
use Psr\Log\LoggerInterface;

class InjectionMiddleware extends Middleware {
	public function __construct(
		protected IRequest $request,
		protected ParticipantService $participantService,
		protected TalkSession $talkSession,
		protected Manager $manager,
		protected ICloudIdManager $cloudIdManager,
		protected IThrottler $throttler,
		protected IURLGenerator $url,
		protected InvitationMapper $invitationMapper,
		protected Authenticator $federationAuthenticator,
		protected BanService $banService,
		protected LoggerInterface $logger,
		protected ?string $userId,
	) {
	}

	/**
	 * @throws FederationUnsupportedFeatureException
	 * @throws LobbyException
	 * @throws NotAModeratorException
	 * @throws ParticipantNotFoundException
	 * @throws PermissionsException
	 * @throws ReadOnlyException
	 * @throws ForbiddenException
	 * @throws RoomNotFoundException
	 */
	#[\Override]
	public function beforeController(Controller $controller, string $methodName): void {
		if (!$controller instanceof AEnvironmentAwareOCSController) {
			return;
		}

		$reflectionMethod = new \ReflectionMethod($controller, $methodName);

		$apiVersion = $this->request->getParam('apiVersion');
		$controller->setAPIVersion((int)substr($apiVersion, 1));

		if (!empty($reflectionMethod->getAttributes(AllowWithoutParticipantWhenPendingInvitation::class))) {
			try {
				$this->getRoomByInvite($controller);
				return;
			} catch (RoomNotFoundException|ParticipantNotFoundException) {
				// Falling back to bellow checks
			}
		}

		if (!empty($reflectionMethod->getAttributes(RequireAuthenticatedParticipant::class))) {
			$this->getLoggedInOrGuest($controller, false, requireFederationWhenNotLoggedIn: true);
		}

		if (!empty($reflectionMethod->getAttributes(RequireLoggedInParticipant::class))) {
			$this->getLoggedIn($controller, false);
		}

		if (!empty($reflectionMethod->getAttributes(RequireLoggedInModeratorParticipant::class))) {
			$this->getLoggedIn($controller, true);
		}

		if (!empty($reflectionMethod->getAttributes(RequireParticipantOrLoggedInAndListedConversation::class))) {
			$this->getLoggedInOrGuest($controller, false, true);
		}

		$attributes = $reflectionMethod->getAttributes(RequireFederatedParticipant::class);
		if (!empty($attributes)) {
			$sessionIdParameter = $this->readSessionIdParameterFromAttributes($attributes);
			$this->getLoggedInOrGuest($controller, false, sessionIdParameter: $sessionIdParameter);
		}

		if (!empty($reflectionMethod->getAttributes(RequireParticipant::class))) {
			$this->getLoggedInOrGuest($controller, false);
		}

		if (!empty($reflectionMethod->getAttributes(RequireModeratorParticipant::class))) {
			$this->getLoggedInOrGuest($controller, true);
		}

		if (!empty($reflectionMethod->getAttributes(RequireRoom::class))) {
			$this->getRoom($controller);
		}

		if (empty($reflectionMethod->getAttributes(FederationSupported::class))) {
			// When federation is not supported, the room needs to be local
			$this->checkFederationSupport($controller);
		}

		if (!empty($reflectionMethod->getAttributes(RequireReadWriteConversation::class))) {
			$this->checkReadOnlyState($controller);
		}

		if (!empty($reflectionMethod->getAttributes(RequireModeratorOrNoLobby::class))) {
			$this->checkLobbyState($controller);
		}

		$requiredPermissions = $reflectionMethod->getAttributes(RequirePermission::class);
		if ($requiredPermissions) {
			foreach ($requiredPermissions as $attribute) {
				/** @var RequirePermission $requirement */
				$requirement = $attribute->newInstance();
				$this->checkPermission($controller, $requirement->getPermission());
			}
		}
	}

	protected function readSessionIdParameterFromAttributes(array $attributes): ?string {
		foreach ($attributes as $attribute) {
			/** @var RequireFederatedParticipant $instance */
			$instance = $attribute->newInstance();
			if ($instance->getSessionIdParameter() !== null) {
				return $instance->getSessionIdParameter();
			}
		}
		return null;
	}

	/**
	 * @param AEnvironmentAwareOCSController $controller
	 * @throws ForbiddenException
	 */
	protected function getRoom(AEnvironmentAwareOCSController $controller): void {
		$token = $this->request->getParam('token');
		$room = $this->manager->getRoomByToken($token);
		$controller->setRoom($room);

		$this->banService->throwIfActorIsBanned($room, $this->userId);
	}

	/**
	 * @param AEnvironmentAwareOCSController $controller
	 * @param bool $moderatorRequired
	 * @throws NotAModeratorException
	 */
	protected function getLoggedIn(AEnvironmentAwareOCSController $controller, bool $moderatorRequired): void {
		$token = $this->request->getParam('token');
		$sessionId = $this->talkSession->getSessionForRoom($token);
		$room = $this->manager->getRoomForUserByToken($token, $this->userId, $sessionId);
		$controller->setRoom($room);

		$participant = $this->participantService->getParticipant($room, $this->userId, $sessionId);
		$controller->setParticipant($participant);

		$this->banService->throwIfActorIsBanned($room, $this->userId);

		if ($moderatorRequired && !$participant->hasModeratorPermissions(false)) {
			throw new NotAModeratorException();
		}
	}

	/**
	 * @param AEnvironmentAwareOCSController $controller
	 * @param bool $moderatorRequired
	 * @param bool $requireListedWhenNoParticipant
	 * @throws NotAModeratorException
	 * @throws ParticipantNotFoundException
	 */
	protected function getLoggedInOrGuest(
		AEnvironmentAwareOCSController $controller,
		bool $moderatorRequired,
		bool $requireListedWhenNoParticipant = false,
		bool $requireFederationWhenNotLoggedIn = false,
		?string $sessionIdParameter = null,
	): void {
		if ($requireFederationWhenNotLoggedIn && $this->userId === null && !$this->federationAuthenticator->isFederationRequest()) {
			throw new ParticipantNotFoundException();
		}

		if ($sessionIdParameter !== null && !$this->federationAuthenticator->isFederationRequest()) {
			throw new ParticipantNotFoundException();
		}

		$room = $controller->getRoom();
		$sessionId = null;
		if (!$room instanceof Room) {
			$token = $this->request->getParam('token');
			if (!$this->federationAuthenticator->isFederationRequest()) {
				$sessionId = $this->talkSession->getSessionForRoom($token);
				$room = $this->manager->getRoomForUserByToken($token, $this->userId, $sessionId);
			} else {
				$sessionId = $sessionIdParameter !== null ? $this->request->getParam($sessionIdParameter) : null;
				$room = $this->manager->getRoomByRemoteAccess($token, Attendee::ACTOR_FEDERATED_USERS, $this->federationAuthenticator->getCloudId(), $this->federationAuthenticator->getAccessToken(), $sessionId);

				// Get and set the participant already, so we don't retry public access
				$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_FEDERATED_USERS, $this->federationAuthenticator->getCloudId());

				if ($sessionIdParameter !== null && !$participant->getSession() instanceof Session) {
					// If a session is required, fail if we didn't find it
					throw new ParticipantNotFoundException();
				}

				$this->federationAuthenticator->authenticated($room, $participant);
				$controller->setParticipant($participant);
			}
			$controller->setRoom($room);
		}

		$participant = $controller->getParticipant();
		if (!$participant instanceof Participant) {
			$participant = null;
			if ($sessionId !== null) {
				try {
					$participant = $this->participantService->getParticipantBySession($room, $sessionId);
					$controller->setParticipant($participant);
				} catch (ParticipantNotFoundException $e) {
					// ignore and fall back in case a concurrent request might have
					// invalidated the session
				}
			}

			if ($participant === null) {
				if (!$requireListedWhenNoParticipant || !$this->manager->isRoomListableByUser($room, $this->userId)) {
					$participant = $this->participantService->getParticipant($room, $this->userId);
					$controller->setParticipant($participant);
				}
			}
		}

		$this->banService->throwIfActorIsBanned($room, $this->userId);

		if ($moderatorRequired && !$participant->hasModeratorPermissions()) {
			throw new NotAModeratorException();
		}
	}

	/**
	 * @param AEnvironmentAwareOCSController $controller
	 * @throws RoomNotFoundException
	 * @throws ParticipantNotFoundException
	 */
	protected function getRoomByInvite(AEnvironmentAwareOCSController $controller): void {
		if ($this->userId === null) {
			throw new ParticipantNotFoundException('No user available');
		}

		$room = $controller->getRoom();
		if (!$room instanceof Room) {
			$token = $this->request->getParam('token');
			$room = $this->manager->getRoomByToken($token);
		}

		$participant = $controller->getParticipant();
		if (!$participant instanceof Participant) {
			try {
				$invitation = $this->invitationMapper->getInvitationForUserByLocalRoom($room, $this->userId);
				$controller->setRoom($room);
				$controller->setInvitation($invitation);
			} catch (DoesNotExistException $e) {
				throw new ParticipantNotFoundException('No invite available', $e->getCode(), $e);
			}
		}
	}

	/**
	 * @param AEnvironmentAwareOCSController $controller
	 * @throws FederationUnsupportedFeatureException
	 */
	protected function checkFederationSupport(AEnvironmentAwareOCSController $controller): void {
		$room = $controller->getRoom();
		if ($room instanceof Room && $room->isFederatedConversation()) {
			throw new FederationUnsupportedFeatureException();
		}
	}

	/**
	 * @param AEnvironmentAwareOCSController $controller
	 * @throws ReadOnlyException
	 */
	protected function checkReadOnlyState(AEnvironmentAwareOCSController $controller): void {
		$room = $controller->getRoom();
		if (!$room instanceof Room || $room->getReadOnly() === Room::READ_ONLY) {
			throw new ReadOnlyException();
		}
		if ($room->getType() === Room::TYPE_CHANGELOG) {
			throw new ReadOnlyException();
		}
	}

	/**
	 * @throws PermissionsException
	 */
	protected function checkPermission(AEnvironmentAwareOCSController $controller, string $permission): void {
		$participant = $controller->getParticipant();
		if (!$participant instanceof Participant) {
			throw new PermissionsException();
		}

		if ($permission === RequirePermission::CHAT && !($participant->getPermissions() & Attendee::PERMISSIONS_CHAT)) {
			throw new PermissionsException();
		}
		if ($permission === RequirePermission::START_CALL && !($participant->getPermissions() & Attendee::PERMISSIONS_CALL_START)) {
			throw new PermissionsException();
		}
	}

	/**
	 * @throws LobbyException
	 */
	protected function checkLobbyState(AEnvironmentAwareOCSController $controller): void {
		try {
			$this->getLoggedInOrGuest($controller, true);
			return;
		} catch (NotAModeratorException|ParticipantNotFoundException) {
		}

		$participant = $controller->getParticipant();
		if ($participant instanceof Participant
			&& $participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE) {
			return;
		}

		$room = $controller->getRoom();
		if (!$room instanceof Room || $room->getLobbyState() !== Webinary::LOBBY_NONE) {
			throw new LobbyException();
		}
	}

	/**
	 * @throws \Exception
	 */
	#[\Override]
	public function afterException(Controller $controller, string $methodName, \Exception $exception): Response {
		if ($exception instanceof RoomNotFoundException
			|| $exception instanceof ParticipantNotFoundException) {
			if ($controller instanceof OCSController) {
				$reflectionMethod = new \ReflectionMethod($controller, $methodName);
				$attributes = $reflectionMethod->getAttributes(BruteForceProtection::class);

				if (!empty($attributes)) {
					foreach ($attributes as $attribute) {
						/** @var BruteForceProtection $protection */
						$protection = $attribute->newInstance();
						$action = $protection->getAction();

						if ($action === 'talkRoomToken') {
							$this->logger->debug('User "' . ($this->userId ?? 'ANONYMOUS') . '" throttled for accessing "' . ($this->request->getParam('token') ?? 'UNKNOWN') . '"', ['app' => 'spreed-bfp']);
							try {
								$this->throttler->sleepDelayOrThrowOnMax($this->request->getRemoteAddress(), $action);
							} catch (MaxDelayReached $e) {
								throw new OCSException($e->getMessage(), Http::STATUS_TOO_MANY_REQUESTS);
							}
							$this->throttler->registerAttempt($action, $this->request->getRemoteAddress(), [
								'token' => $this->request->getParam('token') ?? '',
							]);
						}
					}
				}

				throw new OCSException('', Http::STATUS_NOT_FOUND);
			}

			return new RedirectResponse($this->url->linkToDefaultPageUrl());
		}

		if ($exception instanceof CannotReachRemoteException) {
			if ($controller instanceof OCSController) {
				throw new OCSException('', Http::STATUS_UNPROCESSABLE_ENTITY);
			}

			return new RedirectResponse($this->url->linkToDefaultPageUrl());
		}

		if ($exception instanceof FederationUnsupportedFeatureException) {
			if ($controller instanceof OCSController) {
				throw new OCSException('', Http::STATUS_NOT_ACCEPTABLE);
			}

			return new RedirectResponse($this->url->linkToDefaultPageUrl());
		}

		if ($exception instanceof LobbyException) {
			if ($controller instanceof OCSController) {
				throw new OCSException('', Http::STATUS_PRECONDITION_FAILED);
			}

			return new RedirectResponse($this->url->linkToDefaultPageUrl());
		}

		if ($exception instanceof NotAModeratorException
			|| $exception instanceof ReadOnlyException
			|| $exception instanceof ForbiddenException
			|| $exception instanceof PermissionsException) {
			if ($controller instanceof OCSController) {
				throw new OCSException('', Http::STATUS_FORBIDDEN);
			}

			return new RedirectResponse($this->url->linkToDefaultPageUrl());
		}

		throw $exception;
	}
}
