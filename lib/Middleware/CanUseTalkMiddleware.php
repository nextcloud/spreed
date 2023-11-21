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

use OCA\Talk\Config;
use OCA\Talk\Controller\HostedSignalingServerController;
use OCA\Talk\Controller\RecordingController;
use OCA\Talk\Controller\SignalingController;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Middleware\Attribute\RequireCallEnabled;
use OCA\Talk\Middleware\Exceptions\CanNotUseTalkException;
use OCA\Talk\Room;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectToDefaultAppResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

class CanUseTalkMiddleware extends Middleware {
	private IUserSession $userSession;
	private IGroupManager $groupManager;
	private Config $talkConfig;
	private IConfig $serverConfig;

	public function __construct(
		IUserSession $userSession,
		IGroupManager $groupManager,
		Config $talkConfig,
		IConfig $serverConfig,
	) {
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->talkConfig = $talkConfig;
		$this->serverConfig = $serverConfig;
	}

	/**
	 * @throws CanNotUseTalkException
	 */
	public function beforeController(Controller $controller, string $methodName): void {
		$user = $this->userSession->getUser();
		if ($user instanceof IUser && $this->talkConfig->isDisabledForUser($user)) {
			if ($methodName === 'getWelcomeMessage'
				&& ($controller instanceof SignalingController
					|| $controller instanceof RecordingController)
				&& $this->groupManager->isAdmin($user->getUID())) {
				return;
			}

			if ($controller instanceof HostedSignalingServerController
				&& $this->groupManager->isAdmin($user->getUID())) {
				return;
			}

			throw new CanNotUseTalkException();
		}

		$reflectionMethod = new \ReflectionMethod($controller, $methodName);
		$hasAttribute = !empty($reflectionMethod->getAttributes(RequireCallEnabled::class));

		if ($hasAttribute
			&& ((int) $this->serverConfig->getAppValue('spreed', 'start_calls')) === Room::START_CALL_NOONE) {
			throw new CanNotUseTalkException();
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
		if ($exception instanceof CanNotUseTalkException ||
			$exception instanceof ForbiddenException) {
			if ($controller instanceof OCSController) {
				throw new OCSException($exception->getMessage(), Http::STATUS_FORBIDDEN);
			}

			return new RedirectToDefaultAppResponse();
		}

		throw $exception;
	}
}
