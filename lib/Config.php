<?php
declare(strict_types=1);
/**
 * @author Joachim Bauch <mail@joachim-bauch.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Spreed;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Security\ISecureRandom;

class Config {

	/** @var IConfig */
	protected $config;
	/** @var ITimeFactory */
	protected $timeFactory;
	/** @var IGroupManager */
	private $groupManager;
	/** @var ISecureRandom */
	private $secureRandom;

	public function __construct(IConfig $config,
								ISecureRandom $secureRandom,
								IGroupManager $groupManager,
								ITimeFactory $timeFactory) {
		$this->config = $config;
		$this->secureRandom = $secureRandom;
		$this->groupManager = $groupManager;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @return string[]
	 */
	public function getAllowedGroupIds(): array {
		$groups = $this->config->getAppValue('spreed', 'allowed_groups', '[]');
		$groups = json_decode($groups, true);
		return \is_array($groups) ? $groups : [];
	}

	public function isDisabledForUser(IUser $user): bool {
		$allowedGroups = $this->getAllowedGroupIds();
		if (empty($allowedGroups)) {
			return false;
		}

		$userGroups = $this->groupManager->getUserGroupIds($user);
		return empty(array_intersect($allowedGroups, $userGroups));
	}

	public function getSettings(?string $userId): array {
		$stun = [];
		$stunServer = $this->getStunServer();
		if ($stunServer) {
			$stun[] = [
				'url' => 'stun:' . $stunServer,
			];
		}
		$turn = [];
		$turnSettings = $this->getTurnSettings();
		if (!empty($turnSettings['server'])) {
			$protocols = explode(',', $turnSettings['protocols']);
			foreach ($protocols as $proto) {
				$turn[] = [
					'url' => ['turn:' . $turnSettings['server'] . '?transport=' . $proto],
					'urls' => ['turn:' . $turnSettings['server'] . '?transport=' . $proto],
					'username' => $turnSettings['username'],
					'credential' => $turnSettings['password'],
				];
			}
		}

		$signaling = [];
		$servers = $this->getSignalingServers();
		if (!empty($servers)) {
			try {
				$signaling = $servers[random_int(0, count($servers) - 1)];
			} catch (\Exception $e) {
				$signaling = $servers[0];
			}
			$signaling = $signaling['server'];
		}

		return [
			'hideWarning' => !empty($signaling) || $this->getHideSignalingWarning(),
			'server' => $signaling,
			'ticket' => $this->getSignalingTicket($userId),
			'stunservers' => $stun,
			'turnservers' => $turn,
		];
	}

	/**
	 * @return string[]
	 */
	public function getAllServerUrlsForCSP(): array {
		$urls = [];

		foreach ($this->getStunServers() as $server) {
			$urls[] = $server;
		}

		foreach ($this->getTurnServers() as $server) {
			$urls[] = $server['server'];
		}

		foreach ($this->getSignalingServers() as $server) {
			$urls[] = $this->getWebSocketDomainForSignalingServer($server['server']);
		}

		return $urls;
	}

	protected function getWebSocketDomainForSignalingServer(string $url): string {
		$url .= '/';
		if (strpos($url, 'https://') === 0) {
			return 'wss://' . substr($url, 8, strpos($url, '/', 9) - 8);
		}

		if (strpos($url, 'http://') === 0) {
			return 'ws://' . substr($url, 7, strpos($url, '/', 8) - 7);
		}

		if (strpos($url, 'wss://') === 0) {
			return substr($url, 0, strpos($url, '/', 7));
		}

		if (strpos($url, 'ws://') === 0) {
			return substr($url, 0, strpos($url, '/', 6));
		}

		$protocol = strpos($url, '://');
		if ($protocol !== false) {
			return substr($url, $protocol + 3, strpos($url, '/', $protocol + 3) - $protocol - 3);
		}

		return substr($url, 0, strpos($url, '/'));
	}

	/**
	 * @return string[]
	 */
	public function getStunServers(): array {
		$config = $this->config->getAppValue('spreed', 'stun_servers', json_encode(['stun.nextcloud.com:443']));
		$servers = json_decode($config, true);

		if (is_array($servers) && !empty($servers)) {
			return $servers;
		}

		return ['stun.nextcloud.com:443'];
	}

	/**
	 * @return string
	 */
	public function getStunServer(): string {
		$servers = $this->getStunServers();
		// For now we use a random server from the list
		try {
			return $servers[random_int(0, count($servers) - 1)];
		} catch (\Exception $e) {
			return $servers[0];
		}
	}

	/**
	 * Generates a username and password for the TURN server
	 *
	 * @return array
	 */
	public function getTurnServers(): array {
		$config = $this->config->getAppValue('spreed', 'turn_servers');
		$servers = json_decode($config, true);

		if ($servers === null || empty($servers) || !is_array($servers)) {
			return [];
		}

		return $servers;
	}

	/**
	 * Generates a username and password for the TURN server
	 *
	 * @return array
	 */
	public function getTurnSettings(): array {
		$servers = $this->getTurnServers();

		if (empty($servers)) {
			return [
				'server' => '',
				'username' => '',
				'password' => '',
				'protocols' => '',
			];
		}

		// For now we use a random server from the list
		try {
			$server = $servers[random_int(0, count($servers) - 1)];
		} catch (\Exception $e) {
			$server = $servers[0];
		}

		// Credentials are valid for 24h
		// FIXME add the TTL to the response and properly reconnect then
		$timestamp = $this->timeFactory->getTime() + 86400;
		$rnd = $this->secureRandom->generate(16);
		$username = $timestamp . ':' . $rnd;
		$password = base64_encode(hash_hmac('sha1', $username, $server['secret'], true));

		return array(
			'server' => $server['server'],
			'username' => $username,
			'password' => $password,
			'protocols' => $server['protocols'],
		);
	}

	/**
	 * Returns list of signaling servers. Each entry contains the URL of the
	 * server and a flag whether the certificate should be verified.
	 *
	 * @return array
	 */
	public function getSignalingServers(): array {
		$config = $this->config->getAppValue('spreed', 'signaling_servers');
		$signaling = json_decode($config, true);
		if (!is_array($signaling) || !isset($signaling['servers'])) {
			return [];
		}

		return $signaling['servers'];
	}

	/**
	 * @return string
	 */
	public function getSignalingSecret(): string {
		$config = $this->config->getAppValue('spreed', 'signaling_servers');
		$signaling = json_decode($config, true);

		if (!is_array($signaling)) {
			return '';
		}

		return $signaling['secret'];
	}

	public function getHideSignalingWarning(): bool {
		return $this->config->getAppValue('spreed', 'hide_signaling_warning', 'no') === 'yes';
	}

	/**
	 * @param string $userId
	 * @return string
	 */
	public function getSignalingTicket(?string $userId): string {
		if (empty($userId)) {
			$secret = $this->config->getAppValue('spreed', 'signaling_ticket_secret');
		} else {
			$secret = $this->config->getUserValue($userId, 'spreed', 'signaling_ticket_secret');
		}
		if (empty($secret)) {
			// Create secret lazily on first access.
			// TODO(fancycode): Is there a possibility for a race condition?
			$secret = $this->secureRandom->generate(255);
			if (empty($userId)) {
				$this->config->setAppValue('spreed', 'signaling_ticket_secret', $secret);
			} else {
				$this->config->setUserValue($userId, 'spreed', 'signaling_ticket_secret', $secret);
			}
		}

		// Format is "random:timestamp:userid:checksum" and "checksum" is the
		// SHA256-HMAC of "random:timestamp:userid" with the per-user secret.
		$random = $this->secureRandom->generate(16);
		$timestamp = $this->timeFactory->getTime();
		$data = $random . ':' . $timestamp . ':' . $userId;
		$hash = hash_hmac('sha256', $data, $secret);
		return $data . ':' . $hash;
	}

	/**
	 * @param string $userId
	 * @param string $ticket
	 * @return bool
	 */
	public function validateSignalingTicket(?string $userId, string $ticket): bool {
		if (empty($userId)) {
			$secret = $this->config->getAppValue('spreed', 'signaling_ticket_secret');
		} else {
			$secret = $this->config->getUserValue($userId, 'spreed', 'signaling_ticket_secret');
		}
		if (empty($secret)) {
			return false;
		}

		$lastColon = strrpos($ticket, ':');
		if ($lastColon === false) {
			// Immediately reject invalid formats.
			return false;
		}

		// TODO(fancycode): Should we reject tickets that are too old?
		$data = substr($ticket, 0, $lastColon);
		$hash = hash_hmac('sha256', $data, $secret);
		return hash_equals($hash, substr($ticket, $lastColon + 1));
	}

}
