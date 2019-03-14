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

namespace OCA\Spreed\Middleware;

use OC\AppFramework\Utility\ControllerMethodReflector;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Middleware\Exceptions\CanNotUseTalkException;
use OCA\Spreed\Room;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\RedirectToDefaultAppResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

class InjectionMiddleware extends Middleware {

	/** @var IRequest */
	private $request;
	/** @var ControllerMethodReflector */
	private $reflector;
	/** @var IUserSession */
	private $userSession;
	/** @var TalkSession */
	private $talkSession;
	/** @var Manager */
	private $manager;
	/** @var ?string */
	private $userId;

	public function __construct(IRequest $request,
								ControllerMethodReflector $reflector,
								TalkSession $talkSession,
								Manager $manager,
								?string $userId) {
		$this->request = $request;
		$this->reflector = $reflector;
		$this->talkSession = $talkSession;
		$this->manager = $manager;
		$this->userId = $userId;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @throws RoomNotFoundException
	 * @throws ParticipantNotFoundException
	 */
	public function beforeController($controller, $methodName): void {
		if ($this->reflector->hasAnnotation('RequireLoggedInParticipant')) {
			$token = $this->request->getParam('token');

			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$controller->setRoom($room);
			$participant = $room->getParticipant($this->userId);
			$controller->setParticipant($participant);
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
				throw new OCSException('', Http::STATUS_NOT_FOUND);
			}

			return new RedirectToDefaultAppResponse();
		}

		throw $exception;
	}

	protected function getRoomForParticipantByToken(string $token): Room {
		return $this->manager->getRoomForParticipantByToken($token, $this->userId);
	}

	protected function getRoomForSession(string $token): Room {
		return $this->manager->getRoomForSession(
			$this->userId,
			$this->talkSession->getSessionForRoom($token)
		);
	}
}
