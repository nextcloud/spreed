<?php

declare(strict_types=1);
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

namespace OCA\Talk\Signaling;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use OC\Http\Client\Response;
use OCA\Talk\Config;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class BackendNotifier {

	public function __construct(
		private Config $config,
		private LoggerInterface $logger,
		private IClientService $clientService,
		private ISecureRandom $secureRandom,
		private Manager $signalingManager,
		private ParticipantService $participantService,
		private IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * Perform actual network request to the signaling backend.
	 * This can be overridden in tests.
	 *
	 * @param string $url
	 * @param array $params
	 * @param int $retries
	 * @return ?IResponse
	 * @throws \Exception
	 */
	protected function doRequest(string $url, array $params, int $retries = 3): ?IResponse {
		if (defined('PHPUNIT_RUN')) {
			// Don't perform network requests when running tests.
			return null;
		}

		$client = $this->clientService->newClient();
		try {
			$response = $client->post($url, $params);

			if (!$this->signalingManager->isCompatibleSignalingServer($response)) {
				throw new \RuntimeException('Signaling server needs to be updated to be compatible with this version of Talk');
			}

			return $response;
		} catch (ConnectException $e) {
			if ($retries > 1) {
				$this->logger->error('Failed to send message to signaling server, ' . $retries . ' retries left!', ['exception' => $e]);
				return $this->doRequest($url, $params, $retries - 1);
			}

			$this->logger->error('Failed to send message to signaling server, giving up!', ['exception' => $e]);
			throw $e;
		} catch (ServerException $e) {
			if ($retries > 1) {
				$this->logger->error('Failed to send message to signaling server, ' . $retries . ' retries left!', ['exception' => $e]);
				return $this->doRequest($url, $params, $retries - 1);
			}

			$this->logger->error('Failed to send message to signaling server, giving up!', ['exception' => $e]);
			if ($e->hasResponse()) {
				return new Response($e->getResponse());
			}
			throw $e;
		} catch (\Exception $e) {
			$this->logger->error('Failed to send message to signaling server', ['exception' => $e]);
			throw $e;
		}
	}

	/**
	 * Perform a request to the signaling backend.
	 *
	 * @param Room $room
	 * @param array $data
	 * @return ?IResponse
	 * @throws \Exception
	 */
	private function backendRequest(Room $room, array $data): ?IResponse {
		if ($this->config->getSignalingMode() === Config::SIGNALING_INTERNAL) {
			return null;
		}

		// FIXME some need to go to all HPBs, but that doesn't scale, so bad luck for now :(
		$signaling = $this->signalingManager->getSignalingServerForConversation($room);
		$signaling['server'] = rtrim($signaling['server'], '/');

		$url = '/api/v1/room/' . $room->getToken();
		$url = $signaling['server'] . $url;
		if (str_starts_with($url, 'wss://')) {
			$url = 'https://' . substr($url, 6);
		} elseif (str_starts_with($url, 'ws://')) {
			$url = 'http://' . substr($url, 5);
		}
		$body = json_encode($data);
		$headers = [
			'Content-Type' => 'application/json',
		];

		if ($room->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #6.0: ' . microtime(true));
		}
		$random = $this->secureRandom->generate(64);
		$hash = hash_hmac('sha256', $random . $body, $this->config->getSignalingSecret());
		$headers['Spreed-Signaling-Random'] = $random;
		$headers['Spreed-Signaling-Checksum'] = $hash;
		$headers['Spreed-Signaling-Backend'] = $this->urlGenerator->getAbsoluteURL('');
		if ($room->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #6.1: ' . microtime(true));
		}

		$params = [
			'headers' => $headers,
			'body' => $body,
			'nextcloud' => [
				'allow_local_address' => true,
			],
		];
		if (empty($signaling['verify'])) {
			$params['verify'] = false;
		}
		return $this->doRequest($url, $params);
	}

	/**
	 * The given users are now invited to a room.
	 *
	 * @param Room $room
	 * @param Attendee[] $attendees
	 * @throws \Exception
	 */
	public function roomInvited(Room $room, array $attendees): void {
		$userIds = [];
		foreach ($attendees as $attendee) {
			if ($attendee->getActorType() === Attendee::ACTOR_USERS) {
				$userIds[] = $attendee->getActorId();
			}
		}
		$start = microtime(true);
		$this->backendRequest($room, [
			'type' => 'invite',
			'invite' => [
				'userids' => $userIds,
				// TODO(fancycode): We should try to get rid of 'alluserids' and
				// find a better way to notify existing users to update the room.
				'alluserids' => $this->participantService->getParticipantUserIds($room),
				'properties' => $room->getPropertiesForSignaling('', false),
			],
		]);
		$duration = microtime(true) - $start;
		$this->logger->debug('Now invited to {token}: {users} ({duration})', [
			'token' => $room->getToken(),
			'users' => print_r($userIds, true),
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-hpb',
		]);
	}

	/**
	 * The given users are no longer invited to a room.
	 *
	 * @param Room $room
	 * @param Attendee[] $attendees
	 * @throws \Exception
	 */
	public function roomsDisinvited(Room $room, array $attendees): void {
		$allUserIds = $this->participantService->getParticipantUserIds($room);
		sort($allUserIds);
		$userIds = [];
		foreach ($attendees as $attendee) {
			if ($attendee->getActorType() === Attendee::ACTOR_USERS) {
				$userIds[] = $attendee->getActorId();
			}
		}
		$start = microtime(true);
		$this->backendRequest($room, [
			'type' => 'disinvite',
			'disinvite' => [
				'userids' => $userIds,
				// TODO(fancycode): We should try to get rid of 'alluserids' and
				// find a better way to notify existing users to update the room.
				'alluserids' => $allUserIds,
				'properties' => $room->getPropertiesForSignaling('', false),
			],
		]);
		$duration = microtime(true) - $start;
		$this->logger->debug('No longer invited to {token}: {users} ({duration})', [
			'token' => $room->getToken(),
			'users' => print_r($userIds, true),
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-hpb',
		]);
	}

	/**
	 * The given sessions have been removed from a room.
	 *
	 * @param Room $room
	 * @param string[] $sessionIds
	 * @throws \Exception
	 */
	public function roomSessionsRemoved(Room $room, array $sessionIds): void {
		$allUserIds = $this->participantService->getParticipantUserIds($room);
		sort($allUserIds);
		$start = microtime(true);
		$this->backendRequest($room, [
			'type' => 'disinvite',
			'disinvite' => [
				'sessionids' => $sessionIds,
				// TODO(fancycode): We should try to get rid of 'alluserids' and
				// find a better way to notify existing users to update the room.
				'alluserids' => $allUserIds,
				'properties' => $room->getPropertiesForSignaling('', false),
			],
		]);
		$duration = microtime(true) - $start;
		$this->logger->debug('Removed from {token}: {users} ({duration})', [
			'token' => $room->getToken(),
			'users' => print_r($sessionIds, true),
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-hpb',
		]);
	}

	/**
	 * The given room has been modified.
	 *
	 * @param Room $room
	 * @throws \Exception
	 */
	public function roomModified(Room $room): void {
		$start = microtime(true);
		$this->backendRequest($room, [
			'type' => 'update',
			'update' => [
				'userids' => $this->participantService->getParticipantUserIds($room),
				'properties' => $room->getPropertiesForSignaling(''),
			],
		]);
		$duration = microtime(true) - $start;
		$this->logger->debug('Room modified: {token} ({duration})', [
			'token' => $room->getToken(),
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-hpb',
		]);
	}

	/**
	 * The given room has been deleted.
	 *
	 * @param Room $room
	 * @param string[] $userIds
	 * @throws \Exception
	 */
	public function roomDeleted(Room $room, array $userIds): void {
		$start = microtime(true);
		$this->backendRequest($room, [
			'type' => 'delete',
			'delete' => [
				'userids' => $userIds,
			],
		]);
		$duration = microtime(true) - $start;
		$this->logger->debug('Room deleted: {token} ({duration})', [
			'token' => $room->getToken(),
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-hpb',
		]);
	}

	/**
	 * The given participants should switch to the given room.
	 *
	 * @param Room $room
	 * @param string $switchToRoomToken
	 * @param string[] $sessionIds
	 * @throws \Exception
	 */
	public function switchToRoom(Room $room, string $switchToRoomToken, array $sessionIds): void {
		$start = microtime(true);
		$this->backendRequest($room, [
			'type' => 'switchto',
			'switchto' => [
				'roomid' => $switchToRoomToken,
				'sessions' => $sessionIds,
			],
		]);
		$duration = microtime(true) - $start;
		$this->logger->debug('Switch to room: {token} {roomid} {sessions} ({duration})', [
			'token' => $room->getToken(),
			'roomid' => $switchToRoomToken,
			'sessions' => print_r($sessionIds, true),
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-hpb',
		]);
	}

	/**
	 * The participant list of the given room has been modified.
	 *
	 * @param Room $room
	 * @param string[] $sessionIds
	 * @throws \Exception
	 */
	public function participantsModified(Room $room, array $sessionIds): void {
		$changed = [];
		$users = [];
		$participants = $this->participantService->getSessionsAndParticipantsForRoom($room);
		foreach ($participants as $participant) {
			$attendee = $participant->getAttendee();
			if ($attendee->getActorType() !== Attendee::ACTOR_USERS
				&& $attendee->getActorType() !== Attendee::ACTOR_GUESTS) {
				continue;
			}

			$data = [
				'inCall' => Participant::FLAG_DISCONNECTED,
				'lastPing' => 0,
				'sessionId' => '0',
				'participantType' => $attendee->getParticipantType(),
				'participantPermissions' => Attendee::PERMISSIONS_CUSTOM,
				'displayName' => $attendee->getDisplayName(),
			];
			if ($attendee->getActorType() === Attendee::ACTOR_USERS) {
				$data['userId'] = $attendee->getActorId();
			}

			$session = $participant->getSession();
			if ($session instanceof Session) {
				$data['inCall'] = $session->getInCall();
				$data['lastPing'] = $session->getLastPing();
				$data['sessionId'] = $session->getSessionId();
				$data['participantPermissions'] = $participant->getPermissions();
				$users[] = $data;

				if (\in_array($session->getSessionId(), $sessionIds, true)) {
					$data['permissions'] = [];
					if ($participant->getPermissions() & Attendee::PERMISSIONS_PUBLISH_AUDIO) {
						$data['permissions'][] = 'publish-audio';
					}
					if ($participant->getPermissions() & Attendee::PERMISSIONS_PUBLISH_VIDEO) {
						$data['permissions'][] = 'publish-video';
					}
					if ($participant->getPermissions() & Attendee::PERMISSIONS_PUBLISH_SCREEN) {
						$data['permissions'][] = 'publish-screen';
					}
					if ($participant->hasModeratorPermissions(false)) {
						$data['permissions'][] = 'control';
					}
					$changed[] = $data;
				}
			} else {
				$users[] = $data;
			}
		}

		$start = microtime(true);
		$this->backendRequest($room, [
			'type' => 'participants',
			'participants' => [
				'changed' => $changed,
				'users' => $users
			],
		]);
		$duration = microtime(true) - $start;
		$this->logger->debug('Room participants modified: {token} {users} ({duration})', [
			'token' => $room->getToken(),
			'users' => print_r($sessionIds, true),
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-hpb',
		]);
	}

	/**
	 * The "in-call" status of the given session ids has changed..
	 *
	 * @param Room $room
	 * @param int $flags
	 * @param string[] $sessionIds
	 * @param bool $changeAll
	 * @throws \Exception
	 */
	public function roomInCallChanged(Room $room, int $flags, array $sessionIds, bool $changeAll = false): void {
		if ($changeAll) {
			if ($room->getToken() === 'c9bui2ju') {
				\OC::$server->getLogger()->warning('Debugging step #5.a: ' . microtime(true));
			}
			$data = [
				'incall' => $flags,
				'all' => true
			];
		} else {
			if ($room->getToken() === 'c9bui2ju') {
				\OC::$server->getLogger()->warning('Debugging step #5.b: ' . microtime(true));
			}
			$changed = [];
			$users = [];

			$participants = $this->participantService->getParticipantsForAllSessions($room);
			if ($room->getToken() === 'c9bui2ju') {
				\OC::$server->getLogger()->warning('Debugging step #5.1: ' . microtime(true));
			}
			foreach ($participants as $participant) {
				$session = $participant->getSession();
				if (!$session instanceof Session) {
					continue;
				}

				$attendee = $participant->getAttendee();
				if ($attendee->getActorType() !== Attendee::ACTOR_USERS
					&& $attendee->getActorType() !== Attendee::ACTOR_GUESTS) {
					continue;
				}

				$data = [
					'inCall' => $session->getInCall(),
					'lastPing' => $session->getLastPing(),
					'sessionId' => $session->getSessionId(),
					'nextcloudSessionId' => $session->getSessionId(),
					'participantType' => $attendee->getParticipantType(),
					'participantPermissions' => $participant->getPermissions(),
				];
				if ($attendee->getActorType() === Attendee::ACTOR_USERS) {
					$data['userId'] = $attendee->getActorId();
				}

				if ($session->getInCall() !== Participant::FLAG_DISCONNECTED) {
					$users[] = $data;
				}

				if (\in_array($session->getSessionId(), $sessionIds, true)) {
					$changed[] = $data;
				}
			}

			$data = [
				'incall' => $flags,
				'changed' => $changed,
				'users' => $users,
			];
		}

		if ($room->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #5.2: ' . microtime(true));
		}

		$start = microtime(true);
		$this->backendRequest($room, [
			'type' => 'incall',
			'incall' => $data,
		]);
		$duration = microtime(true) - $start;
		$this->logger->debug('Room in-call status changed: {token} {flags} {users} ({duration})', [
			'token' => $room->getToken(),
			'flags' => $flags,
			'users' => $changeAll ? 'all' : print_r($sessionIds, true),
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-hpb',
		]);
		if ($room->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #5.3: ' . microtime(true));
		}
	}

	/**
	 * Send dial-out requests to the HPB
	 *
	 * @throws \Exception
	 */
	public function dialOutToAttendee(Room $room, Attendee $attendee): ?string {
		$start = microtime(true);
		$response = $this->backendRequest($room, [
			'type' => 'dialout',
			'dialout' => [
				'number' => $attendee->getPhoneNumber(),
				'options' => [
					'attendeeId' => $attendee->getId(),
					'actorType' => $attendee->getActorType(),
					'actorId' => $attendee->getActorId(),
				]
			],
		]);

		if ($response === null) {
			$this->logger->debug('Room dial out response was NULL');
			return null;
		}

		$duration = microtime(true) - $start;
		$this->logger->debug('Room dial out: {token} {number} ({duration})', [
			'token' => $room->getToken(),
			'number' => $attendee->getPhoneNumber(),
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-hpb',
		]);

		return (string) $response->getBody();
	}

	/**
	 * Send a message to all sessions currently joined in a room. The message
	 * will be received by "processRoomMessageEvent" in "signaling.js".
	 *
	 * @param Room $room
	 * @param array $message
	 * @throws \Exception
	 */
	public function sendRoomMessage(Room $room, array $message): void {
		$start = microtime(true);
		$this->backendRequest($room, [
			'type' => 'message',
			'message' => [
				'data' => $message,
			],
		]);
		$duration = microtime(true) - $start;
		$this->logger->debug('Send room message: {token} {message} ({duration})', [
			'token' => $room->getToken(),
			'message' => $message,
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-hpb',
		]);
	}
}
