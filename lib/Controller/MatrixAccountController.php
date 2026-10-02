<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use Nextcloud\Matrix\Exception\ForbiddenException;
use Nextcloud\Matrix\Exception\MatrixException;
use Nextcloud\Matrix\Exception\TransportException;
use OCA\Talk\Matrix\MatrixConfig;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCA\Talk\Matrix\Service\LifecycleService;
use OCA\Talk\Matrix\Sync\SyncService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Personal Matrix account: link, re-login, unlink, status, sync now.
 *
 * @psalm-import-type TalkMatrixAccount from \OCA\Talk\ResponseDefinitions
 */
class MatrixAccountController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly AccountService $accountService,
		private readonly HomeserverService $homeserverService,
		private readonly LifecycleService $lifecycleService,
		private readonly SyncService $syncService,
		private readonly MatrixConfig $config,
		private readonly \OCA\Talk\Matrix\Service\CryptoService $cryptoService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the current user's Matrix account link and the homeservers they may link to
	 *
	 * @return DataResponse<Http::STATUS_OK, array{enabled: bool, canLink: bool, account: ?TalkMatrixAccount, homeservers: list<array{id: int, name: string, serverName: string}>, device: ?array<string, mixed>, verification: ?array<string, mixed>}, array{}>
	 *
	 * 200: Account information returned
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/matrix/account', requirements: ['apiVersion' => '(v1)'])]
	public function getAccount(): DataResponse {
		$user = $this->userSession->getUser();
		$account = $user instanceof IUser ? $this->accountService->getForUser($user->getUID()) : null;
		$homeservers = [];
		if ($this->config->canUserLink($user)) {
			foreach ($this->homeserverService->getAll(true) as $homeserver) {
				$homeservers[] = $homeserver->toPublicArray();
			}
		}
		$device = null;
		$verification = null;
		if ($account !== null && $account->isActive()) {
			try {
				$device = $this->cryptoService->deviceStatus($account);
				$verification = $this->cryptoService->verificationStatus($account);
			} catch (\Throwable $e) {
				$device = ['error' => $e->getMessage()];
			}
		}
		return new DataResponse([
			'enabled' => $this->config->isEnabled(),
			'canLink' => $this->config->canUserLink($user),
			'account' => $account?->toUserArray(),
			'homeservers' => $homeservers,
			'device' => $device,
			'verification' => $verification,
		]);
	}

	/**
	 * Start verifying this Talk device from another of the user's Matrix clients
	 *
	 * @return DataResponse<Http::STATUS_OK, array{transactionId: string, state: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Verification requested; the other clients show the request
	 * 404: No linked account
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/account/verification', requirements: ['apiVersion' => '(v1)'])]
	public function startVerification(): DataResponse {
		$account = $this->currentAccount();
		if ($account === null || !$account->isActive()) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($this->cryptoService->startVerification($account));
	}

	/**
	 * Poll the running verification (state, emoji once keys are exchanged)
	 *
	 * @return DataResponse<Http::STATUS_OK, ?array{transactionId: string, state: string, theirDeviceId: ?string, emoji: list<array{emoji: string, name: string}>, decimal: ?array{0: int, 1: int, 2: int}, reason: ?string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Current verification state (null when none is running)
	 * 404: No linked account
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/matrix/account/verification', requirements: ['apiVersion' => '(v1)'])]
	public function verificationStatus(): DataResponse {
		$account = $this->currentAccount();
		if ($account === null || !$account->isActive()) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		// Pick up to-device events that arrived since the last background sync
		$this->syncService->syncAccount($account, 5, 0);
		return new DataResponse($this->cryptoService->verificationStatus($account));
	}

	/**
	 * Confirm (emoji match) or reject the verification
	 *
	 * @param bool $matches Whether the emoji shown match the other device
	 * @return DataResponse<Http::STATUS_OK, ?array{transactionId: string, state: string, theirDeviceId: ?string, emoji: list<array{emoji: string, name: string}>, decimal: ?array{0: int, 1: int, 2: int}, reason: ?string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Verification advanced
	 * 404: No linked account or no running verification
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/matrix/account/verification', requirements: ['apiVersion' => '(v1)'])]
	public function confirmVerification(bool $matches): DataResponse {
		$account = $this->currentAccount();
		if ($account === null || !$account->isActive()) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		$result = $this->cryptoService->confirmVerification($account, $matches);
		if ($result === null) {
			return new DataResponse(['error' => 'verification'], Http::STATUS_NOT_FOUND);
		}
		$this->syncService->syncAccount($account, 5, 0);
		return new DataResponse($this->cryptoService->verificationStatus($account) ?? $result);
	}

	/**
	 * Cancel the running verification
	 *
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Cancelled
	 * 404: No linked account
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/matrix/account/verification', requirements: ['apiVersion' => '(v1)'])]
	public function cancelVerification(): DataResponse {
		$account = $this->currentAccount();
		if ($account === null || !$account->isActive()) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		$this->cryptoService->cancelVerification($account);
		return new DataResponse(null);
	}

	/**
	 * Link a Matrix account (password login). The password is used once and never stored.
	 *
	 * @param int $homeserverId Id of an admin-configured homeserver
	 * @param string $user Matrix localpart or full user id
	 * @param string $password Matrix password
	 * @return DataResponse<Http::STATUS_CREATED, TalkMatrixAccount, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_UNAUTHORIZED|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 201: Account linked
	 * 400: Invalid input or already linked
	 * 401: Wrong credentials
	 * 403: User is not allowed to link an account
	 * 502: Homeserver unreachable
	 */
	#[NoAdminRequired]
	#[BruteForceProtection(action: 'matrixLink')]
	#[UserRateLimit(limit: 10, period: 300)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/account', requirements: ['apiVersion' => '(v1)'])]
	public function link(int $homeserverId, string $user, #[\SensitiveParameter] string $password): DataResponse {
		$ncUser = $this->userSession->getUser();
		if (!$ncUser instanceof IUser) {
			return new DataResponse(['error' => 'user'], Http::STATUS_FORBIDDEN);
		}
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

		// First sync inline (bounded) so the rooms show up immediately when the homeserver is quick
		$this->syncService->syncAccount($account, 10, 0);
		$account = $this->accountService->getById($account->getId()) ?? $account;
		return new DataResponse($account->toUserArray(), Http::STATUS_CREATED);
	}

	/**
	 * Log in again after the homeserver invalidated the token
	 *
	 * @param string $password Matrix password
	 * @return DataResponse<Http::STATUS_OK, TalkMatrixAccount, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_UNAUTHORIZED|Http::STATUS_BAD_GATEWAY|Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Re-login successful
	 * 401: Wrong credentials
	 * 404: No linked account
	 * 502: Homeserver unreachable
	 */
	#[NoAdminRequired]
	#[BruteForceProtection(action: 'matrixLink')]
	#[UserRateLimit(limit: 10, period: 300)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/matrix/account', requirements: ['apiVersion' => '(v1)'])]
	public function relogin(#[\SensitiveParameter] string $password): DataResponse {
		$account = $this->currentAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		try {
			$account = $this->accountService->relogin($account, $password);
		} catch (ForbiddenException) {
			$response = new DataResponse(['error' => 'credentials'], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['action' => 'matrixLink']);
			return $response;
		} catch (TransportException) {
			return new DataResponse(['error' => 'unreachable'], Http::STATUS_BAD_GATEWAY);
		} catch (MatrixException $e) {
			return new DataResponse(['error' => $e->getErrcode() !== '' ? $e->getErrcode() : 'matrix'], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($account->toUserArray());
	}

	/**
	 * Unlink the Matrix account (logs the Talk device out on the homeserver)
	 *
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Account unlinked
	 * 404: No linked account
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/matrix/account', requirements: ['apiVersion' => '(v1)'])]
	public function unlink(): DataResponse {
		$account = $this->currentAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		$this->lifecycleService->unlink($account);
		return new DataResponse(null);
	}

	/**
	 * Trigger a sync now
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkMatrixAccount, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Sync ran (or was already running)
	 * 404: No linked account
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/account/sync', requirements: ['apiVersion' => '(v1)'])]
	public function sync(): DataResponse {
		$account = $this->currentAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		$this->syncService->syncAccount($account, 10, 0);
		$account = $this->accountService->getById($account->getId()) ?? $account;
		return new DataResponse($account->toUserArray());
	}

	/**
	 * Restore encrypted history from the server-side key backup
	 *
	 * @param string $recoveryKey Recovery key (only needed when no verified device shared the backup key yet)
	 * @return DataResponse<Http::STATUS_OK, array{imported: int, sessions: int, decrypted: int}, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 200: Backup restored
	 * 400: No backup, wrong recovery key or no backup key available
	 * 404: No linked account
	 * 502: Homeserver error
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 5, period: 300)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/account/backup', requirements: ['apiVersion' => '(v1)'])]
	public function restoreBackup(#[\SensitiveParameter] string $recoveryKey = ''): DataResponse {
		$account = $this->currentAccount();
		if ($account === null || !$account->isActive()) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		try {
			return new DataResponse($this->cryptoService->restoreBackup($account, $recoveryKey !== '' ? $recoveryKey : null));
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (MatrixException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}
	}

	private function currentAccount(): ?Account {
		$user = $this->userSession->getUser();
		return $user instanceof IUser ? $this->accountService->getForUser($user->getUID()) : null;
	}
}
