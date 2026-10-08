<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Config;
use OCA\Talk\Matrix\Client\Exception\ForbiddenException;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Exception\TransportException;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * @psalm-import-type TalkMatrixAccount from \OCA\Talk\ResponseDefinitions
 * @psalm-import-type TalkMatrixHomeserver from \OCA\Talk\ResponseDefinitions
 */
class MatrixAccountController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly AccountService $accountService,
		private readonly HomeserverService $homeserverService,
		private readonly Config $talkConfig,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the linked Matrix account and the homeservers an account can be linked on
	 *
	 * @return DataResponse<Http::STATUS_OK, array{canLink: bool, account: ?TalkMatrixAccount, homeservers: list<TalkMatrixHomeserver>}, array{}>
	 *
	 * 200: Account information returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/matrix/account', requirements: ['apiVersion' => '(v1)'])]
	public function getAccount(): DataResponse {
		/** @var IUser $user */
		$user = $this->userSession->getUser();
		$canLink = $this->talkConfig->canLinkMatrixAccount($user);
		$homeservers = [];
		if ($canLink) {
			$homeservers = array_values(array_map(
				static fn (Homeserver $homeserver): array => $homeserver->jsonSerialize(),
				array_filter($this->homeserverService->getAll(), static fn (Homeserver $homeserver): bool => $homeserver->getEnabled()),
			));
		}

		return new DataResponse([
			'canLink' => $canLink,
			'account' => $this->accountService->getForUser($user->getUID())?->jsonSerialize(),
			'homeservers' => $homeservers,
		]);
	}

	/**
	 * Link a Matrix account with a password login, the password is not stored
	 *
	 * @param string $homeserverId Id of the homeserver
	 * @param string $user Matrix localpart or full user id
	 * @param string $password Matrix password
	 * @return DataResponse<Http::STATUS_CREATED, TalkMatrixAccount, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_UNAUTHORIZED|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 201: Account linked
	 * 400: Invalid homeserver or user, account already linked or login rejected by the homeserver
	 * 401: Wrong credentials
	 * 403: User is not allowed to link an account
	 * 502: Homeserver unreachable
	 */
	#[NoAdminRequired]
	#[BruteForceProtection(action: 'matrixLink')]
	#[UserRateLimit(limit: 10, period: 300)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/account', requirements: ['apiVersion' => '(v1)'])]
	public function linkAccount(string $homeserverId, string $user, #[\SensitiveParameter] string $password): DataResponse {
		/** @var IUser $ncUser */
		$ncUser = $this->userSession->getUser();
		try {
			$account = $this->accountService->link($ncUser, $homeserverId, $user, $password);
		} catch (\InvalidArgumentException $e) {
			$status = $e->getMessage() === 'not-allowed' ? Http::STATUS_FORBIDDEN : Http::STATUS_BAD_REQUEST;
			return new DataResponse(['error' => $e->getMessage()], $status);
		} catch (ForbiddenException) {
			$response = new DataResponse(['error' => 'credentials'], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['action' => 'matrixLink']);
			return $response;
		} catch (TransportException) {
			return new DataResponse(['error' => 'unreachable'], Http::STATUS_BAD_GATEWAY);
		} catch (MatrixException $e) {
			return new DataResponse(['error' => $e->getErrcode() !== '' ? $e->getErrcode() : 'matrix'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($account->jsonSerialize(), Http::STATUS_CREATED);
	}

	/**
	 * Unlink the Matrix account and log Talk out on the homeserver
	 *
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: 'account'}, array{}>
	 *
	 * 200: Account unlinked
	 * 404: No linked account
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/matrix/account', requirements: ['apiVersion' => '(v1)'])]
	public function unlinkAccount(): DataResponse {
		/** @var IUser $user */
		$user = $this->userSession->getUser();
		$account = $this->accountService->getForUser($user->getUID());
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		$this->accountService->unlink($account);
		return new DataResponse(null);
	}
}
