<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Federation;

class TrustedServers {
	/** after a user list was exchanged at least once successfully */
	public const STATUS_OK = 1;
	/** waiting for shared secret or initial user list exchange */
	public const STATUS_PENDING = 2;
	/** something went wrong, misconfigured server, software bug,... user interaction needed */
	public const STATUS_FAILURE = 3;
	/** remote server revoked access */
	public const STATUS_ACCESS_REVOKED = 4;
	public function __construct(\OCA\Federation\DbHandler $dbHandler, \OCP\Http\Client\IClientService $httpClientService, \Psr\Log\LoggerInterface $logger, \OCP\BackgroundJob\IJobList $jobList, \OCP\Security\ISecureRandom $secureRandom, \OCP\IConfig $config, \OCP\EventDispatcher\IEventDispatcher $dispatcher, \OCP\AppFramework\Utility\ITimeFactory $timeFactory) {
	}
	/**
	 * Add server to the list of trusted servers
	 */
	public function addServer(string $url) : int {
	}
	/**
	 * Get shared secret for the given server
	 */
	public function getSharedSecret(string $url) : string {
	}
	/**
	 * Add shared secret for the given server
	 */
	public function addSharedSecret(string $url, string $sharedSecret) : void {
	}
	/**
	 * Remove server from the list of trusted servers
	 */
	public function removeServer(int $id) : void {
	}
	/**
	 * Get all trusted servers
	 *
	 * @return list<array{id: int, url: string, url_hash: string, shared_secret: ?string, status: int, sync_token: ?string}>
	 * @throws Exception
	 */
	public function getServers() {
	}
	/**
	 * Check if given server is a trusted Nextcloud server
	 */
	public function isTrustedServer(string $url) : bool {
	}
	/**
	 * Set server status
	 */
	public function setServerStatus(string $url, int $status) : void {
	}
	/**
	 * Get server status
	 */
	public function getServerStatus(string $url) : int {
	}
	/**
	 * Check if URL point to a ownCloud/Nextcloud server
	 */
	public function isNextcloudServer(string $url) : bool {
	}
	/**
	 * Check if ownCloud/Nextcloud version is >= 9.0
	 * @throws HintException
	 */
	protected function checkNextcloudVersion(string $status) : bool {
	}
	/**
	 * Check if the URL contain a protocol, if not add https
	 */
	protected function updateProtocol(string $url) : string {
	}
}
