<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCA\Talk\CachePrefix;
use OCA\Talk\Config;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Exception\UnknownTokenException;
use OCA\Talk\Matrix\Client\Util\Identifier;
use OCA\Talk\Matrix\ClientFactory;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Model\HomeserverMapper;
use OCA\Talk\Matrix\Sync\RoomSyncService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Defaults;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Linking, re-login and unlinking of Matrix accounts. Talk becomes a device on
 * the user's Matrix account, the password is used once and never stored.
 */
class AccountService {
	/** Seconds the result of a connection check is reused */
	public const CONNECTION_CHECK_TTL = 300;

	private ICache $connectionCache;

	public function __construct(
		private readonly AccountMapper $mapper,
		private readonly HomeserverMapper $homeserverMapper,
		private readonly ClientFactory $clientFactory,
		private readonly RoomSyncService $roomSyncService,
		private readonly Config $config,
		private readonly ICrypto $crypto,
		private readonly Defaults $defaults,
		private readonly INotificationManager $notificationManager,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
		ICacheFactory $cacheFactory,
	) {
		$this->connectionCache = $cacheFactory->createDistributed(CachePrefix::MATRIX_CONNECTION);
	}

	public function getForUser(string $userId): ?Account {
		try {
			return $this->mapper->getByUserId($userId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @param string $offset Only return accounts of users after this user id
	 * @param string $homeserverId Only return accounts on this homeserver, empty for all
	 * @return list<Account>
	 */
	public function getAll(string $offset = '', int $limit = 1000, string $homeserverId = ''): array {
		return $this->mapper->getAll($offset, $limit, $homeserverId);
	}

	/**
	 * @throws \InvalidArgumentException 'not-allowed' | 'already-linked' | 'homeserver' | 'user'
	 * @throws MatrixException login failure, ForbiddenException for wrong credentials
	 */
	public function link(IUser $user, string $homeserverId, string $matrixUser, #[\SensitiveParameter] string $password): Account {
		if (!$this->config->canLinkMatrixAccount($user)) {
			throw new \InvalidArgumentException('not-allowed');
		}
		if ($this->getForUser($user->getUID()) !== null) {
			throw new \InvalidArgumentException('already-linked');
		}
		$homeserver = $this->getEnabledHomeserver($homeserverId);

		try {
			$mxid = Identifier::normalizeUserId($matrixUser, $homeserver->getServerName());
		} catch (\InvalidArgumentException) {
			throw new \InvalidArgumentException('user');
		}
		if (strcasecmp(Identifier::serverName($mxid), $homeserver->getServerName()) !== 0) {
			throw new \InvalidArgumentException('user');
		}

		$login = $this->clientFactory->forHomeserver($homeserver)
			->loginWithPassword(Identifier::localpart($mxid), $password, $this->getDeviceName());

		$account = new Account();
		$account->setUserId($user->getUID());
		$account->setHomeserverId((string)$homeserver->getId());
		$account->setMxid($login->userId !== '' ? $login->userId : $mxid);
		$account->setAccessToken($this->crypto->encrypt($login->accessToken));
		$account->setDeviceId($login->deviceId);
		return $this->mapper->insert($account);
	}

	/**
	 * Log in again after the homeserver rejected the access token, the device id
	 * is reused so the device of Talk stays the same
	 *
	 * @throws \InvalidArgumentException 'not-allowed' | 'homeserver' | 'user'
	 * @throws MatrixException login failure, ForbiddenException for wrong credentials
	 */
	public function relogin(IUser $user, Account $account, #[\SensitiveParameter] string $password): Account {
		if (!$this->config->canLinkMatrixAccount($user)) {
			throw new \InvalidArgumentException('not-allowed');
		}
		$homeserver = $this->getEnabledHomeserver($account->getHomeserverId());

		$client = $this->clientFactory->forHomeserver($homeserver);
		$login = $client->loginWithPassword(Identifier::localpart($account->getMxid()), $password, $this->getDeviceName(), $account->getDeviceId());
		if ($login->userId !== '' && strcasecmp($login->userId, $account->getMxid()) !== 0) {
			try {
				$client->withAccessToken($login->accessToken)->logout();
			} catch (MatrixException $e) {
				$this->logger->info('Matrix logout of mismatching re-login failed for ' . $login->userId, ['exception' => $e]);
			}
			throw new \InvalidArgumentException('user');
		}

		$account->setAccessToken($this->crypto->encrypt($login->accessToken));
		$account->setDeviceId($login->deviceId);
		$account->setStatus(Account::STATUS_ACTIVE);
		$account->setLastError(null);
		$account = $this->mapper->update($account);
		$this->notificationManager->markProcessed($this->getReloginNotification($account));
		$this->connectionCache->set((string)$account->getId(), 1, self::CONNECTION_CHECK_TTL);
		return $account;
	}

	/**
	 * Ask the homeserver whether the access token is still valid and mark the
	 * account when it is not. The result is reused for CONNECTION_CHECK_TTL
	 * seconds unless $force is set.
	 *
	 * @return bool Whether the homeserver accepted the access token
	 */
	public function checkConnection(Account $account, bool $force = false): bool {
		if ($account->getStatus() !== Account::STATUS_ACTIVE) {
			return false;
		}

		$cacheKey = (string)$account->getId();
		if (!$force) {
			$cached = $this->connectionCache->get($cacheKey);
			if ($cached !== null) {
				return (bool)$cached;
			}
		}

		try {
			$this->getClient($account, 10)->whoami();
			$connected = true;
		} catch (UnknownTokenException $e) {
			$this->markTokenInvalid($account, $e->getMessage());
			return false;
		} catch (\Exception $e) {
			$this->logger->info('Could not check the Matrix access token of ' . $account->getMxid(), ['exception' => $e]);
			$connected = false;
		}

		$this->connectionCache->set($cacheKey, $connected ? 1 : 0, self::CONNECTION_CHECK_TTL);
		return $connected;
	}

	/**
	 * Stop using the access token and ask the user to log in again
	 */
	public function markTokenInvalid(Account $account, string $reason): Account {
		$account->setStatus(Account::STATUS_TOKEN_INVALID);
		$account->setLastError($reason);
		$account->setAccessToken('');
		$account = $this->mapper->update($account);
		$this->connectionCache->remove((string)$account->getId());

		$notification = $this->getReloginNotification($account);
		$notification->setDateTime($this->timeFactory->getDateTime());
		$this->notificationManager->notify($notification);
		return $account;
	}

	/**
	 * Log the device out on the homeserver (best effort), remove the user from
	 * the mirrored Matrix rooms and forget the account
	 */
	public function unlink(Account $account): void {
		if ($account->getAccessToken() !== '') {
			try {
				$this->getClient($account, 10)->logout();
			} catch (\Exception $e) {
				$this->logger->info('Matrix logout during unlink failed for ' . $account->getMxid(), ['exception' => $e]);
			}
		}
		$this->notificationManager->markProcessed($this->getReloginNotification($account));
		$this->roomSyncService->removeAccount($account);
		$this->mapper->delete($account);
	}

	/**
	 * @return int Number of unlinked accounts
	 */
	public function unlinkAllOnHomeserver(string $homeserverId): int {
		$count = 0;
		while ($accounts = $this->mapper->getByHomeserver($homeserverId, 1000)) {
			foreach ($accounts as $account) {
				$this->unlink($account);
				$count++;
			}
		}
		return $count;
	}

	public function unlinkUser(string $userId): void {
		$account = $this->getForUser($userId);
		if ($account !== null) {
			$this->unlink($account);
		}
	}

	/**
	 * Client authenticated with the access token of the account
	 *
	 * @throws DoesNotExistException when the homeserver was removed
	 */
	public function getClient(Account $account, int $timeout = 30): Client {
		$homeserver = $this->homeserverMapper->getById($account->getHomeserverId());
		return $this->clientFactory->forHomeserver($homeserver, $this->crypto->decrypt($account->getAccessToken()), $timeout);
	}

	/**
	 * @throws \InvalidArgumentException 'homeserver'
	 */
	private function getEnabledHomeserver(string $homeserverId): Homeserver {
		try {
			$homeserver = $this->homeserverMapper->getById($homeserverId);
		} catch (DoesNotExistException) {
			throw new \InvalidArgumentException('homeserver');
		}
		if (!$homeserver->getEnabled()) {
			throw new \InvalidArgumentException('homeserver');
		}
		return $homeserver;
	}

	private function getDeviceName(): string {
		return 'Nextcloud Talk (' . $this->defaults->getName() . ')';
	}

	private function getReloginNotification(Account $account): INotification {
		return $this->notificationManager->createNotification()
			->setApp('spreed')
			->setUser($account->getUserId())
			->setObject('matrix_account', (string)$account->getId())
			->setSubject('matrix_relogin', ['mxid' => $account->getMxid()]);
	}
}
