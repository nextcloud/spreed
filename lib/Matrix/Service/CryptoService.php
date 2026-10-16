<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCA\Talk\CachePrefix;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Crypto\Backup;
use OCA\Talk\Matrix\Client\Crypto\Base64;
use OCA\Talk\Matrix\Client\Crypto\CryptoException;
use OCA\Talk\Matrix\Client\Crypto\DeviceKeys;
use OCA\Talk\Matrix\Client\Crypto\Keys;
use OCA\Talk\Matrix\Client\Crypto\Megolm\InboundSession;
use OCA\Talk\Matrix\Client\Crypto\Megolm\OutboundSession;
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
use OCA\Talk\Matrix\Model\OutboundGroupSession;
use OCA\Talk\Matrix\Model\OutboundGroupSessionMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

/**
 * Decrypts end-to-end encrypted Matrix rooms: the server is a device of
 * each linked account, publishes its keys and receives the room keys of the
 * other devices. Keys and sessions are stored encrypted with the instance
 * secret. Sessions are changed by the sync and when sending, so both run
 * under a lock of the account.
 */
class CryptoService {
	/** One-time keys kept on the homeserver, topped up when half of them were used */
	public const ONE_TIME_KEYS = 50;
	/** Messages and seconds after which a new room key is used, the defaults of the specification */
	public const ROTATION_MESSAGES = 100;
	public const ROTATION_SECONDS = 7 * 24 * 3600;
	/** Seconds the result of a verification check is reused */
	public const VERIFIED_CHECK_TTL = 300;
	/** Room keys requested at most per room and sync */
	public const KEY_REQUESTS = 10;
	/** Seconds before a new Olm session is started again with a device whose messages can not be decrypted */
	public const UNWEDGE_INTERVAL = 3600;

	private ICache $verifiedCache;
	private ICache $unwedgeCache;

	public function __construct(
		private readonly AccountMapper $accountMapper,
		private readonly MatrixDeviceMapper $deviceMapper,
		private readonly OlmSessionMapper $olmSessionMapper,
		private readonly InboundGroupSessionMapper $inboundGroupSessionMapper,
		private readonly OutboundGroupSessionMapper $outboundGroupSessionMapper,
		private readonly VerificationService $verificationService,
		private readonly ExclusiveLock $lock,
		private readonly ISecureRandom $secureRandom,
		private readonly ICrypto $crypto,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
		ICacheFactory $cacheFactory,
	) {
		$this->verifiedCache = $cacheFactory->createDistributed(CachePrefix::MATRIX_VERIFIED);
		$this->unwedgeCache = $cacheFactory->createDistributed(CachePrefix::MATRIX_UNWEDGE);
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
		return $this->lock->run('crypto/' . $account->getId(), fn (): array => $this->processSyncLocked($account, $client, $batch));
	}

	/**
	 * @return list<array{0: string, 1: string}>
	 * @throws MatrixException
	 */
	protected function processSyncLocked(Account $account, Client $client, SyncBatch $batch): array {
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
	public function decryptRoomEvent(Account $account, Client $client, string $roomId, Event $event): ?Event {
		if (!$this->isAvailable()) {
			return null;
		}
		$sessionId = $event->content['session_id'] ?? null;
		$stored = is_string($sessionId) ? $this->inboundGroupSessionMapper->find((string)$account->getId(), $roomId, $sessionId) : null;
		if ($stored === null) {
			return null;
		}
		if ($stored->getSender() === '' && $this->findDevice($account, $client, $event->sender, $stored->getSenderKey()) !== null) {
			// A forwarded room key belongs to the user that owns the device it was created on
			$stored->setSender($event->sender);
			$this->inboundGroupSessionMapper->update($stored);
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
	 * Encrypt an event for a room. The room key is shared with the devices of
	 * the members that do not have it yet, and replaced regularly and when a
	 * device that had it is not in the room anymore.
	 *
	 * @param list<string> $members Matrix user ids of the joined and invited members
	 * @param array<string, mixed> $content
	 * @param ?int $rotationPeriodMs Rotation of the room, only used when shorter than ROTATION_SECONDS
	 * @param ?int $rotationPeriodMsgs Rotation of the room, only used when shorter than ROTATION_MESSAGES
	 * @return array<string, mixed> Content of the m.room.encrypted event
	 * @throws CryptoException when the device has no keys yet
	 * @throws MatrixException
	 */
	public function encryptRoomEvent(Account $account, Client $client, string $roomId, array $members, string $type, array $content, ?int $rotationPeriodMs = null, ?int $rotationPeriodMsgs = null): array {
		if (!$this->isAvailable() || $account->getOlmAccount() === null) {
			throw new CryptoException('The device has no keys yet');
		}
		$rotationMessages = min(self::ROTATION_MESSAGES, $rotationPeriodMsgs ?? self::ROTATION_MESSAGES);
		$rotationSeconds = min(self::ROTATION_SECONDS, intdiv($rotationPeriodMs ?? self::ROTATION_SECONDS * 1000, 1000));
		return $this->lock->run('crypto/' . $account->getId(), function () use ($account, $client, $roomId, $members, $type, $content, $rotationMessages, $rotationSeconds): array {
			$olm = OlmAccount::unpickle($this->crypto->decrypt((string)$account->getOlmAccount()));
			$recipients = [];
			foreach ($this->getDevices($account, $client, $members) as $mxid => $devices) {
				foreach ($devices as $device) {
					if ($mxid !== $account->getMxid() || $device->getDeviceId() !== $account->getDeviceId()) {
						$recipients[$mxid . '|' . $device->getDeviceId()] = $device;
					}
				}
			}

			$stored = $this->outboundGroupSessionMapper->find((string)$account->getId(), $roomId);
			$session = $stored !== null ? OutboundSession::unpickle($this->crypto->decrypt($stored->getPickle())) : null;
			/** @var list<string> $sharedWith */
			$sharedWith = $stored !== null ? json_decode($stored->getSharedWith(), true) : [];
			if ($session === null
				|| $session->getMessageIndex() >= $rotationMessages
				|| $session->getCreatedAt() + $rotationSeconds <= $this->timeFactory->getTime()
				|| array_diff($sharedWith, array_keys($recipients)) !== []) {
				$session = OutboundSession::create($this->timeFactory->getTime());
				$sharedWith = [];
				// Our own messages can be decrypted when they come back with the sync
				$this->storeRoomKey($account, $account->getMxid(), $olm->getIdentityKeyBase64(), MegolmEnvelope::roomKeyContent($session, $roomId));
			}

			$missing = array_diff_key($recipients, array_flip($sharedWith));
			$sharedWith = array_merge($sharedWith, $this->shareRoomKey($account, $client, $olm, $roomId, $session, $missing));
			$encrypted = MegolmEnvelope::encrypt($session, $olm, $account->getDeviceId(), $roomId, $type, $content);

			// Stored before the event is sent, so a message index is never used twice
			$stored ??= new OutboundGroupSession();
			$stored->setAccountId((string)$account->getId());
			$stored->setRoomId($roomId);
			$stored->setPickle($this->crypto->encrypt($session->pickle()));
			$stored->setSharedWith(json_encode($sharedWith, JSON_THROW_ON_ERROR));
			$stored->getId() === null ? $this->outboundGroupSessionMapper->insert($stored) : $this->outboundGroupSessionMapper->update($stored);
			return $encrypted;
		});
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
		$this->outboundGroupSessionMapper->deleteForAccount((string)$account->getId());
		$this->verifiedCache->remove((string)$account->getId());
		$account->setOlmAccount(null);
		$account->setBackupKey(null);
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

		try {
			$plaintext = $this->decryptOlm($account, $olm, $senderKey, $ciphertext['type'], $ciphertext['body']);
		} catch (CryptoException $e) {
			$this->unwedge($account, $client, $olm, $event->sender, $senderKey);
			throw $e;
		}
		$payload = OlmEnvelope::validatePayload($plaintext, $event->sender, $olm, $account->getMxid());
		if (self::isVerification($payload['type'])) {
			$this->handleVerification($account, $client, $event->sender, $payload['type'], $payload['content']);
			return null;
		}
		if ($payload['type'] !== 'm.room_key' && $payload['type'] !== 'm.forwarded_room_key') {
			return null;
		}

		// The sender has to own the device that the key came from
		$device = $this->findDevice($account, $client, $event->sender, $senderKey);
		if ($device === null || !hash_equals($device->getEd25519(), $payload['claimedEd25519'])) {
			throw new CryptoException('Room key from an unknown device');
		}
		if ($payload['type'] === 'm.room_key') {
			return $this->storeRoomKey($account, $event->sender, $senderKey, $payload['content']);
		}
		// Other devices of the user answer key requests, the device has to be verified with emoji
		if ($event->sender !== $account->getMxid() || !$device->getVerified()) {
			throw new CryptoException('Forwarded room key from a device that is not verified');
		}
		return $this->importRoomKey($account, $payload['content']);
	}

	/**
	 * A room key that another device of the user or the key backup had, the
	 * user that shared it originally is only known by the identity key of their device
	 *
	 * @param array<string, mixed> $content m.forwarded_room_key content
	 * @return ?array{0: string, 1: string} Matrix room id and session id when the key is new or goes back further
	 * @throws CryptoException
	 */
	protected function importRoomKey(Account $account, array $content): ?array {
		$roomId = $content['room_id'] ?? null;
		$sessionId = $content['session_id'] ?? null;
		$sessionKey = $content['session_key'] ?? null;
		$senderKey = $content['sender_key'] ?? null;
		if (($content['algorithm'] ?? null) !== MegolmEnvelope::ALGORITHM || !is_string($roomId) || !is_string($sessionId) || !is_string($sessionKey) || !is_string($senderKey)) {
			return null;
		}
		$session = InboundSession::fromExportedKey($sessionKey);
		if ($session->getId() !== $sessionId) {
			throw new CryptoException('Forwarded room key does not match the session id');
		}

		$stored = $this->inboundGroupSessionMapper->find((string)$account->getId(), $roomId, $sessionId);
		if ($stored !== null) {
			if ($stored->getSenderKey() !== $senderKey || !$this->isEarlierState($stored, $session)) {
				return null;
			}
		} else {
			$stored = new InboundGroupSession();
			$stored->setAccountId((string)$account->getId());
			$stored->setRoomId($roomId);
			$stored->setSessionId($sessionId);
			// Set when the first message is decrypted, see decryptRoomEvent()
			$stored->setSender('');
			$stored->setSenderKey($senderKey);
		}
		$stored->setFirstKnownIndex($session->getFirstKnownIndex());
		$stored->setPickle($this->crypto->encrypt($session->pickle()));
		$stored->getId() === null ? $this->inboundGroupSessionMapper->insert($stored) : $this->inboundGroupSessionMapper->update($stored);
		return [$roomId, $sessionId];
	}

	/**
	 * Ask the other devices of the user for the room keys of events that could not be decrypted
	 *
	 * @param list<Event> $events Encrypted events
	 * @return list<array{0: string, 1: string}> Matrix room id and session id of room keys found in the key backup
	 * @throws MatrixException
	 */
	public function requestRoomKeys(Account $account, Client $client, string $roomId, array $events): array {
		if (!$this->isAvailable() || $account->getOlmAccount() === null) {
			return [];
		}
		$backup = $account->getBackupKey() !== null ? json_decode($this->crypto->decrypt($account->getBackupKey()), true) : null;
		$roomKeys = [];
		$requested = [];
		foreach ($events as $event) {
			$sessionId = $event->content['session_id'] ?? null;
			$senderKey = $event->content['sender_key'] ?? null;
			if (!is_string($sessionId) || isset($requested[$sessionId]) || count($requested) >= self::KEY_REQUESTS
				|| $this->inboundGroupSessionMapper->find((string)$account->getId(), $roomId, $sessionId) !== null) {
				continue;
			}
			$requested[$sessionId] = true;
			if (is_array($backup)) {
				$roomKey = $this->getFromBackup($account, $client, Base64::decode($backup['key']), $backup['version'], $roomId, $sessionId);
				if ($roomKey !== null) {
					$roomKeys[] = $roomKey;
					continue;
				}
			}
			$requestId = $this->secureRandom->generate(24, ISecureRandom::CHAR_ALPHANUMERIC);
			$body = ['algorithm' => MegolmEnvelope::ALGORITHM, 'room_id' => $roomId, 'session_id' => $sessionId];
			if (is_string($senderKey)) {
				$body['sender_key'] = $senderKey;
			}
			$client->sendToDevice('m.room_key_request', [$account->getMxid() => ['*' => [
				'action' => 'request',
				'body' => $body,
				'request_id' => $requestId,
				'requesting_device_id' => $account->getDeviceId(),
			]]], $requestId);
		}
		return $roomKeys;
	}

	/**
	 * Restore the room keys of the key backup with the recovery key of the
	 * user. The key of the backup is kept, missing room keys are looked up in
	 * the backup later on.
	 *
	 * @return list<array{0: string, 1: string}> Matrix room id and session id of new room keys
	 * @throws \InvalidArgumentException `unavailable`, `recovery-key` when the key is wrong, `backup` when there is no backup
	 * @throws MatrixException
	 */
	public function restoreBackup(Account $account, Client $client, #[\SensitiveParameter] string $recoveryKey): array {
		if (!$this->isAvailable()) {
			throw new \InvalidArgumentException('unavailable');
		}
		try {
			$key = Backup::decodeRecoveryKey($recoveryKey);
		} catch (CryptoException) {
			throw new \InvalidArgumentException('recovery-key');
		}

		try {
			$keyId = $client->getAccountData($account->getMxid(), 'm.secret_storage.default_key')['key'] ?? null;
			if (!is_string($keyId)) {
				throw new \InvalidArgumentException('backup');
			}
			$description = $client->getAccountData($account->getMxid(), 'm.secret_storage.key.' . $keyId);
			$secret = $client->getAccountData($account->getMxid(), Backup::SECRET_NAME)['encrypted'][$keyId] ?? null;
			$version = $client->getRoomKeysVersion();
		} catch (MatrixException $e) {
			if ($e->getErrcode() === 'M_NOT_FOUND') {
				throw new \InvalidArgumentException('backup');
			}
			throw $e;
		}
		if (!is_array($secret) || ($version['algorithm'] ?? null) !== Backup::ALGORITHM || !is_string($version['version'] ?? null)) {
			throw new \InvalidArgumentException('backup');
		}
		if (!Backup::matchesKeyDescription($key, $description)) {
			throw new \InvalidArgumentException('recovery-key');
		}
		try {
			$backupKey = Base64::decode(Backup::decryptSecret($key, Backup::SECRET_NAME, $secret));
		} catch (CryptoException) {
			throw new \InvalidArgumentException('recovery-key');
		}
		if (strlen($backupKey) !== 32 || !hash_equals((string)($version['auth_data']['public_key'] ?? ''), Base64::encode(Keys::curve25519PublicKey($backupKey)))) {
			// The backup was made with another key
			throw new \InvalidArgumentException('backup');
		}

		$account->setBackupKey($this->crypto->encrypt(json_encode(['key' => Base64::encode($backupKey), 'version' => $version['version']], JSON_THROW_ON_ERROR)));
		$this->accountMapper->update($account);

		$sessions = $client->getRoomKeys($version['version']);
		return $this->lock->run('crypto/' . $account->getId(), function () use ($account, $backupKey, $sessions): array {
			$roomKeys = [];
			foreach ($sessions as $roomId => $roomSessions) {
				foreach ($roomSessions as $sessionId => $data) {
					$roomKey = $this->importBackupSession($account, $backupKey, $roomId, $sessionId, $data);
					if ($roomKey !== null) {
						$roomKeys[] = $roomKey;
					}
				}
			}
			return $roomKeys;
		});
	}

	/**
	 * @return ?array{0: string, 1: string} Matrix room id and session id when the backup has the room key
	 * @throws MatrixException
	 */
	protected function getFromBackup(Account $account, Client $client, string $backupKey, string $version, string $roomId, string $sessionId): ?array {
		try {
			$data = $client->getRoomKeys($version, $roomId, $sessionId)[$roomId][$sessionId] ?? [];
		} catch (MatrixException $e) {
			if ($e->getErrcode() === 'M_NOT_FOUND') {
				return null;
			}
			throw $e;
		}
		return $this->lock->run('crypto/' . $account->getId(), fn (): ?array => $this->importBackupSession($account, $backupKey, $roomId, $sessionId, $data));
	}

	/**
	 * @param array<string, mixed> $data Session of the backup
	 * @return ?array{0: string, 1: string} Matrix room id and session id when the room key is new
	 */
	protected function importBackupSession(Account $account, string $backupKey, string $roomId, string $sessionId, array $data): ?array {
		try {
			$content = Backup::decryptSession($backupKey, is_array($data['session_data'] ?? null) ? $data['session_data'] : []);
			return $this->importRoomKey($account, ['room_id' => $roomId, 'session_id' => $sessionId] + $content);
		} catch (CryptoException $e) {
			$this->logger->info('Session ' . $sessionId . ' of the key backup could not be restored: ' . $e->getMessage());
			return null;
		}
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
		$findDevice = function (string $deviceId) use ($account, $client): ?MatrixDevice {
			$mxid = $account->getMxid();
			return $this->getDevices($account, $client, [$mxid])[$mxid][$deviceId]
				?? $this->getDevices($account, $client, [$mxid], true)[$mxid][$deviceId]
				?? null;
		};
		try {
			$verification = $this->verificationService->handle($account, $client, $type, $content, fn (string $deviceId): ?string => $findDevice($deviceId)?->getEd25519());
			if ($verification?->state !== SasVerification::STATE_DONE) {
				return;
			}
			// The other device just cross-signed us, the cached verified status is stale
			$this->forgetVerified($account);

			// The other device proved it has the key, so the room keys it forwards are trusted
			$device = $verification->theirDeviceId !== null ? $findDevice($verification->theirDeviceId) : null;
			if ($device !== null && !$device->getVerified() && hash_equals($device->getEd25519(), (string)$verification->getTheirEd25519())) {
				$device->setVerified(true);
				$this->deviceMapper->update($device);
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
	 * when the key is unknown
	 */
	protected function findDevice(Account $account, Client $client, string $mxid, string $curve25519): ?MatrixDevice {
		return self::matchDevice($this->getDevices($account, $client, [$mxid])[$mxid], $curve25519)
			?? self::matchDevice($this->getDevices($account, $client, [$mxid], true)[$mxid], $curve25519);
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
	 * Devices of users, queried from the homeserver when none are known, they
	 * changed or $refresh is set
	 *
	 * @param list<string> $mxids
	 * @return array<string, array<string, MatrixDevice>> indexed by user id and device id
	 */
	protected function getDevices(Account $account, Client $client, array $mxids, bool $refresh = false): array {
		$devices = $outdated = [];
		foreach (array_unique($mxids) as $mxid) {
			$devices[$mxid] = $this->deviceMapper->findForUser((string)$account->getId(), $mxid);
			if ($refresh || $devices[$mxid] === [] || array_filter($devices[$mxid], static fn (MatrixDevice $device): bool => $device->getOutdated()) !== []) {
				$outdated[] = $mxid;
			}
		}
		if ($outdated === []) {
			return $devices;
		}

		try {
			$published = $client->queryKeys($outdated);
		} catch (MatrixException $e) {
			$this->logger->info('Devices of ' . implode(', ', $outdated) . ' could not be queried', ['exception' => $e]);
			return $devices;
		}
		foreach ($outdated as $mxid) {
			// Users the homeserver could not query keep their known devices
			if (isset($published[$mxid])) {
				$devices[$mxid] = $this->updateDevices($account, $mxid, $devices[$mxid], $published[$mxid]);
			}
		}
		return $devices;
	}

	/**
	 * @param array<string, MatrixDevice> $known Stored devices of the user
	 * @param array<string, array<string, mixed>> $published Signed device keys indexed by device id
	 * @return array<string, MatrixDevice> Current devices of the user
	 */
	protected function updateDevices(Account $account, string $mxid, array $known, array $published): array {
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
	 * Send the room key to devices, over an existing Olm session or a new one
	 * started with a one-time key of the device
	 *
	 * @param array<string, MatrixDevice> $devices indexed by `user id|device id`
	 * @return list<string> The devices that got the key, as `user id|device id`
	 * @throws MatrixException
	 */
	protected function shareRoomKey(Account $account, Client $client, OlmAccount $olm, string $roomId, OutboundSession $session, array $devices): array {
		$sessions = $withoutSession = [];
		foreach ($devices as $key => $device) {
			$stored = $this->olmSessionMapper->findForDevice((string)$account->getId(), $device->getCurve25519())[0] ?? null;
			if ($stored === null) {
				$withoutSession[$key] = $device;
			} else {
				$sessions[$key] = $stored;
			}
		}

		$sessions += $this->createOlmSessions($account, $client, $olm, $withoutSession);
		$this->sendOlm($account, $client, $olm, $devices, $sessions, 'm.room_key', MegolmEnvelope::roomKeyContent($session, $roomId));
		return array_keys($sessions);
	}

	/**
	 * Start new Olm sessions with one-time keys of the devices
	 *
	 * @param array<string, MatrixDevice> $devices
	 * @return array<string, OlmSession> Sessions of the devices that had a valid one-time key, with the keys of $devices
	 * @throws MatrixException
	 */
	protected function createOlmSessions(Account $account, Client $client, OlmAccount $olm, array $devices): array {
		if ($devices === []) {
			return [];
		}
		$claim = [];
		foreach ($devices as $device) {
			$claim[$device->getMxid()][$device->getDeviceId()] = 'signed_curve25519';
		}
		$claimed = $client->claimKeys($claim);
		$sessions = [];
		foreach ($devices as $key => $device) {
			$oneTimeKey = self::verifiedOneTimeKey($device, $claimed[$device->getMxid()][$device->getDeviceId()] ?? []);
			if ($oneTimeKey === null) {
				$this->logger->info('Device ' . $device->getDeviceId() . ' of ' . $device->getMxid() . ' has no valid one-time key, no Olm session was started');
				continue;
			}
			$olmSession = Session::createOutbound($olm, Base64::decode($device->getCurve25519()), Base64::decode($oneTimeKey));
			$stored = new OlmSession();
			$stored->setAccountId((string)$account->getId());
			$stored->setSenderKey($device->getCurve25519());
			$stored->setSessionId($olmSession->getId());
			$stored->setPickle($this->crypto->encrypt($olmSession->pickle()));
			$sessions[$key] = $this->olmSessionMapper->insert($stored);
		}
		return $sessions;
	}

	/**
	 * Send an event to devices, encrypted with their Olm session
	 *
	 * @param array<string, MatrixDevice> $devices
	 * @param array<string, OlmSession> $sessions With the keys of $devices
	 * @param array<string, mixed> $content
	 * @throws MatrixException
	 */
	protected function sendOlm(Account $account, Client $client, OlmAccount $olm, array $devices, array $sessions, string $type, array $content): void {
		$messages = [];
		foreach ($sessions as $key => $stored) {
			$device = $devices[$key];
			$olmSession = Session::unpickle($this->crypto->decrypt($stored->getPickle()));
			$recipient = new DeviceKeys($device->getMxid(), $device->getDeviceId(), $device->getCurve25519(), $device->getEd25519(), [], []);
			$messages[$device->getMxid()][$device->getDeviceId()] = OlmEnvelope::encrypt($olm, $account->getMxid(), $account->getDeviceId(), $olmSession, $recipient, $type, $content);
			$stored->setPickle($this->crypto->encrypt($olmSession->pickle()));
			$stored->setLastUsed($this->timeFactory->getTime());
			$this->olmSessionMapper->update($stored);
		}
		if ($messages !== []) {
			$client->sendToDevice('m.room.encrypted', $messages, $this->secureRandom->generate(24, ISecureRandom::CHAR_ALPHANUMERIC));
		}
	}

	/**
	 * A message of the device could not be decrypted, so the device may have
	 * lost its Olm session: a new one is started with an m.dummy event, at most
	 * once per UNWEDGE_INTERVAL
	 */
	protected function unwedge(Account $account, Client $client, OlmAccount $olm, string $mxid, string $senderKey): void {
		$cacheKey = $account->getId() . '/' . $senderKey;
		if ($this->unwedgeCache->get($cacheKey) !== null) {
			return;
		}
		$this->unwedgeCache->set($cacheKey, true, self::UNWEDGE_INTERVAL);

		try {
			$device = $this->findDevice($account, $client, $mxid, $senderKey);
			if ($device === null) {
				return;
			}
			$devices = ['device' => $device];
			$this->sendOlm($account, $client, $olm, $devices, $this->createOlmSessions($account, $client, $olm, $devices), 'm.dummy', []);
		} catch (MatrixException $e) {
			$this->logger->info('No new Olm session could be started with a device of ' . $mxid, ['exception' => $e]);
		}
	}

	/**
	 * @param array<string, mixed> $claimed Claimed keys of the device, indexed by key id
	 * @return ?string Unpadded base64 one-time key, when the device signed it
	 */
	protected static function verifiedOneTimeKey(MatrixDevice $device, array $claimed): ?string {
		foreach ($claimed as $keyId => $signed) {
			if (str_starts_with((string)$keyId, 'signed_curve25519:') && is_array($signed) && is_string($signed['key'] ?? null)
				&& Keys::verifyJson($signed, $device->getMxid(), 'ed25519:' . $device->getDeviceId(), Base64::decode($device->getEd25519()))) {
				return $signed['key'];
			}
		}
		return null;
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
			if ($stored->getSender() !== $sender || $stored->getSenderKey() !== $senderKey || !$this->isEarlierState($stored, $session)) {
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
	 * Whether the room key goes back further than the stored one and is the
	 * same session, so replacing the stored key does not break decryption
	 */
	protected function isEarlierState(InboundGroupSession $stored, InboundSession $session): bool {
		if ($stored->getFirstKnownIndex() <= $session->getFirstKnownIndex()) {
			return false;
		}
		if (!$session->isEarlierStateOf(InboundSession::unpickle($this->crypto->decrypt($stored->getPickle())))) {
			$this->logger->warning('Room key ' . $stored->getSessionId() . ' was ignored, it does not match the stored state of the session');
			return false;
		}
		return true;
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
