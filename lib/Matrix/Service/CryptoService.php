<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCA\Talk\CachePrefix;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Crypto\Base64;
use OCA\Talk\Matrix\Client\Crypto\CryptoException;
use OCA\Talk\Matrix\Client\Crypto\DeviceKeys;
use OCA\Talk\Matrix\Client\Crypto\Keys;
use OCA\Talk\Matrix\Client\Crypto\Megolm\InboundSession;
use OCA\Talk\Matrix\Client\Crypto\MegolmEnvelope;
use OCA\Talk\Matrix\Client\Crypto\Olm\Account as OlmAccount;
use OCA\Talk\Matrix\Client\Crypto\Olm\Message;
use OCA\Talk\Matrix\Client\Crypto\Olm\Session;
use OCA\Talk\Matrix\Client\Crypto\OlmEnvelope;
use OCA\Talk\Matrix\Client\Crypto\Verification\SasVerification;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Model\Event;
use OCA\Talk\Matrix\Client\Model\SyncBatch;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\InboundGroupSession;
use OCA\Talk\Matrix\Model\InboundGroupSessionMapper;
use OCA\Talk\Matrix\Model\MatrixDevice;
use OCA\Talk\Matrix\Model\MatrixDeviceMapper;
use OCA\Talk\Matrix\Model\OlmSession;
use OCA\Talk\Matrix\Model\OlmSessionMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Decrypts end-to-end encrypted Matrix rooms: the server is a device of
 * each linked account, publishes its keys and receives the room keys of the
 * other devices. Keys and sessions are stored encrypted with the instance
 * secret. All changes happen during the sync, under the lock of the account.
 */
class CryptoService {
	/** One-time keys kept on the homeserver, topped up when half of them were used */
	public const ONE_TIME_KEYS = 50;
	/** Seconds the result of a verification check is reused */
	public const VERIFIED_CHECK_TTL = 300;

	private ICache $verifiedCache;

	public function __construct(
		private readonly AccountMapper $accountMapper,
		private readonly MatrixDeviceMapper $deviceMapper,
		private readonly OlmSessionMapper $olmSessionMapper,
		private readonly InboundGroupSessionMapper $inboundGroupSessionMapper,
		private readonly VerificationService $verificationService,
		private readonly ICrypto $crypto,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
		ICacheFactory $cacheFactory,
	) {
		$this->verifiedCache = $cacheFactory->createDistributed(CachePrefix::MATRIX_VERIFIED);
	}

	/**
	 * Curve25519 and Ed25519 need the sodium extension
	 */
	public function isAvailable(): bool {
		return function_exists('sodium_crypto_sign_verify_detached');
	}

	/**
	 * Handle the encryption part of a sync batch, before its rooms are
	 * applied: changed devices, room keys and the keys of the device
	 *
	 * @return list<array{0: string, 1: string}> Matrix room id and session id of new room keys
	 * @throws MatrixException when the keys could not be published, so the batch is synced again
	 */
	public function processSync(Account $account, Client $client, SyncBatch $batch): array {
		if (!$this->isAvailable()) {
			return [];
		}
		$isNew = $account->getOlmAccount() === null;
		$olm = $isNew ? OlmAccount::create() : OlmAccount::unpickle($this->crypto->decrypt((string)$account->getOlmAccount()));

		foreach ($batch->changedDevices as $mxid) {
			$this->deviceMapper->markOutdated((string)$account->getId(), $mxid);
		}

		$roomKeys = [];
		foreach ($batch->toDevice as $event) {
			if (self::isVerification($event->type)) {
				$this->handleVerification($account, $client, $event->sender, $event->type, $event->content);
				continue;
			}
			if ($event->type !== 'm.room.encrypted') {
				continue;
			}
			try {
				$roomKey = $this->receiveToDevice($account, $client, $olm, $event);
			} catch (CryptoException $e) {
				$this->logger->info('Encrypted to-device event from ' . $event->sender . ' was ignored: ' . $e->getMessage());
				continue;
			}
			if ($roomKey !== null) {
				$roomKeys[] = $roomKey;
			}
		}
		// Used one-time keys are saved before anything else can fail
		$this->saveOlmAccount($account, $olm);

		$this->publishKeys($account, $client, $olm, $batch, $isNew);
		$this->saveOlmAccount($account, $olm);
		return $roomKeys;
	}

	/**
	 * @return ?Event The event with the decrypted type and content, null when the key is missing or the event is invalid
	 */
	public function decryptRoomEvent(Account $account, string $roomId, Event $event): ?Event {
		if (!$this->isAvailable()) {
			return null;
		}
		$sessionId = $event->content['session_id'] ?? null;
		$stored = is_string($sessionId) ? $this->inboundGroupSessionMapper->find((string)$account->getId(), $roomId, $sessionId) : null;
		if ($stored === null) {
			return null;
		}
		if ($stored->getSender() !== $event->sender) {
			// The room key was shared by a device of another user
			$this->logger->warning('Matrix event ' . $event->eventId . ' of ' . $event->sender . ' uses a room key of ' . $stored->getSender());
			return null;
		}

		try {
			$session = InboundSession::unpickle($this->crypto->decrypt($stored->getPickle()));
			$decrypted = MegolmEnvelope::decrypt($session, $event->content, $roomId);
		} catch (CryptoException $e) {
			$this->logger->info('Matrix event ' . $event->eventId . ' could not be decrypted: ' . $e->getMessage());
			return null;
		}
		return $event->withDecryptedContent($decrypted['type'], $decrypted['content'], (string)$sessionId, $decrypted['index']);
	}

	/**
	 * @return ?string Unpadded base64 signing key of the device of the server, null before the first sync
	 */
	public function getSigningKey(Account $account): ?string {
		$olm = $account->getOlmAccount();
		return $olm !== null ? OlmAccount::unpickle($this->crypto->decrypt($olm))->getSigningKeyBase64() : null;
	}

	/**
	 * Whether another device of the user cross-signed the device of the server, so
	 * MSC4153-compliant clients keep sharing room keys with it. The result is reused
	 * for VERIFIED_CHECK_TTL seconds unless $force is set.
	 *
	 * @return ?bool null when encryption is not available on the server
	 */
	public function isVerified(Account $account, Client $client, bool $force = false): ?bool {
		if (!$this->isAvailable()) {
			return null;
		}
		$cacheKey = (string)$account->getId();
		if (!$force) {
			$cached = $this->verifiedCache->get($cacheKey);
			if ($cached !== null) {
				return (bool)$cached;
			}
		}
		$verified = $this->checkVerified($account, $client);
		$this->verifiedCache->set($cacheKey, $verified, self::VERIFIED_CHECK_TTL);
		return $verified;
	}

	/**
	 * Forget the cached verification status, so the next check is live again
	 */
	public function forgetVerified(Account $account): void {
		$this->verifiedCache->remove((string)$account->getId());
	}

	/**
	 * Checks only that the device's keys carry a valid signature of the user's
	 * self-signing key; the self-signing key itself is trusted as published, its
	 * own signature by the master key is not verified
	 */
	protected function checkVerified(Account $account, Client $client): bool {
		try {
			$keys = $client->getVerificationKeys($account->getMxid(), $account->getDeviceId());
		} catch (MatrixException $e) {
			$this->logger->info('Verification status of ' . $account->getMxid() . ' could not be checked', ['exception' => $e]);
			return false;
		}
		$device = $keys['device'];
		$selfSigningKey = $keys['selfSigningKey'];
		if ($device === null || $selfSigningKey === null
			|| ($selfSigningKey['user_id'] ?? null) !== $account->getMxid()
			|| !in_array('self_signing', is_array($selfSigningKey['usage'] ?? null) ? $selfSigningKey['usage'] : [], true)) {
			return false;
		}

		foreach (is_array($selfSigningKey['keys'] ?? null) ? $selfSigningKey['keys'] : [] as $keyId => $publicKey) {
			if (!str_starts_with((string)$keyId, 'ed25519:') || !is_string($publicKey)) {
				continue;
			}
			try {
				if (Keys::verifyJson($device, $account->getMxid(), (string)$keyId, Base64::decode($publicKey))) {
					return true;
				}
			} catch (CryptoException) {
				// Malformed key, keep looking at the others
			}
		}
		return false;
	}

	/**
	 * Forget the keys and sessions of the device, for example when the
	 * account logged in with a new device
	 */
	public function wipe(Account $account): void {
		$this->deviceMapper->deleteForAccount((string)$account->getId());
		$this->olmSessionMapper->deleteForAccount((string)$account->getId());
		$this->inboundGroupSessionMapper->deleteForAccount((string)$account->getId());
		$this->verifiedCache->remove((string)$account->getId());
		$account->setOlmAccount(null);
	}

	/**
	 * @return ?array{0: string, 1: string} Matrix room id and session id of a new room key
	 * @throws CryptoException
	 */
	protected function receiveToDevice(Account $account, Client $client, OlmAccount $olm, Event $event): ?array {
		if (($event->content['algorithm'] ?? null) !== OlmEnvelope::ALGORITHM) {
			return null;
		}
		$ciphertext = OlmEnvelope::ourCiphertext($event->content, $olm);
		$senderKey = $event->content['sender_key'] ?? null;
		if ($ciphertext === null || !is_string($senderKey)) {
			return null;
		}

		$plaintext = $this->decryptOlm($account, $olm, $senderKey, $ciphertext['type'], $ciphertext['body']);
		$payload = OlmEnvelope::validatePayload($plaintext, $event->sender, $olm, $account->getMxid());
		if (self::isVerification($payload['type'])) {
			$this->handleVerification($account, $client, $event->sender, $payload['type'], $payload['content']);
			return null;
		}
		if ($payload['type'] !== 'm.room_key') {
			return null;
		}

		// The sender has to own the device that the key came from
		$device = $this->findDevice($account, $client, $event->sender, $senderKey);
		if ($device === null || !hash_equals($device->getEd25519(), $payload['claimedEd25519'])) {
			throw new CryptoException('Room key from an unknown device');
		}
		return $this->storeRoomKey($account, $event->sender, $senderKey, $payload['content']);
	}

	protected static function isVerification(string $type): bool {
		return str_starts_with($type, 'm.key.verification.');
	}

	/**
	 * Only other devices of the user verify the device of the server
	 *
	 * A failing verification event is ignored, so it does not block the sync
	 *
	 * @param array<string, mixed> $content
	 */
	protected function handleVerification(Account $account, Client $client, string $sender, string $type, array $content): void {
		if ($sender !== $account->getMxid()) {
			return;
		}
		try {
			$verification = $this->verificationService->handle($account, $client, $type, $content, function (string $deviceId) use ($account, $client): ?string {
				$devices = $this->deviceMapper->findForUser((string)$account->getId(), $account->getMxid());
				if (!isset($devices[$deviceId]) || $devices[$deviceId]->getOutdated()) {
					$devices = $this->queryDevices($account, $client, $account->getMxid(), $devices);
				}
				return isset($devices[$deviceId]) ? $devices[$deviceId]->getEd25519() : null;
			});
			if ($verification?->state === SasVerification::STATE_DONE) {
				// The other device just cross-signed us, the cached verified status is stale
				$this->forgetVerified($account);
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Matrix verification event ' . $type . ' was ignored for ' . $account->getMxid(), ['exception' => $e]);
		}
	}

	/**
	 * @throws CryptoException
	 */
	protected function decryptOlm(Account $account, OlmAccount $olm, string $senderKey, int $type, string $body): string {
		foreach ($this->olmSessionMapper->findForDevice((string)$account->getId(), $senderKey) as $stored) {
			$session = Session::unpickle($this->crypto->decrypt($stored->getPickle()));
			if ($type === Message::TYPE_PREKEY && !$session->matchesPreKeyMessage(Base64::decode($body))) {
				continue;
			}
			try {
				$plaintext = $session->decrypt($type, $body);
			} catch (CryptoException $e) {
				if ($type === Message::TYPE_PREKEY) {
					throw $e;
				}
				continue;
			}
			$stored->setPickle($this->crypto->encrypt($session->pickle()));
			$stored->setLastUsed($this->timeFactory->getTime());
			$this->olmSessionMapper->update($stored);
			return $plaintext;
		}

		if ($type !== Message::TYPE_PREKEY) {
			throw new CryptoException('No Olm session for the message');
		}
		['session' => $session, 'plaintext' => $plaintext] = Session::fromPreKeyMessage($olm, $body, Base64::decode($senderKey));
		$stored = new OlmSession();
		$stored->setAccountId((string)$account->getId());
		$stored->setSenderKey($senderKey);
		$stored->setSessionId($session->getId());
		$stored->setPickle($this->crypto->encrypt($session->pickle()));
		$stored->setLastUsed($this->timeFactory->getTime());
		$this->olmSessionMapper->insert($stored);
		return $plaintext;
	}

	/**
	 * Device of a user with the identity key, the devices are queried again
	 * when they changed or the key is unknown
	 */
	protected function findDevice(Account $account, Client $client, string $mxid, string $curve25519): ?MatrixDevice {
		$devices = $this->deviceMapper->findForUser((string)$account->getId(), $mxid);
		$device = self::matchDevice($devices, $curve25519);
		if ($device === null || $device->getOutdated()) {
			$device = self::matchDevice($this->queryDevices($account, $client, $mxid, $devices), $curve25519);
		}
		return $device;
	}

	/**
	 * @param array<string, MatrixDevice> $devices
	 */
	protected static function matchDevice(array $devices, string $curve25519): ?MatrixDevice {
		foreach ($devices as $device) {
			if (hash_equals($device->getCurve25519(), $curve25519)) {
				return $device;
			}
		}
		return null;
	}

	/**
	 * @param array<string, MatrixDevice> $known Stored devices of the user
	 * @return array<string, MatrixDevice> Current devices of the user
	 */
	protected function queryDevices(Account $account, Client $client, string $mxid, array $known): array {
		try {
			$published = $client->queryKeys([$mxid])[$mxid] ?? [];
		} catch (MatrixException $e) {
			$this->logger->info('Devices of ' . $mxid . ' could not be queried', ['exception' => $e]);
			return $known;
		}

		$devices = [];
		foreach ($published as $deviceId => $raw) {
			try {
				$keys = DeviceKeys::fromArray($mxid, $deviceId, $raw);
			} catch (CryptoException $e) {
				$this->logger->info('Device ' . $deviceId . ' of ' . $mxid . ' was ignored: ' . $e->getMessage());
				continue;
			}

			$device = $known[$deviceId] ?? null;
			if ($device !== null && ($device->getCurve25519() !== $keys->curve25519 || $device->getEd25519() !== $keys->ed25519)) {
				// Devices never change their keys, so the new keys are not trusted
				$this->logger->warning('Device ' . $deviceId . ' of ' . $mxid . ' was published with different keys');
			} elseif ($device === null) {
				$device = new MatrixDevice();
				$device->setAccountId((string)$account->getId());
				$device->setMxid($mxid);
				$device->setDeviceId($deviceId);
				$device->setCurve25519($keys->curve25519);
				$device->setEd25519($keys->ed25519);
				$device = $this->deviceMapper->insert($device);
			}
			if ($device->getOutdated()) {
				$device->setOutdated(false);
				$this->deviceMapper->update($device);
			}
			$devices[$deviceId] = $device;
		}

		foreach (array_diff_key($known, $devices) as $removed) {
			$this->deviceMapper->delete($removed);
		}
		return $devices;
	}

	/**
	 * @param array<string, mixed> $content m.room_key content
	 * @return ?array{0: string, 1: string} Matrix room id and session id when the key is new
	 * @throws CryptoException
	 */
	protected function storeRoomKey(Account $account, string $sender, string $senderKey, array $content): ?array {
		$roomId = $content['room_id'] ?? null;
		$sessionId = $content['session_id'] ?? null;
		$sessionKey = $content['session_key'] ?? null;
		if (($content['algorithm'] ?? null) !== MegolmEnvelope::ALGORITHM || !is_string($roomId) || !is_string($sessionId) || !is_string($sessionKey)) {
			return null;
		}
		$session = InboundSession::fromSessionKey($sessionKey);
		if ($session->getId() !== $sessionId) {
			throw new CryptoException('Room key does not match the session id');
		}

		$stored = $this->inboundGroupSessionMapper->find((string)$account->getId(), $roomId, $sessionId);
		if ($stored !== null) {
			// Only the same device can share an earlier state of the session
			if ($stored->getSender() !== $sender || $stored->getSenderKey() !== $senderKey || $stored->getFirstKnownIndex() <= $session->getFirstKnownIndex()) {
				return null;
			}
		} else {
			$stored = new InboundGroupSession();
			$stored->setAccountId((string)$account->getId());
			$stored->setRoomId($roomId);
			$stored->setSessionId($sessionId);
			$stored->setSender($sender);
			$stored->setSenderKey($senderKey);
		}
		$stored->setFirstKnownIndex($session->getFirstKnownIndex());
		$stored->setPickle($this->crypto->encrypt($session->pickle()));
		$stored->getId() === null ? $this->inboundGroupSessionMapper->insert($stored) : $this->inboundGroupSessionMapper->update($stored);
		return [$roomId, $sessionId];
	}

	/**
	 * Upload the device keys with new one-time keys when the device is new
	 * or half of the one-time keys were used, and a new fallback key once
	 * the old one was used
	 *
	 * @throws MatrixException
	 */
	protected function publishKeys(Account $account, Client $client, OlmAccount $olm, SyncBatch $batch, bool $isNew): void {
		$needsOneTimeKeys = $isNew || ($batch->oneTimeKeyCount !== null && $batch->oneTimeKeyCount < self::ONE_TIME_KEYS / 2);
		$needsFallbackKey = $isNew || ($batch->unusedFallbackKeyTypes !== null && !in_array('signed_curve25519', $batch->unusedFallbackKeyTypes, true));
		if ($needsOneTimeKeys) {
			$olm->generateOneTimeKeys(self::ONE_TIME_KEYS - ($batch->oneTimeKeyCount ?? 0));
		}
		if ($needsFallbackKey) {
			$olm->generateFallbackKey();
		}

		$keys = $olm->keysForUpload($account->getMxid(), $account->getDeviceId());
		if ($keys['one_time_keys'] === [] && $keys['fallback_keys'] === []) {
			return;
		}
		$client->uploadKeys($olm->deviceKeys($account->getMxid(), $account->getDeviceId()), $keys['one_time_keys'], $keys['fallback_keys']);
		$olm->markKeysAsPublished();
	}

	protected function saveOlmAccount(Account $account, OlmAccount $olm): void {
		$account->setOlmAccount($this->crypto->encrypt($olm->pickle()));
		$this->accountMapper->update($account);
	}
}
