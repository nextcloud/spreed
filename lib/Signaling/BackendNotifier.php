<?php
/**
 * @copyright Copyright (c) 2017 Joachim Bauch <bauch@struktur.de>
 *
 * @author Joachim Bauch <bauch@struktur.de>
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

namespace OCA\Spreed\Signaling;

use OCA\Spreed\Config;
use OCA\Spreed\Room;
use OCP\Http\Client\IClientService;
use OCP\ILogger;
use OCP\Security\ISecureRandom;

class BackendNotifier{
	/** @var Config */
	private $config;
	/** @var ILogger */
	private $logger;
	/** @var IClientService */
	private $clientService;
	/** @var ISecureRandom */
	private $secureRandom;

	/**
	 * @param Config $config
	 * @param ILogger $logger
	 * @param IClientService $clientService
	 * @param ISecureRandom $secureRandom
	 */
	public function __construct(Config $config,
								ILogger $logger,
								IClientService $clientService,
								ISecureRandom $secureRandom) {
		$this->config = $config;
		$this->logger = $logger;
		$this->clientService = $clientService;
		$this->secureRandom = $secureRandom;
	}

	/**
	 * Perform a request to the signaling backend.
	 *
	 * @param string $url
	 * @param array $data
	 * @throws \Exception
	 */
	private function backendRequest($url, $data) {
		$servers = $this->config->getSignalingServers();
		if (empty($servers)) {
			return;
		}

		// We can use any server of the available backends.
		$signaling = $servers[mt_rand(0, count($servers) - 1)];
		$signaling['server'] = rtrim($signaling['server'], '/');
		$url = rtrim($signaling['server'], '/') . $url;
		if (strpos($url, 'wss://') === 0) {
			$url = 'https://' . substr($url, 6);
		} else if (strpos($url, 'ws://') === 0) {
			$url = 'http://' . substr($url, 5);
		}
		$client = $this->clientService->newClient();
		$body = json_encode($data);
		$headers = [
			'Content-Type' => 'application/json',
		];

		$random = $this->secureRandom->generate(64);
		$hash = hash_hmac('sha256', $random . $body, $this->config->getSignalingSecret());
		$headers['Spreed-Signaling-Random'] = $random;
		$headers['Spreed-Signaling-Checksum'] = $hash;

		$params = [
			'headers' => $headers,
			'body' => $body,
		];
		if (!$signaling['verify']) {
			$params['verify'] = false;
		}
		$client->post($url, $params);
	}

	/**
	 * Return list of userids that are invited to a room.
	 *
	 * @param Room $room
	 * @return array
	 */
	private function getRoomUserIds($room) {
		$participants = $room->getParticipants();
		return array_keys($participants['users']);
	}

	/**
	 * The given users are now invited to a room.
	 *
	 * @param Room $room
	 * @param array $users
	 * @throws \Exception
	 */
	public function roomInvited($room, $users) {
		$this->logger->info('Now invited to ' . $room->getToken() . ': ' . print_r($users, true));
		$userIds = [];
		foreach ($users as $user) {
			$userIds[] = $user['userId'];
		}
		$this->backendRequest('/api/v1/room/' . $room->getToken(), [
			'type' => 'invite',
			'invite' => [
				'userids' => $userIds,
				// TODO(fancycode): We should try to get rid of 'alluserids' and
				// find a better way to notify existing users to update the room.
				'alluserids' => $this->getRoomUserIds($room),
				'properties' => [
					'name' => $room->getName(),
					'type' => $room->getType(),
				],
			],
		]);
	}

	/**
	 * The given users are no longer invited to a room.
	 *
	 * @param Room $room
	 * @param array $userIds
	 * @throws \Exception
	 */
	public function roomsDisinvited($room, $userIds) {
		$this->logger->info('No longer invited to ' . $room->getToken() . ': ' . print_r($userIds, true));
		$this->backendRequest('/api/v1/room/' . $room->getToken(), [
			'type' => 'disinvite',
			'disinvite' => [
				'userids' => $userIds,
				// TODO(fancycode): We should try to get rid of 'alluserids' and
				// find a better way to notify existing users to update the room.
				'alluserids' => $this->getRoomUserIds($room),
				'properties' => [
					'name' => $room->getName(),
					'type' => $room->getType(),
				],
			],
		]);
	}

	/**
	 * The given room has been modified.
	 *
	 * @param Room $room
	 * @throws \Exception
	 */
	public function roomModified($room) {
		$this->logger->info('Room modified: ' . $room->getToken());
		$this->backendRequest('/api/v1/room/' . $room->getToken(), [
			'type' => 'update',
			'update' => [
				'userids' => $this->getRoomUserIds($room),
				'properties' => [
					'name' => $room->getName(),
					'type' => $room->getType(),
				],
			],
		]);
	}

	/**
	 * The given room has been deleted.
	 *
	 * @param Room $room
	 * @throws \Exception
	 */
	public function roomDeleted($room, $participants) {
		$this->logger->info('Room deleted: ' . $room->getToken());
		$userIds = array_keys($participants['users']);
		$this->backendRequest('/api/v1/room/' . $room->getToken(), [
			'type' => 'delete',
			'delete' => [
				'userids' => $userIds,
			],
		]);
	}

	/**
	 * The "in-call" status of the given session ids has changed..
	 *
	 * @param Room $room
	 * @param bool $inCall
	 * @param array $sessionids
	 * @throws \Exception
	 */
	public function roomInCallChanged($room, $inCall, $sessionIds) {
		$this->logger->info('Room in-call status changed: ' . $room->getToken() . ' ' . $inCall . ' ' . print_r($sessionIds, true));
		$changed = [];
		$users = [];
		$participants = $room->getParticipants();
		foreach ($participants['users'] as $userId => $participant) {
			if ($participant['inCall']) {
				$users[] = $participant;
			}
			if (in_array($participant['sessionId'], $sessionIds)) {
				$participant['userId'] = $userId;
				$changed[] = $participant;
			}
		}
		foreach ($participants['guests'] as $participant) {
			if ($participant['inCall']) {
				$users[] = $participant;
			}
			if (in_array($participant['sessionId'], $sessionIds)) {
				$changed[] = $participant;
			}
		}

		$this->backendRequest('/api/v1/room/' . $room->getToken(), [
			'type' => 'incall',
			'incall' => [
				'incall' => $inCall,
				'changed' => $changed,
				'users' => $users
			],
		]);
	}

}
