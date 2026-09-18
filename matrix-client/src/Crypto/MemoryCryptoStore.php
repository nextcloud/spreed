<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

/** In-memory crypto store for tests and scripts. */
final class MemoryCryptoStore implements CryptoStoreInterface {
	private ?string $account = null;
	/** @var array<string, array<string, string>> */
	private array $olm = [];
	/** @var array<string, array<string, string>> */
	private array $inbound = [];
	/** @var array<string, array{pickle: string, sessionId: string, sharedWith: array<string, list<string>>, createdAt: int, messageCount: int}> */
	private array $outbound = [];
	/** @var array<string, array<string, DeviceKeys>> */
	private array $devices = [];
	/** @var array<string, array<string, int>> */
	private array $trust = [];
	/** @var list<string> */
	private array $stale = [];
	/** @var array<string, CrossSigningKeys> */
	private array $crossSigning = [];
	/** @var array<string, string> */
	private array $secrets = [];

	public function loadAccount(): ?string {
		return $this->account;
	}

	public function saveAccount(string $pickle): void {
		$this->account = $pickle;
	}

	public function loadOlmSessions(string $theirCurve25519): array {
		return $this->olm[$theirCurve25519] ?? [];
	}

	public function saveOlmSession(string $theirCurve25519, string $sessionId, string $pickle): void {
		$this->olm[$theirCurve25519][$sessionId] = $pickle;
	}

	public function loadInboundGroupSession(string $roomId, string $sessionId): ?string {
		return $this->inbound[$roomId][$sessionId] ?? null;
	}

	public function saveInboundGroupSession(string $roomId, string $sessionId, string $senderKey, string $pickle, int $firstKnownIndex, array $forwardingChain, string $importedFrom): void {
		$this->inbound[$roomId][$sessionId] = $pickle;
	}

	public function loadOutboundGroupSession(string $roomId): ?string {
		return $this->outbound[$roomId]['pickle'] ?? null;
	}

	public function saveOutboundGroupSession(string $roomId, string $sessionId, string $pickle, array $sharedWith, int $createdAt, int $messageCount): void {
		$this->outbound[$roomId] = ['pickle' => $pickle, 'sessionId' => $sessionId, 'sharedWith' => $sharedWith, 'createdAt' => $createdAt, 'messageCount' => $messageCount];
	}

	public function discardOutboundGroupSession(string $roomId): void {
		unset($this->outbound[$roomId]);
	}

	public function outboundGroupSessionMeta(string $roomId): ?array {
		if (!isset($this->outbound[$roomId])) {
			return null;
		}
		return ['sharedWith' => $this->outbound[$roomId]['sharedWith'], 'createdAt' => $this->outbound[$roomId]['createdAt'], 'messageCount' => $this->outbound[$roomId]['messageCount']];
	}

	public function loadDevices(array $userIds): array {
		$out = [];
		foreach ($userIds as $userId) {
			if (array_key_exists($userId, $this->devices)) {
				$out[$userId] = $this->devices[$userId];
			}
		}
		return $out;
	}

	public function saveDevices(array $devices, array $trust): void {
		foreach ($devices as $userId => $userDevices) {
			$this->devices[$userId] = $userDevices;
			foreach ($userDevices as $deviceId => $device) {
				$this->trust[$userId][$deviceId] = $trust[$userId][$deviceId] ?? Trust::UNKNOWN;
			}
			$this->stale = array_values(array_diff($this->stale, [$userId]));
		}
	}

	public function deviceTrust(string $userId, string $deviceId): int {
		return $this->trust[$userId][$deviceId] ?? Trust::UNKNOWN;
	}

	public function setDeviceTrust(string $userId, string $deviceId, int $trust): void {
		$this->trust[$userId][$deviceId] = $trust;
	}

	public function markDevicesStale(array $userIds): void {
		$this->stale = array_values(array_unique([...$this->stale, ...$userIds]));
	}

	public function staleUsers(): array {
		return $this->stale;
	}

	public function loadCrossSigning(string $userId): ?CrossSigningKeys {
		return $this->crossSigning[$userId] ?? null;
	}

	public function saveCrossSigning(CrossSigningKeys $keys): void {
		$this->crossSigning[$keys->userId] = $keys;
	}

	public function getSecret(string $name): ?string {
		return $this->secrets[$name] ?? null;
	}

	public function setSecret(string $name, ?string $value): void {
		if ($value === null) {
			unset($this->secrets[$name]);
		} else {
			$this->secrets[$name] = $value;
		}
	}
}
