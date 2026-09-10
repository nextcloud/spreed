<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use Nextcloud\Matrix\Client;
use Nextcloud\Matrix\Exception\MatrixException;
use Nextcloud\Matrix\Util\Identifier;
use OCA\Talk\Matrix\ClientFactory;
use OCA\Talk\Matrix\MatrixConfig;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Model\HomeserverMapper;
use OCA\Talk\Matrix\Model\MatrixMemberMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Defaults;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Linking, re-login and unlinking of Matrix accounts. Talk becomes a *device*
 * on the user's Matrix account; the password is used once and never stored.
 */
class AccountService {
	public function __construct(
		private readonly AccountMapper $mapper,
		private readonly HomeserverMapper $homeserverMapper,
		private readonly MatrixMemberMapper $memberMapper,
		private readonly ClientFactory $clientFactory,
		private readonly MatrixConfig $config,
		private readonly ITimeFactory $timeFactory,
		private readonly Defaults $defaults,
		private readonly INotificationManager $notificationManager,
		private readonly CryptoService $cryptoService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getForUser(string $userId): ?Account {
		try {
			return $this->mapper->getByUserId($userId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function getById(int $id): ?Account {
		try {
			return $this->mapper->getById($id);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function getByMxid(string $mxid): ?Account {
		try {
			return $this->mapper->getByMxid($mxid);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return list<Account> */
	public function getAll(): array {
		return $this->mapper->getAll();
	}

	/**
	 * @throws \InvalidArgumentException 'not-allowed' | 'already-linked' | 'homeserver' | 'user'
	 * @throws MatrixException login failure (M_FORBIDDEN = wrong credentials)
	 */
	public function link(IUser $user, int $homeserverId, string $matrixUser, #[\SensitiveParameter] string $password): Account {
		if (!$this->config->canUserLink($user)) {
			throw new \InvalidArgumentException('not-allowed');
		}
		if ($this->getForUser($user->getUID()) !== null) {
			throw new \InvalidArgumentException('already-linked');
		}
		$homeserver = $this->getEnabledHomeserver($homeserverId);
		$mxid = Identifier::normalizeUserId($matrixUser, $homeserver->getServerName());
		if (strcasecmp(Identifier::serverName($mxid), $homeserver->getServerName()) !== 0) {
			throw new \InvalidArgumentException('user');
		}

		$login = $this->clientFactory->anonymous($homeserver)->loginWithPassword(Identifier::localpart($mxid), $password, $this->deviceName());

		$account = new Account();
		$account->setUserId($user->getUID());
		$account->setHomeserverId($homeserver->getId());
		$account->setMxid($login->userId !== '' ? $login->userId : $mxid);
		$account->setAccessToken($this->clientFactory->encryptToken($login->accessToken));
		$account->setDeviceId($login->deviceId);
		$account->setStatus(Account::STATUS_ACTIVE);
		$account->setCreatedAt($this->timeFactory->getDateTime());
		$account = $this->mapper->insert($account);

		$this->ensureFilter($account);
		if ($homeserver->getAllowE2ee()) {
			$this->cryptoService->bootstrap($account);
		}
		return $account;
	}

	/**
	 * Re-login after the token was invalidated. Reuses the device id so the
	 * (future) E2EE identity survives.
	 *
	 * @throws MatrixException
	 */
	public function relogin(Account $account, #[\SensitiveParameter] string $password): Account {
		$homeserver = $this->clientFactory->getHomeserver($account->getHomeserverId());
		$login = $this->clientFactory->anonymous($homeserver)->loginWithPassword(Identifier::localpart($account->getMxid()), $password, $this->deviceName(), $account->getDeviceId());
		$account->setAccessToken($this->clientFactory->encryptToken($login->accessToken));
		$account->setDeviceId($login->deviceId);
		$account->setStatus(Account::STATUS_ACTIVE);
		$account->setLastError(null);
		$account->setFilterId(null);
		$account = $this->mapper->update($account);
		$this->ensureFilter($account);
		if ($homeserver->getAllowE2ee()) {
			$this->cryptoService->bootstrap($account);
		}
		$this->notificationManager->markProcessed($this->reloginNotification($account));
		return $account;
	}

	/**
	 * Log the device out on the homeserver (best effort) and delete everything
	 * belonging to the account. Conversation clean-up is done by the caller
	 * (LifecycleService) which knows about attendees and rooms.
	 */
	public function unlink(Account $account): void {
		try {
			if ($account->getAccessToken() !== null) {
				$this->clientFactory->forAccount($account, 10)->logout();
			}
		} catch (\Throwable $e) {
			$this->logger->info('Matrix logout during unlink failed for ' . $account->getMxid() . ': ' . $e->getMessage());
		}
		$this->memberMapper->clearAccount($account->getId());
		$this->cryptoService->wipe($account);
		$this->notificationManager->markProcessed($this->reloginNotification($account));
		$this->mapper->delete($account);
	}

	/**
	 * Called when the homeserver rejects the token: stop syncing and tell the user.
	 */
	public function markTokenInvalid(Account $account, string $reason): void {
		$account->setStatus(Account::STATUS_TOKEN_INVALID);
		$account->setLastError($reason);
		$account->setAccessToken(null);
		$this->mapper->update($account);

		$notification = $this->reloginNotification($account);
		$notification->setDateTime($this->timeFactory->getDateTime());
		$this->notificationManager->notify($notification);
	}

	public function update(Account $account): Account {
		return $this->mapper->update($account);
	}

	public function client(Account $account, int $timeout = 30): Client {
		return $this->clientFactory->forAccount($account, $timeout);
	}

	/**
	 * Server-side sync filter: lazy-loaded members, bounded timeline, no presence.
	 */
	public function ensureFilter(Account $account): string {
		if ($account->getFilterId() !== null && $account->getFilterId() !== '') {
			return $account->getFilterId();
		}
		try {
			$filterId = $this->client($account)->createFilter($account->getMxid(), Client::defaultFilter(min(200, max(20, $this->config->getHistoryEvents()))));
		} catch (MatrixException $e) {
			$this->logger->warning('Could not create Matrix sync filter for ' . $account->getMxid() . ': ' . $e->getMessage());
			return '';
		}
		$account->setFilterId($filterId);
		$this->mapper->update($account);
		return $filterId;
	}

	private function deviceName(): string {
		return MatrixConfig::DEVICE_NAME_PREFIX . ' (' . $this->defaults->getName() . ')';
	}

	/**
	 * @throws \InvalidArgumentException 'homeserver'
	 */
	private function getEnabledHomeserver(int $id): Homeserver {
		try {
			$homeserver = $this->homeserverMapper->getById($id);
		} catch (DoesNotExistException) {
			throw new \InvalidArgumentException('homeserver');
		}
		if (!$homeserver->getEnabled()) {
			throw new \InvalidArgumentException('homeserver');
		}
		return $homeserver;
	}

	private function reloginNotification(Account $account): \OCP\Notification\INotification {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('spreed')
			->setUser($account->getUserId())
			->setObject('matrix_account', (string)$account->getId())
			->setSubject('matrix_relogin', ['mxid' => $account->getMxid()]);
		return $notification;
	}
}
