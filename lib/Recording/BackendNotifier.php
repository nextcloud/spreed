<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Recording;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\RecordingNotFoundException;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class BackendNotifier {

	public function __construct(
		private Config $config,
		private LoggerInterface $logger,
		private IClientService $clientService,
		private ISecureRandom $secureRandom,
		private IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * Perform actual network request to the recording backend.
	 * This can be overridden in tests.
	 *
	 * @param string $url
	 * @param array $params
	 * @param int $retries
	 * @throws \Exception
	 */
	protected function doRequest(string $url, array $params, int $retries = 3): void {
		if (defined('PHPUNIT_RUN')) {
			// Don't perform network requests when running tests.
			return;
		}

		$client = $this->clientService->newClient();
		try {
			$response = $client->post($url, $params);
		} catch (ServerException|ConnectException $e) {
			if ($retries > 1) {
				$this->logger->error('Failed to send message to recording server, ' . $retries . ' retries left!', ['exception' => $e]);
				$this->doRequest($url, $params, $retries - 1);
			} else {
				$this->logger->error('Failed to send message to recording server, giving up!', ['exception' => $e]);
				throw $e;
			}
		} catch (\Exception $e) {
			$this->logger->error('Failed to send message to recording server', ['exception' => $e]);
			throw $e;
		}
	}

	/**
	 * Perform a request to the recording backend.
	 *
	 * @param Room $room
	 * @param array $data
	 * @throws \Exception
	 */
	private function backendRequest(Room $room, array $data): void {
		$recordingServers = $this->config->getRecordingServers();
		if (empty($recordingServers)) {
			$this->logger->error('No configured recording server');
			return;
		}

		// FIXME Currently clustering is not implemented in the recording
		// server, so for now only the first configured server is taken into
		// account to ensure that all the "stop" requests are sent to the same
		// server that received the "start" request.
		$recording = $recordingServers[0];
		$recording['server'] = rtrim($recording['server'], '/');

		$url = '/api/v1/room/' . $room->getToken();
		$url = $recording['server'] . $url;
		if (str_starts_with($url, 'ws://')) {
			$url = 'http://' . substr($url, 5);
		} elseif (str_starts_with($url, 'wss://')) {
			$url = 'https://' . substr($url, 6);
		}
		$body = json_encode($data);
		$headers = [
			'Content-Type' => 'application/json',
		];

		$random = $this->secureRandom->generate(64);
		$hash = hash_hmac('sha256', $random . $body, $this->config->getRecordingSecret());
		$headers['Talk-Recording-Random'] = $random;
		$headers['Talk-Recording-Checksum'] = $hash;
		$headers['Talk-Recording-Backend'] = $this->urlGenerator->getAbsoluteURL('');

		$params = [
			'headers' => $headers,
			'body' => $body,
			'nextcloud' => [
				'allow_local_address' => true,
			],
		];
		if (empty($recording['verify'])) {
			$params['verify'] = false;
		}
		$this->doRequest($url, $params);
	}

	public function start(Room $room, int $status, string $owner, Participant $participant): void {
		$start = microtime(true);
		$this->backendRequest($room, [
			'type' => 'start',
			'start' => [
				'status' => $status,
				'owner' => $owner,
				'actor' => [
					'type' => $participant->getAttendee()->getActorType(),
					'id' => $participant->getAttendee()->getActorId(),
				],
			],
		]);
		$duration = microtime(true) - $start;
		$this->logger->debug('Send start message: {token} ({duration})', [
			'token' => $room->getToken(),
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-recording',
		]);
	}

	public function stop(Room $room, ?Participant $participant = null): void {
		$parameters = [];
		if ($participant !== null) {
			$parameters['actor'] = [
				'type' => $participant->getAttendee()->getActorType(),
				'id' => $participant->getAttendee()->getActorId(),
			];
		}

		$start = microtime(true);
		try {
			$this->backendRequest($room, [
				'type' => 'stop',
				'stop' => $parameters,
			]);
		} catch (ClientException $e) {
			if ($e->getResponse()->getStatusCode() === Http::STATUS_NOT_FOUND) {
				throw new RecordingNotFoundException();
			}

			throw $e;
		}
		$duration = microtime(true) - $start;
		$this->logger->debug('Send stop message: {token} ({duration})', [
			'token' => $room->getToken(),
			'duration' => sprintf('%.2f', $duration),
			'app' => 'spreed-recording',
		]);
	}
}
