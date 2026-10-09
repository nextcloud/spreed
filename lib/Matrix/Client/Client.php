<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client;

use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Model\LoginResult;
use OCA\Talk\Matrix\Client\Model\SyncBatch;

/**
 * Matrix Client-Server API façade. One instance per (homeserver, access token).
 */
class Client {
	public const PREFIX = '/_matrix/client/v3';

	public function __construct(
		private readonly Transport $transport,
	) {
	}

	public function withAccessToken(#[\SensitiveParameter] ?string $token): self {
		return new self($this->transport->withAccessToken($token));
	}

	/**
	 * m.login.password
	 *
	 * @param string $deviceId Reuse an existing device instead of creating a new one
	 * @throws MatrixException
	 */
	public function loginWithPassword(string $user, #[\SensitiveParameter] string $password, string $initialDeviceDisplayName, string $deviceId = ''): LoginResult {
		$body = [
			'type' => 'm.login.password',
			'identifier' => ['type' => 'm.id.user', 'user' => $user],
			'password' => $password,
			'initial_device_display_name' => $initialDeviceDisplayName,
		];
		if ($deviceId !== '') {
			$body['device_id'] = $deviceId;
		}
		return LoginResult::fromArray($this->transport->withAccessToken(null)->post(self::PREFIX . '/login', $body));
	}

	/**
	 * Owner of the access token
	 *
	 * @return string Matrix user id
	 * @throws MatrixException UnknownTokenException when the token is no longer valid
	 */
	public function whoami(): string {
		return (string)($this->transport->get(self::PREFIX . '/account/whoami')['user_id'] ?? '');
	}

	/**
	 * Invalidates the access token and removes the device
	 *
	 * @throws MatrixException
	 */
	public function logout(): void {
		$this->transport->post(self::PREFIX . '/logout');
	}

	/**
	 * Upload a filter definition for /sync
	 *
	 * @param array<string, mixed> $filter
	 * @return string Filter id
	 * @throws MatrixException
	 */
	public function createFilter(string $userId, array $filter): string {
		return (string)($this->transport->post(self::PREFIX . '/user/' . rawurlencode($userId) . '/filter', $filter)['filter_id'] ?? '');
	}

	/**
	 * @param string $since Position of the previous sync, empty for an initial sync
	 * @param int $timeoutMs How long the homeserver may wait for new events
	 * @throws MatrixException
	 */
	public function sync(string $since, string $filterId, int $timeoutMs = 0): SyncBatch {
		return SyncBatch::fromArray($this->transport->get(self::PREFIX . '/sync', [
			'since' => $since !== '' ? $since : null,
			'filter' => $filterId !== '' ? $filterId : null,
			'timeout' => $timeoutMs,
			'set_presence' => 'offline',
		]));
	}

	/**
	 * @param array<string, mixed> $content
	 * @param string $transactionId Unique per access token, a retry with the same id is not sent twice
	 * @return string Event id
	 * @throws MatrixException
	 */
	public function sendEvent(string $roomId, string $type, array $content, string $transactionId): string {
		$path = self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/send/' . rawurlencode($type) . '/' . rawurlencode($transactionId);
		return (string)($this->transport->put($path, $content)['event_id'] ?? '');
	}

	/**
	 * @return string Event id of the redaction
	 * @throws MatrixException
	 */
	public function redact(string $roomId, string $eventId, string $transactionId): string {
		$path = self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/redact/' . rawurlencode($eventId) . '/' . rawurlencode($transactionId);
		return (string)($this->transport->put($path)['event_id'] ?? '');
	}

	/**
	 * Mark the event as read and as the fully read marker
	 *
	 * @throws MatrixException
	 */
	public function setReadMarker(string $roomId, string $eventId): void {
		$this->transport->post(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/read_markers', [
			'm.read' => $eventId,
			'm.fully_read' => $eventId,
		]);
	}
}
