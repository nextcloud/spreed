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

namespace OCA\Spreed\Controller;

use OCA\Spreed\Config;
use OCA\Spreed\Room;
use OCP\AppFramework\Controller;
use OCP\Http\Client\IClientService;
use OCP\ILogger;
use OCP\IRequest;
use OCP\Security\ISecureRandom;

class BackendController extends Controller {
	/** @var Config */
	private $config;
	/** @var ILogger */
	private $logger;
	/** @var IClientService */
	private $clientService;
	/** @var ISecureRandom */
	private $secureRandom;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param ILogger $logger
	 * @param IClientService $clientService
	 */
	public function __construct($appName,
								IRequest $request,
								Config $config,
								ILogger $logger,
								IClientService $clientService,
								ISecureRandom $secureRandom) {
		parent::__construct($appName, $request);
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
	 */
	private function backendRequest($url, $data) {
		$signaling = $this->config->getSignalingServer();
		if (empty($signaling)) {
			return;
		}

		// We can use any server of the available backends.
		$signaling = $signaling[array_rand($signaling)];
		if (substr($signaling, -1) === '/') {
			$signaling = substr($signaling, 0, strlen($signaling) - 1);
		}
		$url = $signaling . $url;
		if (substr($url, 0, 6) === 'wss://') {
			$url = 'https://' . substr($url, 6);
		} else if (substr($url, 0, 5) === 'ws://') {
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
		if ($this->config->allowInsecureSignaling()) {
			$params['verify'] = false;
		}
		$response = $client->post($url, $params);
	}

	/**
	 * Return list of userids that are invited to a room.
	 *
	 * @param Room $room
	 * @return array
	 */
	private function getRoomUserIds($room) {
		$participants = $room->getParticipants();
		$userIds = [];
		foreach ($participants['users'] as $participant => $data) {
			array_push($userIds, $participant);
		}
		return $userIds;
	}

	/**
	 * The given users are now invited to a room.
	 *
	 * @param Room $room
	 * @param array $userIds
	 */
	public function roomInvited($room, $userIds) {
		$this->logger->info("Now invited to " . $room->getToken() . ": " + print_r($userIds, true));
		$this->backendRequest('/api/v1/room/' . $room->getToken(), [
			'type' => 'invite',
			'invite' => [
				'userids' => $userIds,
				// TODO(fancycode): We should try to get rid of "alluserids" and
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
	 */
	public function roomsDisinvited($room, $userIds) {
		$this->logger->info("No longer invited to " . $room->getToken() . ": " + print_r($userIds, true));
		$this->backendRequest('/api/v1/room/' . $room->getToken(), [
			'type' => 'disinvite',
			'disinvite' => [
				'userids' => $userIds,
				// TODO(fancycode): We should try to get rid of "alluserids" and
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
	 */
	public function roomModified($room) {
		$this->logger->info("Room modified: " . $room->getToken());
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
	 */
	public function roomDeleted($room) {
		$this->logger->info("Room deleted: " . $room->getToken());
		$this->backendRequest('/api/v1/room/' . $room->getToken(), [
			'type' => 'delete',
			'delete' => [
				'userids' => $this->getRoomUserIds($room),
			],
		]);
	}

}
