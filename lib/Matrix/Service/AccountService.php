<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCA\Talk\Config;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Util\Identifier;
use OCA\Talk\Matrix\ClientFactory;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\HomeserverMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Defaults;
use OCP\IUser;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Linking and unlinking of Matrix accounts. Talk becomes a device on the
 * user's Matrix account, the password is used once and never stored.
 */
class AccountService {
	public function __construct(
		private readonly AccountMapper $mapper,
		private readonly HomeserverMapper $homeserverMapper,
		private readonly ClientFactory $clientFactory,
		private readonly Config $config,
		private readonly ICrypto $crypto,
		private readonly Defaults $defaults,
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

	/**
	 * @param string $offset Only return accounts of users after this user id
	 * @return list<Account>
	 */
	public function getAll(string $offset = '', int $limit = 1000): array {
		return $this->mapper->getAll($offset, $limit);
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
		try {
			$homeserver = $this->homeserverMapper->getById($homeserverId);
		} catch (DoesNotExistException) {
			throw new \InvalidArgumentException('homeserver');
		}
		if (!$homeserver->getEnabled()) {
			throw new \InvalidArgumentException('homeserver');
		}

		try {
			$mxid = Identifier::normalizeUserId($matrixUser, $homeserver->getServerName());
		} catch (\InvalidArgumentException) {
			throw new \InvalidArgumentException('user');
		}
		if (strcasecmp(Identifier::serverName($mxid), $homeserver->getServerName()) !== 0) {
			throw new \InvalidArgumentException('user');
		}

		$login = $this->clientFactory->forHomeserver($homeserver)
			->loginWithPassword(Identifier::localpart($mxid), $password, 'Nextcloud Talk (' . $this->defaults->getName() . ')');

		$account = new Account();
		$account->setUserId($user->getUID());
		$account->setHomeserverId((string)$homeserver->getId());
		$account->setMxid($login->userId !== '' ? $login->userId : $mxid);
		$account->setAccessToken($this->crypto->encrypt($login->accessToken));
		$account->setDeviceId($login->deviceId);
		return $this->mapper->insert($account);
	}

	/**
	 * Log the device out on the homeserver (best effort) and forget the account
	 */
	public function unlink(Account $account): void {
		try {
			$homeserver = $this->homeserverMapper->getById($account->getHomeserverId());
			$this->clientFactory->forHomeserver($homeserver, $this->crypto->decrypt($account->getAccessToken()), 10)->logout();
		} catch (\Exception $e) {
			$this->logger->info('Matrix logout during unlink failed for ' . $account->getMxid(), ['exception' => $e]);
		}
		$this->mapper->delete($account);
	}

	public function unlinkUser(string $userId): void {
		$account = $this->getForUser($userId);
		if ($account !== null) {
			$this->unlink($account);
		}
	}
}
