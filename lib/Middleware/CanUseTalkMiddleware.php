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
use OCA\Talk\Middleware\Exceptions\UnsupportedClientVersionException;
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
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

class CanUseTalkMiddleware extends Middleware {
	/**
	 * Talk Desktop user agent but with a regex match for the version
	 * @see IRequest::USER_AGENT_TALK_DESKTOP
	 */
	public const USER_AGENT_TALK_DESKTOP = '/^Mozilla\/5\.0 \((?!Android|iOS)[A-Za-z ]+\) Nextcloud-Talk v([^ ]*).*$/';
	public const TALK_DESKTOP_MIN_VERSION = '0.6.0';

	/**
	 * Talk Android user agent but with a regex match for the version
	 * @see IRequest::USER_AGENT_TALK_ANDROID
	 */
	public const USER_AGENT_TALK_ANDROID = '/^Mozilla\/5\.0 \(Android\) Nextcloud\-Talk v([^ ]*).*$/';
	public const TALK_ANDROID_MIN_VERSION = '15.0.0';

	/**
	 * Talk iOS user agent but with a regex match for the version
	 * @see IRequest::USER_AGENT_TALK_IOS
	 */
	public const USER_AGENT_TALK_IOS = '/^Mozilla\/5\.0 \(iOS\) Nextcloud\-Talk v([^ ]*).*$/';
	public const TALK_IOS_MIN_VERSION = '15.0.0';


	public function __construct(
		protected IUserSession $userSession,
		protected IGroupManager $groupManager,
		protected Config $talkConfig,
		protected IConfig $serverConfig,
		protected IRequest $request,
	) {
	}

	/**
	 * @throws CanNotUseTalkException
	 * @throws UnsupportedClientVersionException
	 */
	public function beforeController(Controller $controller, string $methodName): void {
		if ($this->request->isUserAgent([
			IRequest::USER_AGENT_TALK_DESKTOP,
			IRequest::USER_AGENT_TALK_ANDROID,
			IRequest::USER_AGENT_TALK_IOS,
		])) {
			if ($this->request->isUserAgent([IRequest::USER_AGENT_TALK_DESKTOP])) {
				$this->throwIfUnsupportedClientVersion('desktop', $this->request->getHeader('USER_AGENT'));
			} elseif ($this->request->isUserAgent([IRequest::USER_AGENT_TALK_ANDROID])) {
				$this->throwIfUnsupportedClientVersion('android', $this->request->getHeader('USER_AGENT'));
			} elseif ($this->request->isUserAgent([IRequest::USER_AGENT_TALK_IOS])) {
				$this->throwIfUnsupportedClientVersion('ios', $this->request->getHeader('USER_AGENT'));
			}
		}

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
		if ($exception instanceof UnsupportedClientVersionException) {
			if ($controller instanceof OCSController) {
				throw new OCSException($exception->getMinVersion(), Http::STATUS_UPGRADE_REQUIRED);
			}

			return new RedirectToDefaultAppResponse();
		}

		if ($exception instanceof CanNotUseTalkException ||
			$exception instanceof ForbiddenException) {
			if ($controller instanceof OCSController) {
				throw new OCSException($exception->getMessage(), Http::STATUS_FORBIDDEN);
			}

			return new RedirectToDefaultAppResponse();
		}

		throw $exception;
	}

	/**
	 * @param string $client
	 * @param string $userAgent
	 * @throws UnsupportedClientVersionException
	 */
	protected function throwIfUnsupportedClientVersion(string $client, string $userAgent): void {
		$configMinVersion = $this->serverConfig->getAppValue('spreed', 'minimum.supported.' . $client . '.version');

		if ($client === 'desktop') {
			$versionRegex = self::USER_AGENT_TALK_DESKTOP;
			$minVersion = self::TALK_DESKTOP_MIN_VERSION;
		} elseif ($client === 'android') {
			$versionRegex = self::USER_AGENT_TALK_ANDROID;
			$minVersion = self::TALK_ANDROID_MIN_VERSION;
		} elseif ($client === 'ios') {
			$versionRegex = self::USER_AGENT_TALK_IOS;
			$minVersion = self::TALK_IOS_MIN_VERSION;
		} else {
			return;
		}

		preg_match($versionRegex, $userAgent, $matches);

		if (isset($matches[1])) {
			$clientVersion = $matches[1];

			// API requirement and safety net
			if (version_compare($clientVersion, $minVersion, '<')) {
				throw new UnsupportedClientVersionException($minVersion);
			}

			// Admin option to be more pushy
			if ($configMinVersion && version_compare($clientVersion, $configMinVersion, '<')) {
				throw new UnsupportedClientVersionException($configMinVersion);
			}
		}
	}
}
