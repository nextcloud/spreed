<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;

class CanUseTalkMiddleware extends Middleware {
	public const TALK_DESKTOP_MIN_VERSION = '1.0.0';
	/**
	 * Actual version was 0.16.0 but otherwise the logic would differ in the version check,
	 * so we simply bump the recording consent version as well
	 */
	public const TALK_DESKTOP_MIN_VERSION_RECORDING_CONSENT = '1.0.0';
	public const TALK_DESKTOP_MIN_VERSION_E2EE_CALLS = '1.1.0';

	public const TALK_ANDROID_MIN_VERSION = '15.0.0';
	public const TALK_ANDROID_MIN_VERSION_RECORDING_CONSENT = '18.0.0';
	public const TALK_ANDROID_MIN_VERSION_E2EE_CALLS = '22.0.0';

	public const TALK_IOS_MIN_VERSION = '15.0.0';
	public const TALK_IOS_MIN_VERSION_RECORDING_CONSENT = '18.0.0';
	public const TALK_IOS_MIN_VERSION_E2EE_CALLS = '22.0.0';


	public function __construct(
		protected IUserSession $userSession,
		protected IGroupManager $groupManager,
		protected Config $talkConfig,
		protected IConfig $serverConfig,
		protected IRequest $request,
		protected IURLGenerator $url,
	) {
	}

	/**
	 * @throws CanNotUseTalkException
	 * @throws UnsupportedClientVersionException
	 */
	#[\Override]
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
			&& ((int)$this->serverConfig->getAppValue('spreed', 'start_calls')) === Room::START_CALL_NOONE) {
			throw new CanNotUseTalkException();
		}
	}

	/**
	 * @throws \Exception
	 */
	#[\Override]
	public function afterException(Controller $controller, string $methodName, \Exception $exception): Response {
		if ($exception instanceof UnsupportedClientVersionException) {
			if ($controller instanceof OCSController) {
				throw new OCSException($exception->getMinVersion(), Http::STATUS_UPGRADE_REQUIRED);
			}

			return new RedirectResponse($this->url->linkToDefaultPageUrl());
		}

		if ($exception instanceof CanNotUseTalkException
			|| $exception instanceof ForbiddenException) {
			if ($controller instanceof OCSController) {
				throw new OCSException($exception->getMessage(), Http::STATUS_FORBIDDEN);
			}

			return new RedirectResponse($this->url->linkToDefaultPageUrl());
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
			$versionRegex = IRequest::USER_AGENT_TALK_DESKTOP;
			$minVersion = self::TALK_DESKTOP_MIN_VERSION;

			if ($this->talkConfig->isCallEndToEndEncryptionEnabled()) {
				$minVersion = self::TALK_DESKTOP_MIN_VERSION_E2EE_CALLS;
			} elseif ($this->talkConfig->recordingConsentRequired()) {
				$minVersion = self::TALK_DESKTOP_MIN_VERSION_RECORDING_CONSENT;
			}
		} elseif ($client === 'android') {
			$versionRegex = IRequest::USER_AGENT_TALK_ANDROID;
			$minVersion = self::TALK_ANDROID_MIN_VERSION;

			if ($this->talkConfig->isCallEndToEndEncryptionEnabled()) {
				$minVersion = self::TALK_ANDROID_MIN_VERSION_E2EE_CALLS;
			} elseif ($this->talkConfig->recordingConsentRequired()) {
				$minVersion = self::TALK_ANDROID_MIN_VERSION_RECORDING_CONSENT;
			}
		} elseif ($client === 'ios') {
			$versionRegex = IRequest::USER_AGENT_TALK_IOS;
			$minVersion = self::TALK_IOS_MIN_VERSION;

			if ($this->talkConfig->isCallEndToEndEncryptionEnabled()) {
				$minVersion = self::TALK_IOS_MIN_VERSION_E2EE_CALLS;
			} elseif ($this->talkConfig->recordingConsentRequired()) {
				$minVersion = self::TALK_IOS_MIN_VERSION_RECORDING_CONSENT;
			}
		} else {
			return;
		}

		preg_match($versionRegex, $userAgent, $matches);

		if (isset($matches[1])) {
			$clientVersion = $matches[1];
			if (str_contains($clientVersion, '-')) {
				// Claim pre-releases being compatible with the final releases
				$clientVersion = substr($clientVersion, 0, strpos($clientVersion, '-'));
			}

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
