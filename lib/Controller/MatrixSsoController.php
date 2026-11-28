<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Matrix\Client\Exception\ForbiddenException;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Exception\TransportException;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;

/**
 * Links a Matrix account with single sign-on: the browser is sent to the
 * homeserver, which sends it back with a login token after the login at the
 * identity provider. Talk exchanges the token for its own device (m.login.token).
 */
#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class MatrixSsoController extends Controller {
	public const string SESSION_KEY = 'talk-matrix-sso';
	/** Seconds the login at the identity provider may take */
	public const int STATE_TTL = 600;
	public const string MODE_LINK = 'link';
	public const string MODE_RELOGIN = 'relogin';

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly ISession $session,
		private readonly AccountService $accountService,
		private readonly HomeserverService $homeserverService,
		private readonly ISecureRandom $secureRandom,
		private readonly IURLGenerator $urlGenerator,
		private readonly ITimeFactory $timeFactory,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Send the browser to the single sign-on of the homeserver
	 *
	 * @param string $homeserverId Id of the homeserver
	 * @param string $idp Id of the identity provider, empty for the default of the homeserver
	 * @param string $mode 'link' for a new account, 'relogin' after the homeserver ended the session
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UseSession]
	#[UserRateLimit(limit: 10, period: 300)]
	#[FrontpageRoute(verb: 'GET', url: '/matrix/sso/{homeserverId}/start', requirements: ['homeserverId' => '[0-9]+'])]
	public function start(string $homeserverId, string $idp = '', string $mode = self::MODE_LINK): RedirectResponse {
		/** @var IUser $user */
		$user = $this->userSession->getUser();
		if (!in_array($mode, [self::MODE_LINK, self::MODE_RELOGIN], true)) {
			return $this->result('error', 'mode');
		}

		if ($mode === self::MODE_RELOGIN) {
			$account = $this->accountService->getForUser($user->getUID());
			if ($account === null) {
				return $this->result('error', 'account');
			}
			$homeserverId = $account->getHomeserverId();
		}

		try {
			$homeserver = $this->homeserverService->get($homeserverId);
		} catch (DoesNotExistException) {
			return $this->result('error', 'homeserver');
		}
		if (!$homeserver->getEnabled()) {
			return $this->result('error', 'homeserver');
		}

		$flows = $this->homeserverService->getLoginFlows($homeserver);
		if (!$flows['sso']) {
			return $this->result('error', 'sso');
		}
		if ($idp !== '' && !in_array($idp, array_column($flows['identityProviders'], 'id'), true)) {
			return $this->result('error', 'idp');
		}

		$state = $this->secureRandom->generate(48, ISecureRandom::CHAR_ALPHANUMERIC);
		$this->session->set(self::SESSION_KEY, [
			'state' => $state,
			'user' => $user->getUID(),
			'homeserverId' => (string)$homeserver->getId(),
			'mode' => $mode,
			'time' => $this->timeFactory->getTime(),
		]);

		return new RedirectResponse($this->getSsoRedirectUrl($homeserver, $idp, $state));
	}

	/**
	 * The homeserver sends the browser back here with the login token
	 *
	 * @param string $state The state of the start, ties the answer to this session
	 * @param string $loginToken Login token of the homeserver, valid once and for a short time
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UseSession]
	#[BruteForceProtection(action: 'matrixLink')]
	#[FrontpageRoute(verb: 'GET', url: '/matrix/sso/callback')]
	public function callback(string $state = '', #[\SensitiveParameter] string $loginToken = ''): RedirectResponse {
		/** @var IUser $user */
		$user = $this->userSession->getUser();
		$expected = $this->session->get(self::SESSION_KEY);
		// Only one answer per start
		$this->session->remove(self::SESSION_KEY);

		if (!is_array($expected)
			|| !is_string($expected['state'] ?? null)
			|| $state === ''
			|| !hash_equals($expected['state'], $state)
			|| ($expected['user'] ?? null) !== $user->getUID()) {
			$response = $this->result('error', 'state');
			$response->throttle(['action' => 'matrixLink']);
			return $response;
		}
		if ($this->timeFactory->getTime() - (int)($expected['time'] ?? 0) > self::STATE_TTL) {
			return $this->result('error', 'expired');
		}
		if ($loginToken === '') {
			// Cancelled at the homeserver or the identity provider
			return $this->result('error', 'cancelled');
		}

		$homeserverId = (string)($expected['homeserverId'] ?? '');
		try {
			if (($expected['mode'] ?? self::MODE_LINK) === self::MODE_RELOGIN) {
				$account = $this->accountService->getForUser($user->getUID());
				if ($account === null || $account->getHomeserverId() !== $homeserverId) {
					return $this->result('error', 'account');
				}
				$this->accountService->reloginWithLoginToken($user, $account, $loginToken);
			} else {
				$this->accountService->linkWithLoginToken($user, $homeserverId, $loginToken);
			}
		} catch (\InvalidArgumentException $e) {
			$reason = in_array($e->getMessage(), ['not-allowed', 'already-linked', 'homeserver', 'user'], true) ? $e->getMessage() : 'matrix';
			return $this->result('error', $reason);
		} catch (ForbiddenException) {
			$response = $this->result('error', 'token');
			$response->throttle(['action' => 'matrixLink']);
			return $response;
		} catch (TransportException) {
			return $this->result('error', 'unreachable');
		} catch (MatrixException) {
			return $this->result('error', 'matrix');
		}

		return $this->result('ok');
	}

	private function getSsoRedirectUrl(Homeserver $homeserver, string $idp, string $state): string {
		$callback = $this->urlGenerator->linkToRouteAbsolute('spreed.MatrixSso.callback', ['state' => $state]);
		$path = '/_matrix/client/v3/login/sso/redirect' . ($idp !== '' ? '/' . rawurlencode($idp) : '');
		return rtrim($homeserver->getBaseUrl(), '/') . $path . '?redirectUrl=' . rawurlencode($callback);
	}

	/**
	 * Back to Talk, which opens the settings with the result
	 */
	private function result(string $result, string $reason = ''): RedirectResponse {
		$query = ['matrix_sso' => $result];
		if ($reason !== '') {
			$query['reason'] = $reason;
		}
		return new RedirectResponse($this->urlGenerator->linkToRoute('spreed.Page.index') . '?' . http_build_query($query));
	}
}
