<?php
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
use OCP\IUser;
use OCP\Security\ISecureRandom;

class Config {

	/** @var IConfig */
	protected $config;

	/** @var ITimeFactory */
	protected $timeFactory;

	/** @var ISecureRandom */
	private $secureRandom;

	/**
	 * Config constructor.
	 *
	 * @param IConfig $config
	 * @param ISecureRandom $secureRandom
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IConfig $config,
								ISecureRandom $secureRandom,
								ITimeFactory $timeFactory) {
		$this->config = $config;
		$this->secureRandom = $secureRandom;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @return string
	 */
	public function getStunServer() {
		$config = $this->config->getAppValue('spreed', 'stun_servers', json_encode(['stun.nextcloud.com:443']));
		$servers = json_decode($config, true);

		if ($servers === null) {
			return $config ?: 'stun.nextcloud.com:443';
		}

		if (is_array($servers) && !empty($servers)) {
			// For now we use a random server from the list
			return $servers[mt_rand(0, count($servers) - 1)];
		}

		return 'stun.nextcloud.com:443';
	}

	/**
	 * Generates a username and password for the TURN server
	 *
	 * @return array
	 */
	public function getTurnSettings() {
		$config = $this->config->getAppValue('spreed', 'turn_servers');
		$servers = json_decode($config, true);

		if ($servers === null || empty($servers) || !is_array($servers)) {
			return [
				'server' => '',
				'username' => '',
				'password' => '',
				'protocols' => '',
			];
		}

		// For now we use a random server from the list
		$server = $servers[mt_rand(0, count($servers) - 1)];

		// Credentials are valid for 24h
		// FIXME add the TTL to the response and properly reconnect then
		$username = $this->timeFactory->getTime() + 86400;
		$password = base64_encode(hash_hmac('sha1', $username, $server['secret'], true));

		return array(
			'server' => $server['server'],
			'username' => (string) $username,
			'password' => $password,
			'protocols' => $server['protocols'],
		);
	}

	/**
	 * @return string
	 */
	public function getSignalingServer() {
		return $this->config->getAppValue('spreed', 'signaling_server', '');
	}

	/**
	 * @return string
	 */
	public function getSignalingSecret() {
		return $this->config->getAppValue('spreed', 'signaling_secret', '');
	}

	/**
	 * @return bool
	 */
	public function allowInsecureSignaling() {
		$skip_verify = $this->config->getAppValue('spreed', 'signaling_skip_verify_cert', '');
		return !empty($skip_verify);
	}

	/**
	 * @param string $userId
	 * @return string
	 */
	public function getSignalingTicket($userId) {
		if (empty($userId)) {
			$secret = $this->config->getAppValue('spreed', 'signaling_ticket_secret', '');
		} else {
			$secret = $this->config->getUserValue($userId, 'spreed', 'signaling_ticket_secret', '');
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
	public function validateSignalingTicket($userId, $ticket) {
		if (empty($userId)) {
			$secret = $this->config->getAppValue('spreed', 'signaling_ticket_secret', '');
		} else {
			$secret = $this->config->getUserValue($userId, 'spreed', 'signaling_ticket_secret', '');
		}
		if (empty($secret)) {
			return false;
		}

		$lastcolon = strrpos($ticket, ':');
		if ($lastcolon === false) {
			// Immediately reject invalid formats.
			return false;
		}

		// TODO(fancycode): Should we reject tickets that are too old?
		$data = substr($ticket, 0, $lastcolon);
		$hash = hash_hmac('sha256', $data, $secret);
		return hash_equals($hash, substr($ticket, $lastcolon + 1));
	}

}
