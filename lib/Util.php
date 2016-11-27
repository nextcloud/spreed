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
use OCP\ISession;
use OCP\IUser;

class Util {

	/**
	 * @param IConfig $config
	 * @return string
	 */
	public static function getStunServer(IConfig $config) {
		return $config->getAppValue('spreed', 'stun_server', 'stun.nextcloud.com:443');
	}

	/**
	 * @param IUser $user
	 */
	public static function deleteUser(IUser $user) {
		/** @var Manager $manager */
		$manager = \OC::$server->query(Manager::class);
		$rooms = $manager->getRoomsForParticipant($user->getUID());

		foreach ($rooms as $room) {
			if ($room->getType() === Room::ONE_TO_ONE_CALL || $room->getNumberOfParticipants() === 1) {
				$room->deleteRoom();
			} else {
				$room->removeUser($user);
			}
		}
	}

    public static function getTurnSettings(IConfig $config, $uid) {
		$value = $config->getUserValue($uid, 'spreed', 'turn_settings');
		if (empty($value)) {
			return array();
        }
		return json_decode($value, true);
    }

	/**
	 * Generates a username and password for the TURN server based on the
	 *
	 * @param IConfig $config
	 * @param ISession $session
	 * @param ITimeFactory $timeFactory
	 * @return array
	 */
    public static function generateTurnSettings(IConfig $config, ISession $session, ITimeFactory $timeFactory) {
		// generate from shared secret
		$turnServer = $config->getAppValue('spreed', 'turn_server', '');
		$turnServerSecret = $config->getAppValue('spreed', 'turn_server_secret', '');
		$turnServerProtocols = $config->getAppValue('spreed', 'turn_server_protocols', '');

		if ($turnServer === '' || $turnServerSecret === '' || $turnServerProtocols === '' || empty($session)) {
			return array();
		}

		// the credentials are valid for 24h - FIXME add the TTL to the response and properly reconnect then
		$time = new \OC\AppFramework\Utility\TimeFactory();
		$username =  $time->getTime() + 86400;
		$hashedString = hash_hmac('sha1', $username, $turnServerSecret, true);
		$password = base64_encode($hashedString);

		return array(
			'server' => $turnServer,
			'username' => $username,
			'password' => $password,
			'protocols' => $turnServerProtocols,
		);
	}

}
