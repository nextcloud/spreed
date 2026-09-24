<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix;

use Nextcloud\Matrix\Http\Transport;
use Nextcloud\Matrix\Model\Event;
use Nextcloud\Matrix\Model\LoginResult;
use Nextcloud\Matrix\Model\MessagesPage;
use Nextcloud\Matrix\Model\Mxc;
use Nextcloud\Matrix\Model\RoomState;
use Nextcloud\Matrix\Model\SyncBatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Matrix Client-Server API façade (spec v1.11). One instance per (homeserver,
 * access token). All methods throw {@see Exception\MatrixException} subclasses.
 */
final class Client {
	public const PREFIX = '/_matrix/client/v3';
	public const PREFIX_V1 = '/_matrix/client/v1';
	public const MEDIA_LEGACY = '/_matrix/media/v3';

	/** @var array<string, mixed>|null */
	private ?array $versions = null;

	public function __construct(
		private readonly Transport $transport,
	) {
	}

	public function getTransport(): Transport {
		return $this->transport;
	}

	public function withAccessToken(?string $token): self {
		return new self($this->transport->withAccessToken($token));
	}

	// ---- Server discovery & info -------------------------------------------------

	/** @return array<string, mixed> GET /_matrix/client/versions */
	public function versions(): array {
		if ($this->versions === null) {
			$this->versions = $this->transport->get('/_matrix/client/versions');
		}
		return $this->versions;
	}

	/** @param array<string, mixed>|null $versions Pre-fetched /versions body */
	public function setVersions(?array $versions): void {
		$this->versions = $versions;
	}

	public function supportsSpecVersion(string $version): bool {
		$supported = $this->versions()['versions'] ?? [];
		return is_array($supported) && in_array($version, $supported, true);
	}

	public function supportsUnstableFeature(string $feature): bool {
		$features = $this->versions()['unstable_features'] ?? [];
		return is_array($features) && ($features[$feature] ?? false) === true;
	}

	/** Authenticated media endpoints (v1.11) available? */
	public function supportsAuthenticatedMedia(): bool {
		$supported = $this->versions()['versions'] ?? [];
		if (!is_array($supported)) {
			return false;
		}
		foreach ($supported as $v) {
			if (is_string($v) && version_compare(ltrim($v, 'v'), '1.11', '>=')) {
				return true;
			}
		}
		return $this->supportsUnstableFeature('org.matrix.msc3916.stable');
	}

	// ---- Session -----------------------------------------------------------------

	/**
	 * m.login.password. $deviceId reuses an existing device (keeps E2EE identity).
	 */
	public function loginWithPassword(string $user, #[\SensitiveParameter] string $password, string $initialDeviceDisplayName = 'Nextcloud Talk', ?string $deviceId = null): LoginResult {
		$body = [
			'type' => 'm.login.password',
			'identifier' => ['type' => 'm.id.user', 'user' => $user],
			'password' => $password,
			'initial_device_display_name' => $initialDeviceDisplayName,
		];
		if ($deviceId !== null && $deviceId !== '') {
			$body['device_id'] = $deviceId;
		}
		return LoginResult::fromArray($this->transport->withAccessToken(null)->post(self::PREFIX . '/login', $body));
	}

	/** @return list<array<string, mixed>> Supported login flows */
	public function getLoginFlows(): array {
		$flows = $this->transport->withAccessToken(null)->get(self::PREFIX . '/login')['flows'] ?? [];
		return is_array($flows) ? array_values($flows) : [];
	}

	public function logout(): void {
		$this->transport->post(self::PREFIX . '/logout');
	}

	/** @return array{user_id: string, device_id?: string} */
	public function whoami(): array {
		/** @var array{user_id: string, device_id?: string} */
		return $this->transport->get(self::PREFIX . '/account/whoami');
	}

	/** @return array<string, mixed> */
	public function getProfile(string $userId): array {
		return $this->transport->get(self::PREFIX . '/profile/' . rawurlencode($userId));
	}

	// ---- Sync --------------------------------------------------------------------

	/**
	 * @param array<string, mixed>|string|null $filter Filter id or inline filter definition
	 */
	public function sync(?string $since, array|string|null $filter = null, int $timeoutMs = 0, bool $fullState = false, ?string $setPresence = 'offline'): SyncBatch {
		$query = [
			'since' => $since,
			'timeout' => $timeoutMs,
			'full_state' => $fullState ? 'true' : null,
			'set_presence' => $setPresence,
		];
		if (is_array($filter)) {
			$query['filter'] = json_encode($filter, JSON_THROW_ON_ERROR);
		} elseif ($filter !== null) {
			$query['filter'] = $filter;
		}
		return SyncBatch::fromArray($this->transport->get(self::PREFIX . '/sync', $query));
	}

	/** @param array<string, mixed> $filter */
	public function createFilter(string $userId, array $filter): string {
		return (string)($this->transport->post(self::PREFIX . '/user/' . rawurlencode($userId) . '/filter', $filter)['filter_id'] ?? '');
	}

	/**
	 * Default sync filter: lazy-loaded members, limited timeline, no presence.
	 * @return array<string, mixed>
	 */
	public static function defaultFilter(int $timelineLimit = 50): array {
		return [
			'presence' => ['types' => []],
			'room' => [
				'state' => ['lazy_load_members' => true],
				'timeline' => ['limit' => $timelineLimit, 'lazy_load_members' => true],
				'ephemeral' => ['types' => ['m.receipt', 'm.typing']],
			],
		];
	}

	// ---- Rooms: reading ----------------------------------------------------------

	public function getRoomState(string $roomId): RoomState {
		$raw = $this->transport->get(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/state');
		$state = new RoomState($roomId);
		foreach ($raw as $event) {
			if (is_array($event)) {
				$state->apply(Event::fromArray($event, $roomId));
			}
		}
		return $state;
	}

	/** @return array<string, mixed> */
	public function getStateEvent(string $roomId, string $type, string $stateKey = ''): array {
		return $this->transport->get(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/state/' . rawurlencode($type) . '/' . rawurlencode($stateKey));
	}

	/** @return list<Event> m.room.member events, optionally filtered by membership */
	public function getMembers(string $roomId, ?string $membership = null, ?string $notMembership = null): array {
		$raw = $this->transport->get(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/members', ['membership' => $membership, 'not_membership' => $notMembership]);
		$chunk = is_array($raw['chunk'] ?? null) ? $raw['chunk'] : [];
		return array_map(static fn (array $e) => Event::fromArray($e, $roomId), array_values(array_filter($chunk, 'is_array')));
	}

	public function getEvent(string $roomId, string $eventId): Event {
		return Event::fromArray($this->transport->get(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/event/' . rawurlencode($eventId)), $roomId);
	}

	/**
	 * @param 'b'|'f' $dir
	 * @param array<string, mixed>|null $filter
	 */
	public function getMessages(string $roomId, ?string $from, string $dir = 'b', int $limit = 100, ?string $to = null, ?array $filter = null): MessagesPage {
		return MessagesPage::fromArray($roomId, $this->transport->get(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/messages', [
			'from' => $from,
			'to' => $to,
			'dir' => $dir,
			'limit' => $limit,
			'filter' => $filter === null ? null : json_encode($filter, JSON_THROW_ON_ERROR),
		]));
	}

	/** @return array<string, mixed> Joined rooms list: {joined_rooms: list<string>} */
	public function getJoinedRooms(): array {
		return $this->transport->get(self::PREFIX . '/joined_rooms');
	}

	// ---- Rooms: sending ----------------------------------------------------------

	/**
	 * @param array<string, mixed> $content
	 * @return string event id
	 */
	public function sendEvent(string $roomId, string $type, array $content, string $txnId): string {
		$result = $this->transport->put(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/send/' . rawurlencode($type) . '/' . rawurlencode($txnId), $content);
		return (string)($result['event_id'] ?? '');
	}

	/** @param array<string, mixed> $content */
	public function sendMessage(string $roomId, array $content, string $txnId): string {
		return $this->sendEvent($roomId, 'm.room.message', $content, $txnId);
	}

	/**
	 * Build m.text content with optional HTML, reply and thread relation.
	 * @param list<string> $mentionedUserIds
	 * @return array<string, mixed>
	 */
	public static function textContent(string $body, ?string $html = null, ?string $replyToEventId = null, ?string $threadRootEventId = null, ?string $threadFallbackEventId = null, array $mentionedUserIds = [], bool $mentionRoom = false, string $msgtype = 'm.text'): array {
		$content = ['msgtype' => $msgtype, 'body' => $body];
		if ($html !== null && $html !== '' && $html !== $body) {
			$content['format'] = 'org.matrix.custom.html';
			$content['formatted_body'] = $html;
		}
		if ($threadRootEventId !== null) {
			$content['m.relates_to'] = [
				'rel_type' => 'm.thread',
				'event_id' => $threadRootEventId,
				'is_falling_back' => $replyToEventId === null,
				'm.in_reply_to' => ['event_id' => $replyToEventId ?? $threadFallbackEventId ?? $threadRootEventId],
			];
		} elseif ($replyToEventId !== null) {
			$content['m.relates_to'] = ['m.in_reply_to' => ['event_id' => $replyToEventId]];
		}
		if ($mentionedUserIds !== [] || $mentionRoom) {
			$mentions = [];
			if ($mentionedUserIds !== []) {
				$mentions['user_ids'] = array_values(array_unique($mentionedUserIds));
			}
			if ($mentionRoom) {
				$mentions['room'] = true;
			}
			$content['m.mentions'] = $mentions;
		}
		return $content;
	}

	/** @param array<string, mixed> $content */
	public function sendStateEvent(string $roomId, string $type, array $content, string $stateKey = ''): string {
		$result = $this->transport->put(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/state/' . rawurlencode($type) . '/' . rawurlencode($stateKey), $content);
		return (string)($result['event_id'] ?? '');
	}

	public function redact(string $roomId, string $eventId, string $txnId, ?string $reason = null): string {
		$body = $reason === null || $reason === '' ? [] : ['reason' => $reason];
		$result = $this->transport->put(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/redact/' . rawurlencode($eventId) . '/' . rawurlencode($txnId), $body);
		return (string)($result['event_id'] ?? '');
	}

	public function setReadMarker(string $roomId, string $eventId, bool $alsoFullyRead = true, bool $private = false): void {
		$body = [$private ? 'm.read.private' : 'm.read' => $eventId];
		if ($alsoFullyRead) {
			$body['m.fully_read'] = $eventId;
		}
		$this->transport->post(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/read_markers', $body);
	}

	public function setTyping(string $roomId, string $userId, bool $typing, int $timeoutMs = 10000): void {
		$body = ['typing' => $typing];
		if ($typing) {
			$body['timeout'] = $timeoutMs;
		}
		$this->transport->put(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/typing/' . rawurlencode($userId), $body);
	}

	// ---- Rooms: membership & lifecycle -------------------------------------------

	/**
	 * @param list<string> $via
	 * @return string room id
	 */
	public function join(string $roomIdOrAlias, array $via = [], ?string $reason = null): string {
		$body = $reason === null ? [] : ['reason' => $reason];
		$result = $this->transport->post(self::PREFIX . '/join/' . rawurlencode($roomIdOrAlias), $body, ['via' => $via]);
		return (string)($result['room_id'] ?? '');
	}

	/** @param list<string> $via */
	public function knock(string $roomIdOrAlias, array $via = [], ?string $reason = null): string {
		$body = $reason === null ? [] : ['reason' => $reason];
		$result = $this->transport->post(self::PREFIX . '/knock/' . rawurlencode($roomIdOrAlias), $body, ['via' => $via]);
		return (string)($result['room_id'] ?? '');
	}

	public function leave(string $roomId, ?string $reason = null): void {
		$this->transport->post(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/leave', $reason === null ? [] : ['reason' => $reason]);
	}

	public function forget(string $roomId): void {
		$this->transport->post(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/forget');
	}

	public function invite(string $roomId, string $userId, ?string $reason = null): void {
		$body = ['user_id' => $userId];
		if ($reason !== null) {
			$body['reason'] = $reason;
		}
		$this->transport->post(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/invite', $body);
	}

	public function kick(string $roomId, string $userId, ?string $reason = null): void {
		$body = ['user_id' => $userId];
		if ($reason !== null) {
			$body['reason'] = $reason;
		}
		$this->transport->post(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/kick', $body);
	}

	public function ban(string $roomId, string $userId, ?string $reason = null): void {
		$body = ['user_id' => $userId];
		if ($reason !== null) {
			$body['reason'] = $reason;
		}
		$this->transport->post(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/ban', $body);
	}

	public function unban(string $roomId, string $userId): void {
		$this->transport->post(self::PREFIX . '/rooms/' . rawurlencode($roomId) . '/unban', ['user_id' => $userId]);
	}

	/**
	 * @param array<string, mixed> $options Body of POST /createRoom (name, topic, invite, preset, is_direct, initial_state, …)
	 * @return string room id
	 */
	public function createRoom(array $options): string {
		return (string)($this->transport->post(self::PREFIX . '/createRoom', $options)['room_id'] ?? '');
	}

	/** @return array{room_id: string, servers: list<string>} */
	public function resolveAlias(string $alias): array {
		$result = $this->transport->get(self::PREFIX . '/directory/room/' . rawurlencode($alias));
		return [
			'room_id' => (string)($result['room_id'] ?? ''),
			'servers' => array_values(array_filter(is_array($result['servers'] ?? null) ? $result['servers'] : [], 'is_string')),
		];
	}

	/** @return array<string, mixed> */
	public function getPublicRooms(?string $server = null, ?string $since = null, int $limit = 50, ?string $searchTerm = null): array {
		$body = ['limit' => $limit];
		if ($since !== null) {
			$body['since'] = $since;
		}
		if ($searchTerm !== null && $searchTerm !== '') {
			$body['filter'] = ['generic_search_term' => $searchTerm];
		}
		return $this->transport->post(self::PREFIX . '/publicRooms', $body, ['server' => $server]);
	}

	// ---- Account data ------------------------------------------------------------

	/** @return array<string, mixed> */
	public function getAccountData(string $userId, string $type): array {
		return $this->transport->get(self::PREFIX . '/user/' . rawurlencode($userId) . '/account_data/' . rawurlencode($type));
	}

	/** @param array<string, mixed> $content */
	public function setAccountData(string $userId, string $type, array $content): void {
		$this->transport->put(self::PREFIX . '/user/' . rawurlencode($userId) . '/account_data/' . rawurlencode($type), $content);
	}

	/** @param array<string, mixed> $content */
	public function setRoomAccountData(string $userId, string $roomId, string $type, array $content): void {
		$this->transport->put(self::PREFIX . '/user/' . rawurlencode($userId) . '/rooms/' . rawurlencode($roomId) . '/account_data/' . rawurlencode($type), $content);
	}

	/** Mark/unmark a room as DM with $peerUserId in m.direct. */
	public function setDirectRoom(string $userId, string $peerUserId, string $roomId): void {
		try {
			$direct = $this->getAccountData($userId, 'm.direct');
		} catch (Exception\NotFoundException) {
			$direct = [];
		}
		$rooms = is_array($direct[$peerUserId] ?? null) ? $direct[$peerUserId] : [];
		if (!in_array($roomId, $rooms, true)) {
			$rooms[] = $roomId;
		}
		$direct[$peerUserId] = $rooms;
		$this->setAccountData($userId, 'm.direct', $direct);
	}

	// ---- Media -------------------------------------------------------------------

	/** @return array<string, mixed> Media repo config ({m.upload.size}) */
	public function getMediaConfig(): array {
		$path = $this->supportsAuthenticatedMedia() ? self::PREFIX_V1 . '/media/config' : self::MEDIA_LEGACY . '/config';
		return $this->transport->get($path);
	}

	/**
	 * Downloads through the *own* homeserver (never contacts the origin server directly).
	 * The response body stream and Content-Type header are what the caller needs.
	 */
	public function downloadMedia(Mxc|string $mxc, int $timeoutMs = 20000): ResponseInterface {
		$mxc = $mxc instanceof Mxc ? $mxc : Mxc::parse($mxc);
		if ($this->supportsAuthenticatedMedia()) {
			return $this->transport->raw('GET', self::PREFIX_V1 . '/media/download/' . rawurlencode($mxc->serverName) . '/' . rawurlencode($mxc->mediaId), ['timeout_ms' => $timeoutMs]);
		}
		return $this->transport->raw('GET', self::MEDIA_LEGACY . '/download/' . rawurlencode($mxc->serverName) . '/' . rawurlencode($mxc->mediaId), ['allow_redirect' => 'true']);
	}

	/** @param 'crop'|'scale' $method */
	public function downloadThumbnail(Mxc|string $mxc, int $width, int $height, string $method = 'scale'): ResponseInterface {
		$mxc = $mxc instanceof Mxc ? $mxc : Mxc::parse($mxc);
		$query = ['width' => $width, 'height' => $height, 'method' => $method];
		if ($this->supportsAuthenticatedMedia()) {
			return $this->transport->raw('GET', self::PREFIX_V1 . '/media/thumbnail/' . rawurlencode($mxc->serverName) . '/' . rawurlencode($mxc->mediaId), $query);
		}
		$query['allow_redirect'] = 'true';
		return $this->transport->raw('GET', self::MEDIA_LEGACY . '/thumbnail/' . rawurlencode($mxc->serverName) . '/' . rawurlencode($mxc->mediaId), $query);
	}

	/** @return Mxc the content URI of the uploaded file */
	public function uploadMedia(StreamInterface $stream, string $contentType, ?string $filename = null): Mxc {
		$response = $this->transport->raw('POST', self::MEDIA_LEGACY . '/upload', ['filename' => $filename], $stream, $contentType);
		$decoded = json_decode((string)$response->getBody(), true);
		return Mxc::parse((string)($decoded['content_uri'] ?? ''));
	}

	// ---- End-to-end encryption key management -----------------------------------

	/**
	 * @param array<string, mixed>|null $deviceKeys
	 * @param array<string, mixed> $oneTimeKeys
	 * @param array<string, mixed> $fallbackKeys
	 * @return array<string, int> one_time_key_counts by algorithm
	 */
	public function uploadKeys(?array $deviceKeys, array $oneTimeKeys = [], array $fallbackKeys = []): array {
		$body = [];
		if ($deviceKeys !== null) {
			$body['device_keys'] = $deviceKeys;
		}
		if ($oneTimeKeys !== []) {
			$body['one_time_keys'] = $oneTimeKeys;
		}
		if ($fallbackKeys !== []) {
			$body['fallback_keys'] = $fallbackKeys;
		}
		$result = $this->transport->post(self::PREFIX . '/keys/upload', $body);
		return array_map('intval', is_array($result['one_time_key_counts'] ?? null) ? $result['one_time_key_counts'] : []);
	}

	/**
	 * @param list<string> $userIds
	 * @return array<string, mixed> raw response (device_keys, master_keys, self_signing_keys, user_signing_keys, failures)
	 */
	public function queryKeys(array $userIds, int $timeoutMs = 10000): array {
		$deviceKeys = [];
		foreach ($userIds as $userId) {
			$deviceKeys[$userId] = [];
		}
		return $this->transport->post(self::PREFIX . '/keys/query', ['device_keys' => $deviceKeys === [] ? new \stdClass() : $deviceKeys, 'timeout' => $timeoutMs]);
	}

	/**
	 * @param array<string, array<string, string>> $wanted user id → device id → algorithm
	 * @return array<string, mixed> raw response (one_time_keys, failures)
	 */
	public function claimKeys(array $wanted, int $timeoutMs = 10000): array {
		return $this->transport->post(self::PREFIX . '/keys/claim', ['one_time_keys' => $wanted, 'timeout' => $timeoutMs]);
	}

	/** @return array{changed: list<string>, left: list<string>} */
	public function getKeyChanges(string $from, string $to): array {
		$result = $this->transport->get(self::PREFIX . '/keys/changes', ['from' => $from, 'to' => $to]);
		return [
			'changed' => array_values(array_filter(is_array($result['changed'] ?? null) ? $result['changed'] : [], 'is_string')),
			'left' => array_values(array_filter(is_array($result['left'] ?? null) ? $result['left'] : [], 'is_string')),
		];
	}

	/**
	 * @param array<string, array<string, array<string, mixed>>> $messages user id → device id (or "*") → content
	 */
	public function sendToDevice(string $eventType, array $messages, string $txnId): void {
		$this->transport->put(self::PREFIX . '/sendToDevice/' . rawurlencode($eventType) . '/' . rawurlencode($txnId), ['messages' => $messages]);
	}

	/** @return array<string, mixed> device_keys of one user (raw) */
	public function getUserDeviceKeys(string $userId): array {
		$result = $this->queryKeys([$userId]);
		return is_array($result['device_keys'][$userId] ?? null) ? $result['device_keys'][$userId] : [];
	}

	/**
	 * Upload signatures of other keys (e.g. after verification), POST /keys/signatures/upload.
	 * @param array<string, array<string, array<string, mixed>>> $signatures user id → key id → signed object
	 */
	public function uploadSignatures(array $signatures): array {
		return $this->transport->post(self::PREFIX . '/keys/signatures/upload', $signatures);
	}

	// ---- Key backup --------------------------------------------------------------

	/** @return array<string, mixed>|null current backup version info (algorithm, auth_data, version, count) or null when there is none */
	public function getRoomKeysVersion(): ?array {
		try {
			return $this->transport->get(self::PREFIX . '/room_keys/version');
		} catch (Exception\NotFoundException) {
			return null;
		}
	}

	/** @return array<string, mixed> {rooms: {roomId: {sessions: {sessionId: {first_message_index, forwarded_count, is_verified, session_data}}}}} */
	public function getRoomKeys(string $version): array {
		return $this->transport->get(self::PREFIX . '/room_keys/keys', ['version' => $version]);
	}

	/** @param array<string, mixed> $rooms same shape as getRoomKeys()['rooms'] */
	public function putRoomKeys(string $version, array $rooms): array {
		return $this->transport->put(self::PREFIX . '/room_keys/keys', ['rooms' => $rooms], ['version' => $version]);
	}

	// ---- Push rules --------------------------------------------------------------

	/** @return array<string, mixed> */
	public function getPushRules(): array {
		return $this->transport->get(self::PREFIX . '/pushrules/');
	}

	// ---- Device management -------------------------------------------------------

	/** @return list<array<string, mixed>> */
	public function getDevices(): array {
		$devices = $this->transport->get(self::PREFIX . '/devices')['devices'] ?? [];
		return is_array($devices) ? array_values($devices) : [];
	}
}
