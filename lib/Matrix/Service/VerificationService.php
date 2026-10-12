<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Crypto\CryptoException;
use OCA\Talk\Matrix\Client\Crypto\Verification\SasVerification;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;

/**
 * Emoji verification of the device of the server with another device of the
 * user, so that device cross-signs it and clients share room keys with it.
 * The user starts it in Talk and compares the emoji with the other device.
 */
class VerificationService {
	/** Seconds after which a verification is given up, as in the specification */
	public const TIMEOUT = 600;

	public function __construct(
		private readonly AccountMapper $accountMapper,
		private readonly ICrypto $crypto,
		private readonly ILockingProvider $lockingProvider,
		private readonly ITimeFactory $timeFactory,
		private readonly ISecureRandom $secureRandom,
	) {
	}

	/**
	 * Ask all other devices of the user to verify the device of the server
	 *
	 * @param string $ourEd25519 Signing key of the device of the server
	 * @throws MatrixException
	 */
	public function start(Account $account, Client $client, string $ourEd25519): SasVerification {
		return $this->change($account, function () use ($account, $client, $ourEd25519): SasVerification {
			$current = $this->load($account);
			if ($current !== null && !$current->isFinished()) {
				$current->cancel('m.user', 'Replaced by a new verification');
				$this->send($account, $client, $current);
			}
			$transactionId = $this->secureRandom->generate(32, ISecureRandom::CHAR_ALPHANUMERIC);
			$verification = SasVerification::request($transactionId, $account->getMxid(), $account->getDeviceId(), $ourEd25519, $this->timeFactory->getTime());
			$this->send($account, $client, $verification);
			return $verification;
		});
	}

	/**
	 * The running or last verification, null when there is none
	 */
	public function get(Account $account): ?SasVerification {
		return $this->load($account);
	}

	/**
	 * The user confirmed that the emoji match
	 *
	 * @throws CryptoException when there is nothing to confirm
	 * @throws MatrixException
	 */
	public function confirm(Account $account, Client $client): SasVerification {
		return $this->change($account, function () use ($account, $client): SasVerification {
			$verification = $this->load($account) ?? throw new CryptoException('No verification');
			$verification->confirm();
			$this->send($account, $client, $verification);
			return $verification;
		});
	}

	/**
	 * The user cancelled the verification or the emoji do not match
	 *
	 * @throws MatrixException
	 */
	public function cancel(Account $account, Client $client): void {
		$this->change($account, function () use ($account, $client): ?SasVerification {
			$verification = $this->load($account);
			if ($verification === null || $verification->isFinished()) {
				return $verification;
			}
			if ($verification->state === SasVerification::STATE_KEYS_EXCHANGED) {
				$verification->reject();
			} else {
				$verification->cancel('m.user', 'Cancelled by the user');
			}
			$this->send($account, $client, $verification);
			return $verification;
		});
	}

	/**
	 * Apply a verification event another device of the user sent to the device of the server
	 *
	 * @param array<string, mixed> $content
	 * @param \Closure(string): ?string $deviceEd25519 Signing key of a device of the user
	 * @return ?SasVerification The state after the event was applied, for the caller to react on STATE_DONE
	 * @throws MatrixException
	 */
	public function handle(Account $account, Client $client, string $type, array $content, \Closure $deviceEd25519): ?SasVerification {
		return $this->change($account, function () use ($account, $client, $type, $content, $deviceEd25519): ?SasVerification {
			$verification = $this->load($account);
			if ($verification === null || ($content['transaction_id'] ?? null) !== $verification->transactionId) {
				return $verification;
			}
			$verification->handle($type, $content, $deviceEd25519);
			$this->send($account, $client, $verification);
			return $verification;
		});
	}

	/**
	 * @return ?SasVerification Running verifications run into the timeout
	 */
	protected function load(Account $account): ?SasVerification {
		$stored = $this->accountMapper->getVerification((string)$account->getId());
		if ($stored === null) {
			return null;
		}
		$verification = SasVerification::unpickle($this->crypto->decrypt($stored));
		if (!$verification->isFinished() && $verification->startedAt + self::TIMEOUT < $this->timeFactory->getTime()) {
			$verification->state = SasVerification::STATE_CANCELLED;
			$verification->cancelReason = 'Timed out';
		}
		return $verification;
	}

	/**
	 * Events go to the device that answered, before that to all devices of the user
	 *
	 * @throws MatrixException
	 */
	protected function send(Account $account, Client $client, SasVerification $verification): void {
		foreach ($verification->takeOutgoing() as $event) {
			$deviceId = $verification->theirDeviceId ?? '*';
			$client->sendToDevice($event['type'], [$account->getMxid() => [$deviceId => $event['content']]], $this->secureRandom->generate(24, ISecureRandom::CHAR_ALPHANUMERIC));
		}
	}

	/**
	 * Run a change of the verification exclusively, as the sync and requests
	 * of the user change it, and store the result
	 *
	 * @template T of ?SasVerification
	 * @param \Closure(): T $change
	 * @return T
	 */
	protected function change(Account $account, \Closure $change): ?SasVerification {
		$lock = 'talk/matrix/verification/' . $account->getId();
		for ($attempt = 0; ; $attempt++) {
			try {
				$this->lockingProvider->acquireLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
				break;
			} catch (LockedException $e) {
				if ($attempt >= 20) {
					throw $e;
				}
				usleep(100000);
			}
		}

		try {
			$verification = $change();
			if ($verification !== null) {
				$this->accountMapper->setVerification((string)$account->getId(), $this->crypto->encrypt($verification->pickle()));
			}
			return $verification;
		} finally {
			$this->lockingProvider->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}
}
