<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client;

use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Model\Event;
use OCA\Talk\Matrix\Client\Model\LoginResult;
use OCA\Talk\Matrix\Client\Model\SyncBatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

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
	 * Send an event to devices, without a room
	 *
	 * @param array<string, array<string, array<string, mixed>>> $messages Content indexed by user id and device id, `*` for all devices of the user
	 * @param string $transactionId Unique per access token, a retry with the same id is not sent twice
	 * @throws MatrixException
	 */
	public function sendToDevice(string $type, array $messages, string $transactionId): void {
		$this->transport->put(self::PREFIX . '/sendToDevice/' . rawurlencode($type) . '/' . rawurlencode($transactionId), ['messages' => $messages]);
	}

	/**
	 * Publish the keys of the device
	 *
	 * @param array<string, mixed> $deviceKeys Signed device keys
	 * @param array<string, mixed> $oneTimeKeys Signed one-time keys indexed by algorithm and key id
	 * @param array<string, mixed> $fallbackKeys Signed fallback keys indexed by algorithm and key id
	 * @return array<string, int> Number of one-time keys on the homeserver indexed by algorithm
	 * @throws MatrixException
	 */
	public function uploadKeys(array $deviceKeys, array $oneTimeKeys, array $fallbackKeys): array {
		$body = ['device_keys' => $deviceKeys];
		if ($oneTimeKeys !== []) {
			$body['one_time_keys'] = $oneTimeKeys;
		}
		if ($fallbackKeys !== []) {
			$body['fallback_keys'] = $fallbackKeys;
		}
		$counts = $this->transport->post(self::PREFIX . '/keys/upload', $body)['one_time_key_counts'] ?? [];
		return is_array($counts) ? array_map('intval', $counts) : [];
	}

	/**
	 * Device keys of users
	 *
	 * @param list<string> $userIds
	 * @return array<string, array<string, mixed>> Raw signed device keys indexed by user id and device id
	 * @throws MatrixException
	 */
	public function queryKeys(array $userIds): array {
		$response = $this->transport->post(self::PREFIX . '/keys/query', ['device_keys' => array_fill_keys($userIds, [])]);
		$devices = [];
		foreach (is_array($response['device_keys'] ?? null) ? $response['device_keys'] : [] as $userId => $userDevices) {
			foreach (is_array($userDevices) ? $userDevices : [] as $deviceId => $keys) {
				if (is_array($keys)) {
					$devices[(string)$userId][(string)$deviceId] = $keys;
				}
			}
		}
		return $devices;
	}

	/**
	 * A device and the self-signing key of its user, to check whether the device is cross-signed
	 *
	 * @return array{device: ?array<string, mixed>, selfSigningKey: ?array<string, mixed>}
	 * @throws MatrixException
	 */
	public function getVerificationKeys(string $userId, string $deviceId): array {
		$response = $this->transport->post(self::PREFIX . '/keys/query', ['device_keys' => [$userId => []]]);
		$device = $response['device_keys'][$userId][$deviceId] ?? null;
		$selfSigningKey = $response['self_signing_keys'][$userId] ?? null;
		return [
			'device' => is_array($device) ? $device : null,
			'selfSigningKey' => is_array($selfSigningKey) ? $selfSigningKey : null,
		];
	}

	/**
	 * Create or replace a push rule of the user
	 *
	 * @param 'override'|'room' $kind
	 * @param array<string, mixed> $rule Actions and, for override rules, conditions
	 * @throws MatrixException
	 */
	public function setPushRule(string $kind, string $ruleId, array $rule): void {
		$this->transport->put(self::PREFIX . '/pushrules/global/' . $kind . '/' . rawurlencode($ruleId), $rule);
	}

	/**
	 * @param 'override'|'room' $kind
	 * @throws MatrixException M_NOT_FOUND when the rule does not exist
	 */
	public function deletePushRule(string $kind, string $ruleId): void {
		$this->transport->delete(self::PREFIX . '/pushrules/global/' . $kind . '/' . rawurlencode($ruleId));
	}

	/**
	 * Older events of a room, newest first
	 *
	 * @param string $from Pagination token, e.g. the prev_batch of a sync timeline
	 * @return array{events: list<Event>, end: ?string} end is null when the start of the room was reached
	 * @throws MatrixException
	 */
	public function getMessages(string $roomId, string $from, int $limit): array {
		$response = $this->transport->get(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/messages', [
			'from' => $from,
			'dir' => 'b',
			'limit' => $limit,
		]);
		$events = [];
		foreach (is_array($response['chunk'] ?? null) ? $response['chunk'] : [] as $event) {
			if (is_array($event)) {
				$events[] = Event::fromArray($event);
			}
		}
		return ['events' => $events, 'end' => is_string($response['end'] ?? null) ? $response['end'] : null];
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

	/**
	 * @param list<string> $serverNames Servers to join through when the homeserver is not in the room yet
	 * @return string Room id
	 * @throws MatrixException
	 */
	public function join(string $roomIdOrAlias, array $serverNames = []): string {
		$path = self::PREFIX . '/join/' . rawurlencode($roomIdOrAlias);
		foreach ($serverNames as $i => $serverName) {
			$path .= ($i === 0 ? '?' : '&') . 'server_name=' . rawurlencode($serverName);
		}
		return (string)($this->transport->post($path)['room_id'] ?? '');
	}

	/**
	 * Leave a joined room or decline an invite
	 *
	 * @throws MatrixException
	 */
	public function leave(string $roomId): void {
		$this->transport->post(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/leave');
	}

	/**
	 * @throws MatrixException
	 */
	public function invite(string $roomId, string $userId): void {
		$this->transport->post(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/invite', ['user_id' => $userId]);
	}

	/**
	 * @throws MatrixException
	 */
	public function kick(string $roomId, string $userId): void {
		$this->transport->post(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/kick', ['user_id' => $userId]);
	}

	/**
	 * @param array<string, mixed> $content
	 * @return string Event id
	 * @throws MatrixException
	 */
	public function sendStateEvent(string $roomId, string $type, array $content, string $stateKey = ''): string {
		$path = self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/state/' . rawurlencode($type) . '/' . rawurlencode($stateKey);
		return (string)($this->transport->put($path, $content)['event_id'] ?? '');
	}

	/**
	 * @param array<string, mixed> $options Body of the createRoom request
	 * @return string Room id
	 * @throws MatrixException
	 */
	public function createRoom(array $options): string {
		return (string)($this->transport->post(self::PREFIX . '/createRoom', $options)['room_id'] ?? '');
	}

	/**
	 * Download an attachment, through the authenticated media API (spec 1.11)
	 * with a fallback to the legacy one of older homeservers
	 *
	 * @param string $mxc Content URI like mxc://example.org/abc
	 * @throws \InvalidArgumentException when the content URI is invalid
	 * @throws MatrixException
	 */
	public function downloadMedia(string $mxc): ResponseInterface {
		$media = $this->getMediaPath($mxc);

		try {
			return $this->transport->download('/_matrix/client/v1/media/download/' . $media);
		} catch (MatrixException $e) {
			if ($e->getHttpStatus() !== 404 || $e->getErrcode() === 'M_NOT_FOUND') {
				throw $e;
			}
		}
		return $this->transport->download('/_matrix/media/v3/download/' . $media);
	}

	/**
	 * @return string Content URI
	 * @throws MatrixException
	 */
	public function uploadMedia(StreamInterface $content, string $contentType, string $fileName): string {
		return $this->transport->upload($content, $contentType, $fileName);
	}

	/**
	 * Scaled down image, with the same fallback as downloadMedia()
	 *
	 * @param string $mxc Content URI like mxc://example.org/abc
	 * @param bool $crop Crop to a square instead of keeping the aspect ratio
	 * @throws \InvalidArgumentException when the content URI is invalid
	 * @throws MatrixException
	 */
	public function downloadThumbnail(string $mxc, int $size, bool $crop = true): ResponseInterface {
		$query = '?width=' . $size . '&height=' . $size . '&method=' . ($crop ? 'crop' : 'scale');
		$media = $this->getMediaPath($mxc);

		try {
			return $this->transport->download('/_matrix/client/v1/media/thumbnail/' . $media . $query);
		} catch (MatrixException $e) {
			if ($e->getHttpStatus() !== 404 || $e->getErrcode() === 'M_NOT_FOUND') {
				throw $e;
			}
		}
		return $this->transport->download('/_matrix/media/v3/thumbnail/' . $media . $query);
	}

	/**
	 * @throws \InvalidArgumentException when the content URI is invalid
	 */
	protected function getMediaPath(string $mxc): string {
		if (!preg_match('~^mxc://([A-Za-z0-9.\-:\[\]]+)/([A-Za-z0-9_\-]+)$~', $mxc, $matches)) {
			throw new \InvalidArgumentException('Invalid content URI');
		}
		return rawurlencode($matches[1]) . '/' . rawurlencode($matches[2]);
	}
}
