<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

use Nextcloud\Matrix\Client;
use Nextcloud\Matrix\Crypto\Megolm\InboundSession;
use Nextcloud\Matrix\Crypto\Megolm\OutboundSession;
use Nextcloud\Matrix\Crypto\Olm\Account;
use Nextcloud\Matrix\Crypto\Olm\Message;
use Nextcloud\Matrix\Crypto\Olm\Session;
use Nextcloud\Matrix\Exception\MatrixException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The device's E2EE brain: owns the Olm account, tracks other devices, shares
 * and receives room keys, encrypts and decrypts. One instance per account
 * (device); all state goes through {@see CryptoStoreInterface}.
 */
final class Machine {
	public const MIN_ONE_TIME_KEYS = 50;
	public const ROTATION_PERIOD_MS_DEFAULT = 604800000;
	public const ROTATION_MSGS_DEFAULT = 100;

	private Account $account;
	private bool $accountDirty = false;
	/** @var callable(): string */
	private $txnIdFactory;

	public function __construct(
		private readonly Client $client,
		private readonly CryptoStoreInterface $store,
		private readonly string $userId,
		private readonly string $deviceId,
		private readonly LoggerInterface $logger = new NullLogger(),
		?callable $txnIdFactory = null,
	) {
		$pickle = $store->loadAccount();
		if ($pickle === null) {
			$this->account = Account::create();
			$this->accountDirty = true;
		} else {
			$this->account = Account::unpickle($pickle);
		}
		$this->txnIdFactory = $txnIdFactory ?? static fn (): string => 'nc-' . bin2hex(random_bytes(12));
	}

	public function getAccount(): Account {
		return $this->account;
	}

	public function getIdentityKey(): string {
		return $this->account->getIdentityKeyBase64();
	}

	public function getSigningKey(): string {
		return $this->account->getSigningKeyBase64();
	}

	/** Persist the account if it changed (call at the end of a unit of work). */
	public function flush(): void {
		if ($this->accountDirty) {
			$this->store->saveAccount($this->account->pickle());
			$this->accountDirty = false;
		}
	}

	// ---- Device bootstrap ----------------------------------------------------------

	/**
	 * Upload device keys (once) and top up one-time / fallback keys.
	 * @param array<string, int>|null $serverCounts device_one_time_keys_count from sync, if known
	 */
	public function publishKeys(bool $includeDeviceKeys, ?array $serverCounts = null): void {
		// Device keys go up exactly once per device – regardless of which code path runs first
		$includeDeviceKeys = $includeDeviceKeys || $this->store->getSecret('device_keys_published') === null;
		$signedCount = $serverCounts['signed_curve25519'] ?? null;
		$need = $signedCount === null ? self::MIN_ONE_TIME_KEYS : max(0, self::MIN_ONE_TIME_KEYS - $signedCount);
		if ($need > 0) {
			$this->account->generateOneTimeKeys($need);
			$this->accountDirty = true;
		}
		if ($this->account->getUnpublishedFallbackKey() === null && $this->store->getSecret('fallback_published') === null) {
			$this->account->generateFallbackKey();
			$this->accountDirty = true;
		}
		$keys = $this->account->keysForUpload($this->userId, $this->deviceId);
		if (!$includeDeviceKeys && $keys['one_time_keys'] === [] && $keys['fallback_keys'] === []) {
			return;
		}
		$this->client->uploadKeys($includeDeviceKeys ? $this->account->deviceKeys($this->userId, $this->deviceId) : null, $keys['one_time_keys'], $keys['fallback_keys']);
		$this->account->markKeysAsPublished();
		$this->store->setSecret('fallback_published', '1');
		if ($includeDeviceKeys) {
			$this->store->setSecret('device_keys_published', '1');
		}
		$this->accountDirty = true;
	}

	// ---- Device tracking -----------------------------------------------------------

	/**
	 * Make sure we hold current device keys for the given users (re-querying
	 * unknown and stale users). Returns user id → device id → keys.
	 *
	 * @param list<string> $userIds
	 * @return array<string, array<string, DeviceKeys>>
	 */
	public function devicesFor(array $userIds, bool $forceRefresh = false): array {
		$userIds = array_values(array_unique($userIds));
		$known = $this->store->loadDevices($userIds);
		$stale = $this->store->staleUsers();
		$toQuery = [];
		foreach ($userIds as $userId) {
			if ($forceRefresh || !array_key_exists($userId, $known) || in_array($userId, $stale, true)) {
				$toQuery[] = $userId;
			}
		}
		if ($toQuery !== []) {
			$this->queryDevices($toQuery);
			$known = $this->store->loadDevices($userIds);
		}
		return $known;
	}

	/** @param list<string> $userIds */
	public function queryDevices(array $userIds): void {
		foreach (array_chunk($userIds, 100) as $chunk) {
			$response = $this->client->queryKeys($chunk);
			$devices = [];
			$trust = [];
			foreach ($chunk as $userId) {
				$cross = CrossSigningKeys::fromQuery(
					$userId,
					is_array($response['master_keys'][$userId] ?? null) ? $response['master_keys'][$userId] : null,
					is_array($response['self_signing_keys'][$userId] ?? null) ? $response['self_signing_keys'][$userId] : null,
					is_array($response['user_signing_keys'][$userId] ?? null) ? $response['user_signing_keys'][$userId] : null,
				);
				$previous = $this->store->loadCrossSigning($userId);
				if ($previous !== null && $previous->master !== null && $cross->master !== null && $previous->master !== $cross->master) {
					$this->logger->warning('Matrix master key of ' . $userId . ' changed – devices are no longer considered cross-signed');
				}
				if ($cross->master !== null) {
					$this->store->saveCrossSigning($cross);
				}
				$devices[$userId] = [];
				foreach ((is_array($response['device_keys'][$userId] ?? null) ? $response['device_keys'][$userId] : []) as $deviceId => $raw) {
					if (!is_array($raw)) {
						continue;
					}
					try {
						$device = DeviceKeys::fromArray($userId, (string)$deviceId, $raw);
					} catch (CryptoException $e) {
						$this->logger->info('Ignoring device ' . $userId . '/' . $deviceId . ': ' . $e->getMessage());
						continue;
					}
					$devices[$userId][(string)$deviceId] = $device;
					$existing = $this->store->deviceTrust($userId, (string)$deviceId);
					if ($existing === Trust::BLOCKED || $existing === Trust::VERIFIED) {
						$trust[$userId][(string)$deviceId] = $existing;
					} else {
						$trust[$userId][(string)$deviceId] = $cross->signsDevice($device) ? Trust::CROSS_SIGNED : Trust::UNKNOWN;
					}
				}
			}
			$this->store->saveDevices($devices, $trust);
		}
	}

	/** @param list<string> $userIds from sync `device_lists.changed` */
	public function markDevicesChanged(array $userIds): void {
		if ($userIds !== []) {
			$this->store->markDevicesStale($userIds);
		}
	}

	/** Whether this is our own account's other device signed by our cross-signing key. */
	public function isOwnCrossSignedDevice(DeviceKeys $device): bool {
		if ($device->userId !== $this->userId) {
			return false;
		}
		return $this->store->deviceTrust($device->userId, $device->deviceId) >= Trust::CROSS_SIGNED;
	}

	// ---- Olm: to-device ------------------------------------------------------------

	/**
	 * Encrypt one payload for many devices and send it, creating Olm sessions
	 * (claiming one-time keys) where none exist.
	 *
	 * @param list<DeviceKeys> $recipients
	 * @param array<string, mixed> $content
	 * @return list<DeviceKeys> devices that could not be reached (no keys claimable)
	 */
	public function sendEncryptedToDevice(array $recipients, string $type, array $content): array {
		$messages = [];
		$failed = [];
		$needClaim = [];
		foreach ($recipients as $device) {
			if ($this->store->deviceTrust($device->userId, $device->deviceId) === Trust::BLOCKED || !$device->supportsOlm()) {
				continue;
			}
			if ($this->latestSession($device->curve25519) === null) {
				$needClaim[$device->userId][$device->deviceId] = 'signed_curve25519';
			}
		}
		if ($needClaim !== []) {
			$this->claimAndCreateSessions($needClaim, $recipients);
		}
		foreach ($recipients as $device) {
			if ($this->store->deviceTrust($device->userId, $device->deviceId) === Trust::BLOCKED || !$device->supportsOlm()) {
				continue;
			}
			$session = $this->latestSession($device->curve25519);
			if ($session === null) {
				$failed[] = $device;
				continue;
			}
			$messages[$device->userId][$device->deviceId] = OlmEnvelope::encrypt($this->account, $this->userId, $this->deviceId, $session, $device, $type, $content);
			$this->store->saveOlmSession($device->curve25519, $session->getId(), $session->pickle());
		}
		if ($messages !== []) {
			$this->client->sendToDevice('m.room.encrypted', $messages, ($this->txnIdFactory)());
		}
		return $failed;
	}

	/**
	 * @param array<string, array<string, string>> $wanted
	 * @param list<DeviceKeys> $recipients
	 */
	private function claimAndCreateSessions(array $wanted, array $recipients): void {
		try {
			$claimed = $this->client->claimKeys($wanted);
		} catch (MatrixException $e) {
			$this->logger->warning('Claiming one-time keys failed: ' . $e->getMessage());
			return;
		}
		foreach ($recipients as $device) {
			$keys = $claimed['one_time_keys'][$device->userId][$device->deviceId] ?? null;
			if (!is_array($keys)) {
				continue;
			}
			foreach ($keys as $keyId => $keyObject) {
				if (!is_array($keyObject) || !is_string($keyObject['key'] ?? null)) {
					continue;
				}
				// The one-time key must be signed by the device's Ed25519 key
				if (!Keys::verifyJson($keyObject, $device->userId, 'ed25519:' . $device->deviceId, Base64::decode($device->ed25519))) {
					$this->logger->warning('One-time key of ' . $device->userId . '/' . $device->deviceId . ' has a bad signature, skipping');
					continue;
				}
				$session = Session::createOutbound($this->account, Base64::decode($device->curve25519), Base64::decode($keyObject['key']));
				$this->store->saveOlmSession($device->curve25519, $session->getId(), $session->pickle());
				break;
			}
		}
	}

	private function latestSession(string $theirCurve25519): ?Session {
		$sessions = $this->store->loadOlmSessions($theirCurve25519);
		if ($sessions === []) {
			return null;
		}
		// Prefer a session that has received a message (fully established), else the newest
		$best = null;
		foreach ($sessions as $pickle) {
			$session = Session::unpickle($pickle);
			if ($session->hasReceivedMessage()) {
				return $session;
			}
			$best = $session;
		}
		return $best;
	}

	/**
	 * Decrypt an incoming to-device `m.room.encrypted` (Olm) event.
	 *
	 * @param array<string, mixed> $content
	 * @return array{type: string, content: array<string, mixed>, sender: string, senderKey: string, senderDevice: string, claimedEd25519: string}|null null when not addressed to us
	 */
	public function decryptToDevice(string $sender, array $content): ?array {
		if (($content['algorithm'] ?? null) !== OlmEnvelope::ALGORITHM) {
			throw new CryptoException('Unsupported to-device algorithm');
		}
		$ours = OlmEnvelope::ourCiphertext($content, $this->account);
		if ($ours === null) {
			return null;
		}
		$senderKey = (string)($content['sender_key'] ?? '');
		if ($senderKey === '') {
			throw new CryptoException('Missing sender_key');
		}
		$plaintext = null;
		$sessions = $this->store->loadOlmSessions($senderKey);
		$body = Base64::decode($ours['body']);
		foreach ($sessions as $id => $pickle) {
			$session = Session::unpickle($pickle);
			if ($ours['type'] === Message::TYPE_PREKEY && !$session->matchesPreKeyMessage($body)) {
				continue;
			}
			try {
				$plaintext = $session->decrypt($ours['type'], $ours['body']);
				$this->store->saveOlmSession($senderKey, $session->getId(), $session->pickle());
				break;
			} catch (CryptoException $e) {
				if ($ours['type'] === Message::TYPE_PREKEY) {
					throw $e; // matching pre-key session but undecryptable: something is off
				}
				// try the next session
			}
		}
		if ($plaintext === null) {
			if ($ours['type'] !== Message::TYPE_PREKEY) {
				throw new CryptoException('No Olm session can decrypt this message');
			}
			$session = Session::createInbound($this->account, $body, Base64::decode($senderKey));
			$this->accountDirty = true;
			$plaintext = $session->decrypt($ours['type'], $ours['body']);
			$this->store->saveOlmSession($senderKey, $session->getId(), $session->pickle());
			$this->flush();
		}
		$payload = OlmEnvelope::validatePayload($plaintext, $sender, $this->account, $this->userId);
		return $payload + ['senderKey' => $senderKey];
	}

	// ---- Megolm: room keys -----------------------------------------------------------

	/**
	 * Store a room key received via Olm (m.room_key) after checking that the
	 * claimed Ed25519 key really belongs to a device with that Curve25519 key.
	 *
	 * @param array<string, mixed> $content decrypted m.room_key content
	 * @return bool whether the session was new
	 */
	public function receiveRoomKey(array $content, string $senderKey, string $claimedEd25519, string $sender, string $senderDevice): bool {
		if (($content['algorithm'] ?? null) !== MegolmEnvelope::ALGORITHM) {
			return false;
		}
		$roomId = (string)($content['room_id'] ?? '');
		$sessionId = (string)($content['session_id'] ?? '');
		$sessionKey = (string)($content['session_key'] ?? '');
		if ($roomId === '' || $sessionId === '' || $sessionKey === '') {
			throw new CryptoException('Incomplete m.room_key');
		}
		$session = InboundSession::fromSessionKey($sessionKey);
		if ($session->getId() !== $sessionId) {
			throw new CryptoException('m.room_key session id does not match the key');
		}
		$existing = $this->store->loadInboundGroupSession($roomId, $sessionId);
		if ($existing !== null && InboundSession::unpickle($existing)->getFirstKnownIndex() <= $session->getFirstKnownIndex()) {
			return false;
		}
		$this->store->saveInboundGroupSession($roomId, $sessionId, $senderKey, $session->pickle(), $session->getFirstKnownIndex(), [], 'to-device');
		return true;
	}

	/**
	 * @param array<string, mixed> $content decrypted m.forwarded_room_key content
	 */
	public function receiveForwardedRoomKey(array $content, string $forwarderKey, bool $forwarderTrusted): bool {
		if (($content['algorithm'] ?? null) !== MegolmEnvelope::ALGORITHM || !$forwarderTrusted) {
			return false;
		}
		$roomId = (string)($content['room_id'] ?? '');
		$sessionId = (string)($content['session_id'] ?? '');
		$exported = (string)($content['session_key'] ?? '');
		$senderKey = (string)($content['sender_key'] ?? '');
		if ($roomId === '' || $sessionId === '' || $exported === '' || $senderKey === '') {
			throw new CryptoException('Incomplete m.forwarded_room_key');
		}
		$session = InboundSession::fromExportedKey($exported);
		if ($session->getId() !== $sessionId) {
			throw new CryptoException('Forwarded key session id mismatch');
		}
		$existing = $this->store->loadInboundGroupSession($roomId, $sessionId);
		if ($existing !== null && InboundSession::unpickle($existing)->getFirstKnownIndex() <= $session->getFirstKnownIndex()) {
			return false;
		}
		$chain = array_values(array_filter(is_array($content['forwarding_curve25519_key_chain'] ?? null) ? $content['forwarding_curve25519_key_chain'] : [], 'is_string'));
		$chain[] = $forwarderKey;
		$this->store->saveInboundGroupSession($roomId, $sessionId, $senderKey, $session->pickle(), $session->getFirstKnownIndex(), $chain, 'forwarded');
		return true;
	}

	/**
	 * Import an exported session (key backup, manual import).
	 */
	public function importSession(string $roomId, string $exported, string $senderKey, string $source = 'backup'): bool {
		$session = InboundSession::fromExportedKey($exported);
		$existing = $this->store->loadInboundGroupSession($roomId, $session->getId());
		if ($existing !== null && InboundSession::unpickle($existing)->getFirstKnownIndex() <= $session->getFirstKnownIndex()) {
			return false;
		}
		$this->store->saveInboundGroupSession($roomId, $session->getId(), $senderKey, $session->pickle(), $session->getFirstKnownIndex(), [], $source);
		return true;
	}

	/**
	 * Decrypt a Megolm room event.
	 *
	 * @param array<string, mixed> $content
	 * @return array{type: string, content: array<string, mixed>, index: int, senderKey: string, sessionId: string}
	 * @throws MissingSessionException when we do not have the key (yet)
	 */
	public function decryptRoomEvent(string $roomId, array $content): array {
		$sessionId = (string)($content['session_id'] ?? '');
		$senderKey = (string)($content['sender_key'] ?? '');
		if ($sessionId === '') {
			throw new CryptoException('Megolm event without session_id');
		}
		$pickle = $this->store->loadInboundGroupSession($roomId, $sessionId);
		if ($pickle === null) {
			throw new MissingSessionException($roomId, $sessionId, $senderKey);
		}
		$session = InboundSession::unpickle($pickle);
		try {
			$decrypted = MegolmEnvelope::decrypt($session, $content, $roomId);
		} catch (CryptoException $e) {
			if (str_contains($e->getMessage(), 'before the first known index')) {
				throw new MissingSessionException($roomId, $sessionId, $senderKey, $e->getMessage());
			}
			throw $e;
		}
		return $decrypted + ['senderKey' => $senderKey, 'sessionId' => $sessionId];
	}

	/**
	 * Encrypt a room event, rotating / creating the outbound session as needed
	 * and sharing its key with every member device that does not have it yet.
	 *
	 * @param array<string, mixed> $content
	 * @param list<string> $memberUserIds joined + invited members
	 * @return array{content: array<string, mixed>, sharedWith: int, unreachable: int}
	 */
	public function encryptRoomEvent(string $roomId, string $type, array $content, array $memberUserIds, int $now, ?int $rotationPeriodMs, ?int $rotationMsgs, bool $onlyTrustedDevices = false): array {
		$rotationPeriodMs ??= self::ROTATION_PERIOD_MS_DEFAULT;
		$rotationMsgs ??= self::ROTATION_MSGS_DEFAULT;

		$devices = $this->devicesFor($memberUserIds);
		$session = null;
		$meta = $this->store->outboundGroupSessionMeta($roomId);
		$pickle = $this->store->loadOutboundGroupSession($roomId);
		$sharedWith = [];
		if ($pickle !== null && $meta !== null) {
			$session = OutboundSession::unpickle($pickle);
			$sharedWith = $meta['sharedWith'];
			$expired = ($now * 1000 - $session->getCreatedAt() * 1000) > $rotationPeriodMs || $session->getMessageIndex() >= $rotationMsgs;
			$memberLeft = false;
			foreach ($sharedWith as $userId => $deviceIds) {
				if (!in_array($userId, $memberUserIds, true)) {
					$memberLeft = true; // someone we shared with left the room → rotate
					break;
				}
				foreach ($deviceIds as $deviceId) {
					if (!isset($devices[$userId][$deviceId])) {
						$memberLeft = true; // device deleted → rotate
						break 2;
					}
				}
			}
			if ($expired || $memberLeft) {
				$this->store->discardOutboundGroupSession($roomId);
				$session = null;
				$sharedWith = [];
			}
		}
		if ($session === null) {
			$session = OutboundSession::create($now);
			// Keep our own inbound copy so we can read our messages back
			$own = InboundSession::fromSessionKey($session->sessionKey());
			$this->store->saveInboundGroupSession($roomId, $session->getId(), $this->getIdentityKey(), $own->pickle(), 0, [], 'self');
		}

		// Share with devices that have not got the key yet
		$recipients = [];
		foreach ($devices as $userId => $userDevices) {
			foreach ($userDevices as $deviceId => $device) {
				if ($userId === $this->userId && $deviceId === $this->deviceId) {
					continue;
				}
				if (in_array($deviceId, $sharedWith[$userId] ?? [], true) || !$device->supportsMegolm()) {
					continue;
				}
				$trust = $this->store->deviceTrust($userId, $deviceId);
				if ($trust === Trust::BLOCKED || ($onlyTrustedDevices && $trust === Trust::UNKNOWN)) {
					continue;
				}
				$recipients[] = $device;
			}
		}
		$unreachable = 0;
		if ($recipients !== []) {
			$failed = $this->sendEncryptedToDevice($recipients, 'm.room_key', MegolmEnvelope::roomKeyContent($session, $roomId));
			$failedIds = array_map(static fn (DeviceKeys $d) => $d->userId . '/' . $d->deviceId, $failed);
			$unreachable = count($failed);
			foreach ($recipients as $device) {
				if (!in_array($device->userId . '/' . $device->deviceId, $failedIds, true)) {
					$sharedWith[$device->userId][] = $device->deviceId;
				}
			}
		}

		$encrypted = MegolmEnvelope::encrypt($session, $this->account, $this->deviceId, $roomId, $type, $content);
		$this->store->saveOutboundGroupSession($roomId, $session->getId(), $session->pickle(), $sharedWith, $session->getCreatedAt(), $session->getMessageIndex());
		$this->flush();
		return ['content' => $encrypted, 'sharedWith' => count($recipients) - $unreachable, 'unreachable' => $unreachable];
	}

	/**
	 * Ask our own other devices (and the sender's) for a missing room key.
	 * @param list<DeviceKeys> $askDevices
	 */
	public function requestRoomKey(string $roomId, string $sessionId, string $requestId, array $askDevices, bool $cancel = false): void {
		$messages = [];
		foreach ($askDevices as $device) {
			$messages[$device->userId][$device->deviceId] = MegolmEnvelope::keyRequestContent($roomId, $sessionId, $requestId, $this->deviceId, $cancel);
		}
		if ($messages !== []) {
			$this->client->sendToDevice('m.room_key_request', $messages, ($this->txnIdFactory)());
		}
	}

	/**
	 * Answer a key request from one of our own cross-signed devices by forwarding the session.
	 */
	public function forwardRoomKey(string $roomId, string $sessionId, DeviceKeys $requester, string $senderKey, string $senderClaimedEd25519, array $chain): bool {
		$pickle = $this->store->loadInboundGroupSession($roomId, $sessionId);
		if ($pickle === null) {
			return false;
		}
		$session = InboundSession::unpickle($pickle);
		$failed = $this->sendEncryptedToDevice([$requester], 'm.forwarded_room_key', MegolmEnvelope::forwardedRoomKeyContent($session, $roomId, $senderKey, $senderClaimedEd25519, $chain));
		return $failed === [];
	}

	public function txnId(): string {
		return ($this->txnIdFactory)();
	}
}
