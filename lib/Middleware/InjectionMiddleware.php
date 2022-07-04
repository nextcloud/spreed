<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Middleware;

use OCA\Talk\Controller\AEnvironmentAwareController;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\PermissionsException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Middleware\Attribute\RequireLoggedInModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireLoggedInParticipant;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipantOrLoggedInAndListedConversation;
use OCA\Talk\Middleware\Attribute\RequirePermission;
use OCA\Talk\Middleware\Attribute\RequireReadWriteConversation;
use OCA\Talk\Middleware\Attribute\RequireRoom;
use OCA\Talk\Middleware\Exceptions\LobbyException;
use OCA\Talk\Middleware\Exceptions\NotAModeratorException;
use OCA\Talk\Middleware\Exceptions\ReadOnlyException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCA\Talk\Webinary;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\RedirectToDefaultAppResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\Federation\ICloudIdManager;
use OCP\IRequest;
use OCP\Security\Bruteforce\IThrottler;

class InjectionMiddleware extends Middleware {
	protected bool $isTalkFederation = false;
	protected ?string $federationCloudId = null;

	public function __construct(
		protected IRequest $request,
		protected ParticipantService $participantService,
		protected TalkSession $talkSession,
		protected Manager $manager,
		protected ICloudIdManager $cloudIdManager,
		protected IThrottler $throttler,
		protected ?string $userId,
	) {
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @throws LobbyException
	 * @throws NotAModeratorException
	 * @throws ParticipantNotFoundException
	 * @throws PermissionsException
	 * @throws ReadOnlyException
	 * @throws RoomNotFoundException
	 */
	public function beforeController(Controller $controller, string $methodName): void {
		if (!$controller instanceof AEnvironmentAwareController) {
			return;
		}

		$this->isTalkFederation = (bool) $this->request->getHeader('X-Nextcloud-Federation');
		if ($this->isTalkFederation) {
			$controller->setRemoteAccess($this->getRemoteAccessActorId(), $this->getRemoteAccessToken());
		}

		$reflectionMethod = new \ReflectionMethod($controller, $methodName);

		$apiVersion = $this->request->getParam('apiVersion');
		$controller->setAPIVersion((int) substr($apiVersion, 1));

		if (!empty($reflectionMethod->getAttributes(RequireLoggedInParticipant::class))) {
			$this->getLoggedIn($controller, false);
		}

		if (!empty($reflectionMethod->getAttributes(RequireLoggedInModeratorParticipant::class))) {
			$this->getLoggedIn($controller, true);
		}

		if (!empty($reflectionMethod->getAttributes(RequireParticipantOrLoggedInAndListedConversation::class))) {
			$this->getLoggedInOrGuest($controller, false, true);
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

	/**
	 * @param AEnvironmentAwareController $controller
	 */
	protected function getRoom(AEnvironmentAwareController $controller): void {
		$token = $this->request->getParam('token');
		$room = $this->manager->getRoomByToken($token);
		$controller->setRoom($room);
	}

	/**
	 * @param AEnvironmentAwareController $controller
	 * @param bool $moderatorRequired
	 * @throws NotAModeratorException
	 */
	protected function getLoggedIn(AEnvironmentAwareController $controller, bool $moderatorRequired): void {
		$token = $this->request->getParam('token');
		$sessionId = $this->talkSession->getSessionForRoom($token);
		$room = $this->manager->getRoomForUserByToken($token, $this->userId, $sessionId);
		$controller->setRoom($room);

		$participant = $this->participantService->getParticipant($room, $this->userId, $sessionId);
		$controller->setParticipant($participant);

		if ($moderatorRequired && !$participant->hasModeratorPermissions(false)) {
			throw new NotAModeratorException();
		}
	}

	/**
	 * @param AEnvironmentAwareController $controller
	 * @param bool $moderatorRequired
	 * @param bool $requireListedWhenNoParticipant
	 * @throws NotAModeratorException
	 * @throws ParticipantNotFoundException
	 */
	protected function getLoggedInOrGuest(AEnvironmentAwareController $controller, bool $moderatorRequired, bool $requireListedWhenNoParticipant = false): void {
		$room = $controller->getRoom();
		if (!$room instanceof Room) {
			$token = $this->request->getParam('token');
			$sessionId = $this->talkSession->getSessionForRoom($token);
			if (!$this->isTalkFederation) {
				$room = $this->manager->getRoomForUserByToken($token, $this->userId, $sessionId);
			} else {
				$room = $this->manager->getRoomByRemoteAccess($token, Attendee::ACTOR_FEDERATED_USERS, $this->getRemoteAccessActorId(), $this->getRemoteAccessToken());

				// Get and set the participant already so we don't retry public access
				$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_FEDERATED_USERS, $this->getRemoteAccessActorId());
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

		if ($moderatorRequired && !$participant->hasModeratorPermissions()) {
			throw new NotAModeratorException();
		}
	}

	protected function getRemoteAccessActorId(): string {
		if ($this->federationCloudId !== null) {
			return $this->federationCloudId;
		}
		$authUser = $this->request->server['PHP_AUTH_USER'] ?? '';
		$authUser = urldecode($authUser);

		try {
			$cloudId = $this->cloudIdManager->resolveCloudId($authUser);
			$this->federationCloudId = $cloudId->getId();
		} catch (\InvalidArgumentException) {
			$this->federationCloudId = '';
		}

		return $this->federationCloudId;
	}

	protected function getRemoteAccessToken(): string {
		return $this->request->server['PHP_AUTH_PW'] ?? '';
	}

	/**
	 * @param AEnvironmentAwareController $controller
	 * @throws ReadOnlyException
	 */
	protected function checkReadOnlyState(AEnvironmentAwareController $controller): void {
		$room = $controller->getRoom();
		if (!$room instanceof Room || $room->getReadOnly() === Room::READ_ONLY) {
			throw new ReadOnlyException();
		}
		if ($room->getType() === Room::TYPE_CHANGELOG) {
			throw new ReadOnlyException();
		}
	}

	/**
	 * @param AEnvironmentAwareController $controller
	 * @throws PermissionsException
	 */
	protected function checkPermission(AEnvironmentAwareController $controller, string $permission): void {
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
	 * @param AEnvironmentAwareController $controller
	 * @throws LobbyException
	 */
	protected function checkLobbyState(AEnvironmentAwareController $controller): void {
		try {
			$this->getLoggedInOrGuest($controller, true);
			return;
		} catch (NotAModeratorException $e) {
		} catch (ParticipantNotFoundException $e) {
		}

		$participant = $controller->getParticipant();
		if ($participant instanceof Participant &&
			$participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE) {
			return;
		}

		$room = $controller->getRoom();
		if (!$room instanceof Room || $room->getLobbyState() !== Webinary::LOBBY_NONE) {
			throw new LobbyException();
		}
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @throws \Exception
	 * @return Response
	 */
	public function afterException($controller, $methodName, \Exception $exception): Response {
		if ($exception instanceof RoomNotFoundException ||
			$exception instanceof ParticipantNotFoundException) {
			if ($controller instanceof OCSController) {
				$reflectionMethod = new \ReflectionMethod($controller, $methodName);
				$attributes = $reflectionMethod->getAttributes(BruteForceProtection::class);

				if (!empty($attributes)) {
					foreach ($attributes as $attribute) {
						/** @var BruteForceProtection $protection */
						$protection = $attribute->newInstance();
						$action = $protection->getAction();

						if ('talkRoomToken' === $action) {
							$this->throttler->sleepDelay($this->request->getRemoteAddress(), $action);
							$this->throttler->registerAttempt($action, $this->request->getRemoteAddress(), [
								'token' => $this->request->getParam('token') ?? '',
							]);
						}
					}
				}

				throw new OCSException('', Http::STATUS_NOT_FOUND);
			}

			return new RedirectToDefaultAppResponse();
		}

		if ($exception instanceof LobbyException) {
			if ($controller instanceof OCSController) {
				throw new OCSException('', Http::STATUS_PRECONDITION_FAILED);
			}

			return new RedirectToDefaultAppResponse();
		}

		if ($exception instanceof NotAModeratorException ||
			$exception instanceof ReadOnlyException ||
			$exception instanceof PermissionsException) {
			if ($controller instanceof OCSController) {
				throw new OCSException('', Http::STATUS_FORBIDDEN);
			}

			return new RedirectToDefaultAppResponse();
		}

		throw $exception;
	}
}
