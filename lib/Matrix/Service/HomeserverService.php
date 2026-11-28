<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCA\Talk\CachePrefix;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\ClientFactory;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Model\HomeserverMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

/**
 * Administration of the allowed homeservers.
 */
class HomeserverService {
	/** Seconds the login types of a homeserver are reused */
	public const LOGIN_FLOWS_TTL = 3600;

	private ICache $loginFlowsCache;

	public function __construct(
		private readonly HomeserverMapper $mapper,
		private readonly AccountMapper $accountMapper,
		private readonly ClientFactory $clientFactory,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
		ICacheFactory $cacheFactory,
	) {
		$this->loginFlowsCache = $cacheFactory->createDistributed(CachePrefix::MATRIX_LOGIN_FLOWS);
	}

	/** @return list<Homeserver> */
	public function getAll(): array {
		return $this->mapper->getAll();
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function get(string $id): Homeserver {
		return $this->mapper->getById($id);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getByServerName(string $serverName): Homeserver {
		return $this->mapper->getByServerName(strtolower(trim($serverName)));
	}

	/**
	 * Resolve + validate the server and store it.
	 *
	 * @param string $serverName e.g. `matrix.org`
	 * @param string|null $baseUrl manual override of the client API base URL (skips .well-known)
	 * @throws \InvalidArgumentException when the name is taken or invalid
	 * @throws MatrixException when the server cannot be reached / is not a homeserver
	 */
	public function add(string $name, string $serverName, ?string $baseUrl = null): Homeserver {
		$serverName = strtolower(trim($serverName));
		$name = trim($name) !== '' ? trim($name) : $serverName;
		if ($serverName === '') {
			throw new \InvalidArgumentException('server_name');
		}
		try {
			$this->mapper->getByServerName($serverName);
			throw new \InvalidArgumentException('exists');
		} catch (DoesNotExistException) {
		}

		$discovery = $this->clientFactory->discovery();
		if ($baseUrl !== null && trim($baseUrl) !== '') {
			$baseUrl = rtrim(trim($baseUrl), '/');
			$versions = $discovery->validate($baseUrl);
		} else {
			$result = $discovery->discover($serverName);
			$baseUrl = $result['base_url'];
			$versions = $result['versions'];
		}

		$homeserver = new Homeserver();
		$homeserver->setName(mb_substr($name, 0, 64));
		$homeserver->setServerName($serverName);
		$homeserver->setBaseUrl($baseUrl);
		$homeserver->setEnabled(true);
		$homeserver->setVersionsJson(json_encode($versions, JSON_THROW_ON_ERROR));
		$homeserver->setVersionsFetched($this->timeFactory->getDateTime());
		return $this->mapper->insert($homeserver);
	}

	/**
	 * @param array{name?: string, enabled?: bool, baseUrl?: string} $changes
	 * @throws DoesNotExistException
	 * @throws MatrixException when a new base URL is not a homeserver
	 */
	public function update(string $id, array $changes): Homeserver {
		$homeserver = $this->mapper->getById($id);
		if (isset($changes['name']) && trim($changes['name']) !== '') {
			$homeserver->setName(mb_substr(trim($changes['name']), 0, 64));
		}
		if (isset($changes['enabled'])) {
			$homeserver->setEnabled((bool)$changes['enabled']);
		}
		if (isset($changes['baseUrl']) && trim($changes['baseUrl']) !== '' && rtrim(trim($changes['baseUrl']), '/') !== $homeserver->getBaseUrl()) {
			$baseUrl = rtrim(trim($changes['baseUrl']), '/');
			$versions = $this->clientFactory->discovery()->validate($baseUrl);
			$homeserver->setBaseUrl($baseUrl);
			$homeserver->setVersionsJson(json_encode($versions, JSON_THROW_ON_ERROR));
			$homeserver->setVersionsFetched($this->timeFactory->getDateTime());
			$this->loginFlowsCache->remove($id);
		}
		return $this->mapper->update($homeserver);
	}

	/**
	 * How users can log in on the homeserver, reused for LOGIN_FLOWS_TTL seconds.
	 * When the homeserver can not be asked, password login is assumed.
	 *
	 * @return array{password: bool, sso: bool, identityProviders: list<array{id: string, name: string}>}
	 */
	public function getLoginFlows(Homeserver $homeserver): array {
		$key = (string)$homeserver->getId();
		$cached = $this->loginFlowsCache->get($key);
		if (is_array($cached) && isset($cached['password'], $cached['sso'], $cached['identityProviders'])) {
			/** @var array{password: bool, sso: bool, identityProviders: list<array{id: string, name: string}>} $cached */
			return $cached;
		}

		try {
			$flows = $this->clientFactory->forHomeserver($homeserver, null, 15)->getLoginFlows();
		} catch (MatrixException $e) {
			$this->logger->info('Login types of Matrix homeserver ' . $homeserver->getServerName() . ' could not be fetched', ['exception' => $e]);
			return ['password' => true, 'sso' => false, 'identityProviders' => []];
		}
		$this->loginFlowsCache->set($key, $flows, self::LOGIN_FLOWS_TTL);
		return $flows;
	}

	/**
	 * Re-fetch /versions ("Test connection").
	 * @throws DoesNotExistException
	 * @throws MatrixException
	 */
	public function refreshVersions(string $id): Homeserver {
		$homeserver = $this->mapper->getById($id);
		$versions = $this->clientFactory->discovery()->validate($homeserver->getBaseUrl());
		$homeserver->setVersionsJson(json_encode($versions, JSON_THROW_ON_ERROR));
		$homeserver->setVersionsFetched($this->timeFactory->getDateTime());
		$this->loginFlowsCache->remove($id);
		return $this->mapper->update($homeserver);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws \InvalidArgumentException 'accounts' when accounts are still linked on it
	 */
	public function remove(string $id): void {
		$homeserver = $this->mapper->getById($id);
		if ($this->accountMapper->hasAccountsOnHomeserver($id)) {
			throw new \InvalidArgumentException('accounts');
		}
		$this->mapper->delete($homeserver);
	}
}
