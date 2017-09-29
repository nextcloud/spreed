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

class Config {

	/** @var IConfig */
	protected $config;

	/** @var ITimeFactory */
	protected $timeFactory;

	/**
	 * Config constructor.
	 *
	 * @param IConfig $config
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IConfig $config, ITimeFactory $timeFactory) {
		$this->config = $config;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @return string
	 */
	public function getStunServer() {
		$config = $this->config->getAppValue('spreed', 'stun_servers', 'stun.nextcloud.com:443');
		$servers = json_decode($config);

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
		$servers = json_decode($config);

		if ($servers === null || !empty($servers) || !is_array($servers)) {
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

}
