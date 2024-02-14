<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Federation\Proxy\TalkV1;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\Http\Client\IClientService;
use OCP\IConfig;

/**
 * @psalm-import-type TalkChatMentionSuggestion from ResponseDefinitions
 */
class ChatService {
	protected ?Room $room = null;

	public function __construct(
		protected IConfig $config,
		protected IClientService $clientService,
	) {
	}

	/**
	 *
	 * @return TalkChatMentionSuggestion[]
	 */
	public function mentions(Room $room, Participant $participant, string $search, int $limit = 20, bool $includeStatus = false): array {
		$this->room = $room;
		$headers = [
			'Accept' => 'application/json',
			'X-Nextcloud-Federation' => 'true',
			'OCS-APIRequest' => 'true',
		];

		$client = $this->clientService->newClient();
		try {
			$response = $client->get($room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken() . '/mentions', [
				'json' => [
					'search' => $search,
					'limit' => $limit,
					'includeStatus' => $includeStatus,
				],
				'verify' => !$this->config->getSystemValueBool('sharing.federation.allowSelfSignedCertificates', false),
				'nextcloud' => [
					'allow_local_address' => $this->config->getSystemValueBool('allow_local_remote_servers'),
				],
				'headers' => $headers,
				'timeout' => 5,
				'auth' => [urlencode($participant->getAttendee()->getInvitedCloudId()), $participant->getAttendee()->getAccessToken()]
			]);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->error($e->getMessage(), ['exception' => $e]);
			// FIXME Error handling
			return [];
		}

		$content = $response->getBody();
		$data = json_decode($content, true);
		return array_map([$this, 'flipLocalAndRemoteSuggestions'], $data['ocs']['data'] ?? []);
	}

	protected function flipLocalAndRemoteSuggestions(array $suggestion): array {
		if ($suggestion['source'] === Attendee::ACTOR_USERS) {
			$suggestion['source'] = Attendee::ACTOR_FEDERATED_USERS;
			$suggestion['id'] .= '@' . $this->room->getRemoteServer();
		}

		return $suggestion;
	}
}
