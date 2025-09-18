<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Signaling;

use GuzzleHttp\Exception\ConnectException;
use OCA\Talk\CachePrefix;
use OCA\Talk\Config;
use OCA\Talk\Room;
use OCA\Talk\Service\CertificateService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;

class Manager {
	public const FEATURE_HEADER = 'X-Spreed-Signaling-Features';

	protected ICache $cache;

	public function __construct(
		protected IConfig $serverConfig,
		protected Config $talkConfig,
		protected RoomService $roomService,
		protected ITimeFactory $timeFactory,
		protected IClientService $clientService,
		protected CertificateService $certificateService,
		ICacheFactory $cacheFactory,
	) {
		$this->cache = $cacheFactory->createDistributed(CachePrefix::SIGNALING_ASSIGNED_SERVER);
	}

	/**
	 * @param int $serverId
	 * @return array{status: Http::STATUS_OK, data: array{version: string, warning?: string, features?: non-empty-list<string>}}|array{status: Http::STATUS_INTERNAL_SERVER_ERROR, data: array{error: string, version?: string}}
	 * @throws \OutOfBoundsException When the serverId is not found
	 */
	public function checkServerCompatibility(int $serverId): array {
		$signalingServers = $this->talkConfig->getSignalingServers();
		if (empty($signalingServers) || !isset($signalingServers[$serverId])) {
			throw new \OutOfBoundsException();
		}

		$url = rtrim($signalingServers[$serverId]['server'], '/');
		$url = strtolower($url);

		if (str_starts_with($url, 'wss://')) {
			$url = 'https://' . substr($url, 6);
		}

		if (str_starts_with($url, 'ws://')) {
			$url = 'http://' . substr($url, 5);
		}

		$verifyServer = (bool)$signalingServers[$serverId]['verify'];

		if ($verifyServer && str_contains($url, 'https://')) {
			$expiration = $this->certificateService->getCertificateExpirationInDays($url);

			if ($expiration < 0) {
				return [
					'status' => Http::STATUS_INTERNAL_SERVER_ERROR,
					'data' => [
						'error' => 'CERTIFICATE_EXPIRED',
					],
				];
			}
		}

		$client = $this->clientService->newClient();
		try {
			$timeBefore = $this->timeFactory->getTime();
			$response = $client->get($url . '/api/v1/welcome', [
				'verify' => $verifyServer,
				'nextcloud' => [
					'allow_local_address' => true,
				],
			]);
			$timeAfter = $this->timeFactory->getTime();

			$body = $response->getBody();
			$data = json_decode($body, true);

			if (!is_array($data)) {
				return [
					'status' => Http::STATUS_INTERNAL_SERVER_ERROR,
					'data' => [
						'error' => 'JSON_INVALID',
					],
				];
			}

			if (!isset($data['version'])) {
				return [
					'status' => Http::STATUS_INTERNAL_SERVER_ERROR,
					'data' => [
						'error' => 'UPDATE_REQUIRED',
						'version' => '',
					],
				];
			}

			if (!$this->isCompatibleSignalingServer($response)) {
				return [
					'status' => Http::STATUS_INTERNAL_SERVER_ERROR,
					'data' => [
						'error' => 'UPDATE_REQUIRED',
						'version' => $data['version'] ?? '',
					],
				];
			}

			$responseTime = $this->timeFactory->getDateTime($response->getHeader('date'))->getTimestamp();
			if (($timeBefore - Config::ALLOWED_BACKEND_TIMEOFFSET) > $responseTime
				|| ($timeAfter + Config::ALLOWED_BACKEND_TIMEOFFSET) < $responseTime) {
				return [
					'status' => Http::STATUS_INTERNAL_SERVER_ERROR,
					'data' => [
						'error' => 'TIME_OUT_OF_SYNC',
					],
				];
			}

			$missingFeatures = $this->getSignalingServerMissingFeatures($response);
			if (!empty($missingFeatures)) {
				return [
					'status' => Http::STATUS_OK,
					'data' => [
						'warning' => 'UPDATE_OPTIONAL',
						'features' => $missingFeatures,
						'version' => $data['version'],
					],
				];
			}

			return [
				'status' => Http::STATUS_OK,
				'data' => [
					'version' => $data['version'],
				],
			];
		} catch (ConnectException) {
			return [
				'status' => Http::STATUS_INTERNAL_SERVER_ERROR,
				'data' => [
					'error' => 'CAN_NOT_CONNECT',
				],
			];
		} catch (\Exception $e) {
			return [
				'status' => Http::STATUS_INTERNAL_SERVER_ERROR,
				'data' => [
					'error' => (string)$e->getCode(),
				],
			];
		}

	}

	public function isCompatibleSignalingServer(IResponse $response): bool {
		$featureHeader = $response->getHeader(self::FEATURE_HEADER);
		$features = explode(',', $featureHeader);
		$features = array_map('trim', $features);
		return in_array('audio-video-permissions', $features, true)
			&& in_array('federation', $features, true)
			&& in_array('incall-all', $features, true)
			&& in_array('hello-v2', $features, true)
			&& in_array('switchto', $features, true);
	}

	/**
	 * @return list<string>
	 */
	public function getSignalingServerMissingFeatures(IResponse $response): array {
		$featureHeader = $response->getHeader(self::FEATURE_HEADER);
		$features = explode(',', $featureHeader);
		$features = array_map('trim', $features);

		return array_values(array_diff([
			'dialout',
			'join-features',
		], $features));
	}

	public function getSignalingServerLinkForConversation(?Room $room): string {
		if ($this->talkConfig->getSignalingMode() === Config::SIGNALING_INTERNAL) {
			return '';
		}

		return $this->getSignalingServerForConversation($room)['server'];
	}

	public function getSignalingServerForConversation(?Room $room): array {
		switch ($this->talkConfig->getSignalingMode()) {
			case Config::SIGNALING_EXTERNAL:
				return $this->getSignalingServerRandomly();
			case Config::SIGNALING_CLUSTER_CONVERSATION:
				if (!$room instanceof Room) {
					throw new \RuntimeException('Can not get conversation cluster HPB without conversation');
				}
				return $this->getSignalingServerConversationCluster($room);
			default:
				throw new \RuntimeException('Unsupported signaling mode');
		}
	}

	public function getSignalingServerRandomly(): array {
		$servers = $this->talkConfig->getSignalingServers();
		try {
			$serverId = random_int(0, count($servers) - 1);
			return $servers[$serverId];
		} catch (\Exception $e) {
			return $servers[0];
		}
	}

	public function getSignalingServerConversationCluster(Room $room): array {
		$serverId = $room->getAssignedSignalingServer();
		$servers = $this->talkConfig->getSignalingServers();

		if ($serverId !== null && isset($servers[$serverId])) {
			return $servers[$serverId];
		}

		try {
			$serverIdToAssign = random_int(0, count($servers) - 1);
		} catch (\Exception $e) {
			$serverIdToAssign = 0;
		}

		$hardcodedServers = $this->serverConfig->getSystemValue('talk_hardcoded_hpb', []);
		if (isset($hardcodedServers[$room->getToken()])) {
			$hardcodedServerId = $hardcodedServers[$room->getToken()];
			if (isset($servers[$hardcodedServerId])) {
				$serverIdToAssign = $hardcodedServerId;
			}
		}

		$serverId = $this->cache->get($room->getToken());
		if ($serverId === null) {
			$this->cache->set($room->getToken(), $serverIdToAssign);
			$serverId = $serverIdToAssign;
			$this->roomService->setAssignedSignalingServer($room, $serverId);
		}

		return $servers[$serverId];
	}
}
