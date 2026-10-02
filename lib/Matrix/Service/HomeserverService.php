<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use Nextcloud\Matrix\Exception\MatrixException;
use OCA\Talk\Matrix\ClientFactory;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Model\HomeserverMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Administration of the allowed homeservers.
 */
class HomeserverService {
	public function __construct(
		private readonly HomeserverMapper $mapper,
		private readonly AccountMapper $accountMapper,
		private readonly ClientFactory $clientFactory,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	/** @return list<Homeserver> */
	public function getAll(bool $onlyEnabled = false): array {
		return $this->mapper->getAll($onlyEnabled);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function get(int $id): Homeserver {
		return $this->mapper->getById($id);
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
	 * @param array{name?: string, enabled?: bool, allowE2ee?: bool, allowUpload?: bool, baseUrl?: string} $changes
	 * @throws DoesNotExistException
	 * @throws MatrixException when a new base URL is not a homeserver
	 */
	public function update(int $id, array $changes): Homeserver {
		$homeserver = $this->mapper->getById($id);
		if (isset($changes['name']) && trim($changes['name']) !== '') {
			$homeserver->setName(mb_substr(trim($changes['name']), 0, 64));
		}
		if (isset($changes['enabled'])) {
			$homeserver->setEnabled((bool)$changes['enabled']);
		}
		if (isset($changes['allowE2ee'])) {
			$homeserver->setAllowE2ee((bool)$changes['allowE2ee']);
		}
		if (isset($changes['allowUpload'])) {
			$homeserver->setAllowUpload((bool)$changes['allowUpload']);
		}
		if (isset($changes['baseUrl']) && trim($changes['baseUrl']) !== '' && rtrim(trim($changes['baseUrl']), '/') !== $homeserver->getBaseUrl()) {
			$baseUrl = rtrim(trim($changes['baseUrl']), '/');
			$versions = $this->clientFactory->discovery()->validate($baseUrl);
			$homeserver->setBaseUrl($baseUrl);
			$homeserver->setVersionsJson(json_encode($versions, JSON_THROW_ON_ERROR));
			$homeserver->setVersionsFetched($this->timeFactory->getDateTime());
		}
		return $this->mapper->update($homeserver);
	}

	/**
	 * Re-fetch /versions ("Test connection").
	 * @throws DoesNotExistException
	 * @throws MatrixException
	 */
	public function refreshVersions(int $id): Homeserver {
		$homeserver = $this->mapper->getById($id);
		$versions = $this->clientFactory->discovery()->validate($homeserver->getBaseUrl());
		$homeserver->setVersionsJson(json_encode($versions, JSON_THROW_ON_ERROR));
		$homeserver->setVersionsFetched($this->timeFactory->getDateTime());
		return $this->mapper->update($homeserver);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws \InvalidArgumentException when accounts are still linked to it
	 */
	public function remove(int $id): void {
		$homeserver = $this->mapper->getById($id);
		if ($this->accountMapper->getByHomeserver($id) !== []) {
			throw new \InvalidArgumentException('accounts');
		}
		$this->mapper->delete($homeserver);
	}
}
