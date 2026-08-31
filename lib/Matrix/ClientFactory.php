<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix;

use GuzzleHttp\Psr7\HttpFactory;
use Nextcloud\Matrix\Client;
use Nextcloud\Matrix\Discovery;
use Nextcloud\Matrix\Http\Transport;
use OCA\Talk\Matrix\Adapter\HttpClient;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Model\HomeserverMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Http\Client\IClientService;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Builds library clients for a homeserver / a linked account. The only place
 * that decrypts access tokens.
 */
class ClientFactory {
	/** @var array<int, Homeserver> */
	private array $homeservers = [];

	public function __construct(
		private readonly IClientService $clientService,
		private readonly ICrypto $crypto,
		private readonly HomeserverMapper $homeserverMapper,
		private readonly LoggerInterface $logger,
	) {
	}

	public function forHomeserver(Homeserver $homeserver, int $timeout = 30): Client {
		$factory = new HttpFactory();
		$transport = new Transport($homeserver->getBaseUrl(), new HttpClient($this->clientService, $timeout), $factory, $factory, $this->logger);
		$client = new Client($transport);
		$versions = $homeserver->getVersions();
		if ($versions !== null) {
			$client->setVersions($versions);
		}
		return $client;
	}

	/**
	 * @throws DoesNotExistException when the homeserver was removed
	 */
	public function forAccount(Account $account, int $timeout = 30): Client {
		$homeserver = $this->getHomeserver($account->getHomeserverId());
		return $this->forHomeserver($homeserver, $timeout)->withAccessToken($this->decryptToken($account));
	}

	/** Anonymous client for login requests. */
	public function anonymous(Homeserver $homeserver): Client {
		return $this->forHomeserver($homeserver, 30);
	}

	public function discovery(): Discovery {
		return new Discovery(new HttpClient($this->clientService, 15), new HttpFactory());
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getHomeserver(int $id): Homeserver {
		if (!isset($this->homeservers[$id])) {
			$this->homeservers[$id] = $this->homeserverMapper->getById($id);
		}
		return $this->homeservers[$id];
	}

	public function encryptToken(#[\SensitiveParameter] string $token): string {
		return $this->crypto->encrypt($token);
	}

	public function decryptToken(Account $account): ?string {
		$encrypted = $account->getAccessToken();
		if ($encrypted === null || $encrypted === '') {
			return null;
		}
		return $this->crypto->decrypt($encrypted);
	}
}
