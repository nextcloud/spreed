<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

/**
 * Persistence the E2EE layer needs from its host, scoped to one account
 * (= one device). Values are opaque pickles; the host should encrypt them at rest.
 */
interface CryptoStoreInterface {
	public function loadAccount(): ?string;

	public function saveAccount(string $pickle): void;

	/** @return array<string, string> session id → pickle, for the given peer identity key */
	public function loadOlmSessions(string $theirCurve25519): array;

	public function saveOlmSession(string $theirCurve25519, string $sessionId, string $pickle): void;

	public function loadInboundGroupSession(string $roomId, string $sessionId): ?string;

	/**
	 * @param list<string> $forwardingChain
	 */
	public function saveInboundGroupSession(string $roomId, string $sessionId, string $senderKey, string $pickle, int $firstKnownIndex, array $forwardingChain, string $importedFrom): void;

	public function loadOutboundGroupSession(string $roomId): ?string;

	/** @param array<string, list<string>> $sharedWith user id → device ids */
	public function saveOutboundGroupSession(string $roomId, string $sessionId, string $pickle, array $sharedWith, int $createdAt, int $messageCount): void;

	public function discardOutboundGroupSession(string $roomId): void;

	/** @return array{sharedWith: array<string, list<string>>, createdAt: int, messageCount: int}|null */
	public function outboundGroupSessionMeta(string $roomId): ?array;

	/** @return array<string, array<string, DeviceKeys>> user id → device id → keys */
	public function loadDevices(array $userIds): array;

	/** @param array<string, array<string, DeviceKeys>> $devices user id → device id → keys; replaces the user's list */
	public function saveDevices(array $devices, array $trust): void;

	/** @return int one of the TRUST_* constants */
	public function deviceTrust(string $userId, string $deviceId): int;

	public function setDeviceTrust(string $userId, string $deviceId, int $trust): void;

	/** Users whose device list is stale (device_lists.changed seen, not yet re-queried). */
	public function markDevicesStale(array $userIds): void;

	/** @return list<string> */
	public function staleUsers(): array;

	public function loadCrossSigning(string $userId): ?CrossSigningKeys;

	public function saveCrossSigning(CrossSigningKeys $keys): void;

	public function getSecret(string $name): ?string;

	public function setSecret(string $name, ?string $value): void;
}
