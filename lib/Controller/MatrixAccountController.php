<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Config;
use OCA\Talk\Matrix\Client\Crypto\CryptoException;
use OCA\Talk\Matrix\Client\Crypto\Verification\SasVerification;
use OCA\Talk\Matrix\Client\Exception\ForbiddenException;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Exception\TransportException;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\CryptoService;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCA\Talk\Matrix\Service\VerificationService;
use OCA\Talk\Matrix\Sync\SyncService;
use OCP\AppFramework\Db\DoesNotExistException;
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
 * @psalm-import-type TalkMatrixVerification from \OCA\Talk\ResponseDefinitions
 */
class MatrixAccountController extends OCSController {
	/** Seconds to wait for answers of the other device when the state is polled */
	private const VERIFICATION_SYNC_BUDGET = 5;
	/** Seconds to sync when the device has no keys yet */
	private const KEYS_SYNC_BUDGET = 10;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly AccountService $accountService,
		private readonly HomeserverService $homeserverService,
		private readonly Config $talkConfig,
		private readonly CryptoService $cryptoService,
		private readonly VerificationService $verificationService,
		private readonly SyncService $syncService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the linked Matrix account and the homeservers an account can be linked on
	 *
	 * The access token of the linked account is checked with the homeserver,
	 * the result is reused for 5 minutes.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{canLink: bool, account: ?TalkMatrixAccount, connected: bool, homeservers: list<TalkMatrixHomeserver>}, array{}>
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

		$account = $this->accountService->getForUser($user->getUID());
		$connected = $account !== null && $this->accountService->checkConnection($account);

		return new DataResponse([
			'canLink' => $canLink,
			'account' => $this->formatAccount($account),
			'connected' => $connected,
			'homeservers' => $homeservers,
		]);
	}

	/**
	 * @return ?TalkMatrixAccount
	 */
	private function formatAccount(?Account $account, bool $forceVerified = false): ?array {
		if ($account === null) {
			return null;
		}
		return $account->jsonSerialize() + ['verified' => $this->getVerified($account, $forceVerified)];
	}

	private function getVerified(Account $account, bool $force): ?bool {
		if ($account->getStatus() !== Account::STATUS_ACTIVE) {
			return null;
		}
		try {
			return $this->cryptoService->isVerified($account, $this->accountService->getClient($account), $force);
		} catch (DoesNotExistException) {
			// The homeserver was removed
			return null;
		}
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
		} catch (MatrixException $e) {
			return $this->loginErrorResponse($e);
		}

		return new DataResponse($this->formatAccount($account), Http::STATUS_CREATED);
	}

	/**
	 * Check the connection to the homeserver again
	 *
	 * @return DataResponse<Http::STATUS_OK, array{account: TalkMatrixAccount, connected: bool}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: 'account'}, array{}>
	 *
	 * 200: Connection checked
	 * 404: No linked account
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 60)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/account/check', requirements: ['apiVersion' => '(v1)'])]
	public function checkConnection(): DataResponse {
		/** @var IUser $user */
		$user = $this->userSession->getUser();
		$account = $this->accountService->getForUser($user->getUID());
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}

		$connected = $this->accountService->checkConnection($account, true);
		return new DataResponse([
			'account' => $this->formatAccount($account, true),
			'connected' => $connected,
		]);
	}

	/**
	 * Log in again after the homeserver rejected the access token
	 *
	 * @param string $password Matrix password
	 * @return DataResponse<Http::STATUS_OK, TalkMatrixAccount, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 200: Logged in again
	 * 400: Homeserver is not available anymore, the login belongs to another Matrix user or was rejected by the homeserver
	 * 401: Wrong credentials
	 * 403: User is not allowed to link an account
	 * 404: No linked account
	 * 502: Homeserver unreachable
	 */
	#[NoAdminRequired]
	#[BruteForceProtection(action: 'matrixLink')]
	#[UserRateLimit(limit: 10, period: 300)]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/matrix/account', requirements: ['apiVersion' => '(v1)'])]
	public function reloginAccount(#[\SensitiveParameter] string $password): DataResponse {
		/** @var IUser $user */
		$user = $this->userSession->getUser();
		$account = $this->accountService->getForUser($user->getUID());
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}

		try {
			$account = $this->accountService->relogin($user, $account, $password);
		} catch (\InvalidArgumentException $e) {
			$status = $e->getMessage() === 'not-allowed' ? Http::STATUS_FORBIDDEN : Http::STATUS_BAD_REQUEST;
			return new DataResponse(['error' => $e->getMessage()], $status);
		} catch (MatrixException $e) {
			return $this->loginErrorResponse($e);
		}

		return new DataResponse($this->formatAccount($account));
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

	/**
	 * Verify the device of Talk with another device of the user
	 *
	 * The other devices are asked to verify, the user accepts on one of them
	 * and compares the emoji. That device then cross-signs the device of Talk,
	 * so clients share the keys of encrypted rooms with it.
	 *
	 * @return DataResponse<Http::STATUS_CREATED, TalkMatrixVerification, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: 'account'|'keys'|'matrix'|'unavailable'}, array{}>
	 *
	 * 201: Verification requested
	 * 400: Encryption is not available on the server or the device of Talk could not publish its keys
	 * 404: No active linked account
	 * 502: The homeserver could not be reached
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 300)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/account/verification', requirements: ['apiVersion' => '(v1)'])]
	public function startVerification(): DataResponse {
		$account = $this->getActiveAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->cryptoService->isAvailable()) {
			return new DataResponse(['error' => 'unavailable'], Http::STATUS_BAD_REQUEST);
		}
		$signingKey = $this->cryptoService->getSigningKey($account);
		if ($signingKey === null) {
			// The keys of the device are created by the first sync after encryption became available
			$this->syncService->syncAccount($account, self::KEYS_SYNC_BUDGET);
			$signingKey = $this->cryptoService->getSigningKey($account);
		}
		if ($signingKey === null) {
			return new DataResponse(['error' => 'keys'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$verification = $this->verificationService->start($account, $this->accountService->getClient($account), $signingKey);
		} catch (MatrixException) {
			return new DataResponse(['error' => 'matrix'], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse($this->formatVerification($verification), Http::STATUS_CREATED);
	}

	/**
	 * Get the state of the verification, after checking the homeserver for answers of the other device
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkMatrixVerification, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: 'account'|'verification'}, array{}>
	 *
	 * 200: Verification returned
	 * 404: No active linked account or no verification
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/matrix/account/verification', requirements: ['apiVersion' => '(v1)'])]
	public function getVerification(): DataResponse {
		$account = $this->getActiveAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		$verification = $this->verificationService->get($account);
		if ($verification === null) {
			return new DataResponse(['error' => 'verification'], Http::STATUS_NOT_FOUND);
		}
		if (!$verification->isFinished()) {
			// Skipped while another sync of the account runs, which handles the answers as well
			$this->syncService->syncAccount($account, self::VERIFICATION_SYNC_BUDGET);
			$verification = $this->verificationService->get($account) ?? $verification;
		}
		return new DataResponse($this->formatVerification($verification));
	}

	/**
	 * Confirm that the emoji match the ones of the other device
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkMatrixVerification, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: 'account'|'state'|'matrix'}, array{}>
	 *
	 * 200: Verification confirmed
	 * 400: There is nothing to confirm
	 * 404: No active linked account
	 * 502: The homeserver could not be reached
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/matrix/account/verification', requirements: ['apiVersion' => '(v1)'])]
	public function confirmVerification(): DataResponse {
		$account = $this->getActiveAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		try {
			$verification = $this->verificationService->confirm($account, $this->accountService->getClient($account));
		} catch (CryptoException) {
			return new DataResponse(['error' => 'state'], Http::STATUS_BAD_REQUEST);
		} catch (MatrixException) {
			return new DataResponse(['error' => 'matrix'], Http::STATUS_BAD_GATEWAY);
		}
		if ($verification->state === SasVerification::STATE_DONE) {
			// Their mac had already arrived, confirming finished the verification right away
			$this->cryptoService->forgetVerified($account);
		}
		return new DataResponse($this->formatVerification($verification));
	}

	/**
	 * Cancel the verification, for example because the emoji do not match
	 *
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: 'account'|'matrix'}, array{}>
	 *
	 * 200: Verification cancelled
	 * 404: No active linked account
	 * 502: The homeserver could not be reached
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/matrix/account/verification', requirements: ['apiVersion' => '(v1)'])]
	public function cancelVerification(): DataResponse {
		$account = $this->getActiveAccount();
		if ($account === null) {
			return new DataResponse(['error' => 'account'], Http::STATUS_NOT_FOUND);
		}
		try {
			$this->verificationService->cancel($account, $this->accountService->getClient($account));
		} catch (MatrixException) {
			return new DataResponse(['error' => 'matrix'], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse(null);
	}

	private function getActiveAccount(): ?Account {
		/** @var IUser $user */
		$user = $this->userSession->getUser();
		$account = $this->accountService->getForUser($user->getUID());
		return $account?->getStatus() === Account::STATUS_ACTIVE ? $account : null;
	}

	/**
	 * @return TalkMatrixVerification
	 */
	private function formatVerification(SasVerification $verification): array {
		$showEmoji = in_array($verification->state, [SasVerification::STATE_KEYS_EXCHANGED, SasVerification::STATE_MAC_SENT], true);
		return [
			'state' => $verification->state,
			'emoji' => $showEmoji ? $verification->emoji() : [],
			'reason' => $verification->cancelReason,
		];
	}

	/**
	 * @return DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 */
	private function loginErrorResponse(MatrixException $e): DataResponse {
		if ($e instanceof ForbiddenException) {
			$response = new DataResponse(['error' => 'credentials'], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['action' => 'matrixLink']);
			return $response;
		}
		if ($e instanceof TransportException) {
			return new DataResponse(['error' => 'unreachable'], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse(['error' => $e->getErrcode() !== '' ? $e->getErrcode() : 'matrix'], Http::STATUS_BAD_REQUEST);
	}
}
