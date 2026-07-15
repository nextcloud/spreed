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
use OCA\Talk\Service\CertificateService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;

class Manager {
	public const FEATURE_HEADER = 'X-Spreed-Signaling-Features';
	public const string HAS_FEATURE_CHANGED_USERS = 'has_feature_changed_users';

	private readonly ICache $cache;

	public function __construct(
		private readonly IConfig $serverConfig,
		private readonly IAppConfig $appConfig,
		private readonly Config $talkConfig,
		private readonly RoomService $roomService,
		private readonly ITimeFactory $timeFactory,
		private readonly IClientService $clientService,
		private readonly CertificateService $certificateService,
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

		$url = rtrim((string)$signalingServers[$serverId]['server'], '/');
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

			$features = $this->getFeatureArray($response);
			if (!$this->hasRequiredFeatures($features)) {
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

			$missingFeatures = $this->getSignalingServerMissingFeatures($features);
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

			/**
			 * Store appconfig for some optional features that require different
			 * behaviour on PHP backend side
			 */
			if (in_array('changed-users', $features, true)) {
				$this->appConfig->setAppValueBool(Manager::HAS_FEATURE_CHANGED_USERS, true);
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

	protected function getFeatureArray(IResponse $response): array {
		$featureHeader = $response->getHeader(self::FEATURE_HEADER);
		$features = explode(',', $featureHeader);
		return array_map(trim(...), $features);
	}

	public function isCompatibleSignalingServer(IResponse $response): bool {
		$features = $this->getFeatureArray($response);
		return $this->hasRequiredFeatures($features);
	}

	public function hasFeature(IResponse $response, string $feature): bool {
		$features = $this->getFeatureArray($response);
		return in_array($feature, $features, true);
	}

	protected function hasRequiredFeatures(array $features): bool {
		return in_array('audio-video-permissions', $features, true)
			&& in_array('federation', $features, true)
			&& in_array('incall-all', $features, true)
			&& in_array('hello-v2', $features, true)
			&& in_array('switchto', $features, true);
	}

	/**
	 * @return list<string>
	 */
	protected function getSignalingServerMissingFeatures(array $features): array {
		$optionFeatures = [
			'dialout',
			'join-features',
			'chat-relay',
			'changed-users',
		];

		return array_values(array_diff($optionFeatures, $features));
	}
}
