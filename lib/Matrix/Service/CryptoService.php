<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\DeviceKeys;
use Nextcloud\Matrix\Crypto\Machine;
use Nextcloud\Matrix\Crypto\Megolm\InboundSession;
use Nextcloud\Matrix\Crypto\MissingSessionException;
use Nextcloud\Matrix\Crypto\Trust;
use Nextcloud\Matrix\Crypto\Verification\SasVerification;
use Nextcloud\Matrix\Exception\MatrixException;
use Nextcloud\Matrix\Model\SyncBatch;
use OCA\Talk\Matrix\Adapter\CryptoStore;
use OCA\Talk\Matrix\ClientFactory;
use OCA\Talk\Matrix\MatrixConfig;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\Homeserver;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

/**
 * Talk-side E2EE orchestration around the library's {@see Machine}: one
 * machine per linked account, to-device processing, room key bookkeeping,
 * device verification, and the policies from the admin settings.
 */
class CryptoService {
	private const VERIFICATION_PREFIX = 'verification:';
	private const KEY_REQUEST_PREFIX = 'keyreq:';
	private const KEY_REQUEST_RETRY_SECONDS = 600;
	private const KEY_REQUEST_MAX = 3;

	/** @var array<int, Machine> */
	private array $machines = [];
	/** @var array<int, CryptoStore> */
	private array $stores = [];

	public function __construct(
		private readonly IDBConnection $db,
		private readonly ICrypto $crypto,
		private readonly AccountMapper $accountMapper,
		private readonly ClientFactory $clientFactory,
		private readonly MatrixConfig $config,
		private readonly ITimeFactory $timeFactory,
		private readonly ISecureRandom $random,
		private readonly LoggerInterface $logger,
	) {
	}

	public function store(Account $account): CryptoStore {
		return $this->stores[$account->getId()] ??= new CryptoStore($this->db, $this->crypto, $this->accountMapper, $this->timeFactory, $account);
	}

	public function machine(Account $account): Machine {
		return $this->machines[$account->getId()] ??= new Machine(
			$this->clientFactory->forAccount($account, 30),
			$this->store($account),
			$account->getMxid(),
			$account->getDeviceId(),
			$this->logger,
			fn (): string => 'nc-' . $this->random->generate(24, ISecureRandom::CHAR_ALPHANUMERIC),
		);
	}

	public function isEnabledFor(Homeserver $homeserver): bool {
		return $homeserver->getAllowE2ee();
	}

	/**
	 * After link / re-login: create the Olm account (if new) and publish device + one-time keys.
	 */
	public function bootstrap(Account $account): void {
		try {
			$machine = $this->machine($account);
			$machine->publishKeys(true);
			$machine->flush();
		} catch (MatrixException|CryptoException $e) {
			$this->logger->warning('Matrix E2EE bootstrap failed for ' . $account->getMxid() . ': ' . $e->getMessage());
		}
	}

	/** Unlink: drop every key we hold for the account. */
	public function wipe(Account $account): void {
		$this->store($account)->deleteAll();
		unset($this->machines[$account->getId()], $this->stores[$account->getId()]);
	}

	/**
	 * Process the crypto-relevant parts of a sync batch: to-device events,
	 * device list changes, one-time key counts. Returns the ids of Megolm
	 * sessions that became available (so undecryptable messages can be retried).
	 *
	 * @return list<array{roomId: string, sessionId: string}>
	 */
	public function processSync(Account $account, Homeserver $homeserver, SyncBatch $batch): array {
		if (!$this->isEnabledFor($homeserver)) {
			return [];
		}
		$machine = $this->machine($account);
		$newSessions = [];

		$machine->markDevicesChanged($batch->deviceListsChanged);

		foreach ($batch->toDevice as $event) {
			$type = (string)($event['type'] ?? '');
			$sender = (string)($event['sender'] ?? '');
			$content = is_array($event['content'] ?? null) ? $event['content'] : [];
			try {
				if ($type === 'm.room.encrypted') {
					$payload = $machine->decryptToDevice($sender, $content);
					if ($payload === null) {
						continue;
					}
					$this->handleDecryptedToDevice($account, $machine, $payload, $newSessions);
				} elseif ($type === 'm.room_key_request') {
					$this->handleKeyRequest($account, $machine, $sender, $content);
				} elseif (str_starts_with($type, 'm.key.verification.')) {
					$this->handleVerificationEvent($account, $machine, $sender, $type, $content);
				}
			} catch (CryptoException|MatrixException $e) {
				$this->logger->info('Matrix to-device event ' . $type . ' from ' . $sender . ' ignored: ' . $e->getMessage());
			}
		}

		try {
			if ($batch->oneTimeKeysCount !== [] || $batch->nextBatch !== '') {
				$machine->publishKeys(false, $batch->oneTimeKeysCount === [] ? null : $batch->oneTimeKeysCount);
			}
		} catch (MatrixException $e) {
			$this->logger->info('Publishing one-time keys failed: ' . $e->getMessage());
		}
		$machine->flush();
		return $newSessions;
	}

	/**
	 * @param array{type: string, content: array<string, mixed>, sender: string, senderKey: string, senderDevice: string, claimedEd25519: string} $payload
	 * @param list<array{roomId: string, sessionId: string}> $newSessions
	 */
	private function handleDecryptedToDevice(Account $account, Machine $machine, array $payload, array &$newSessions): void {
		switch ($payload['type']) {
			case 'm.room_key':
				if ($machine->receiveRoomKey($payload['content'], $payload['senderKey'], $payload['claimedEd25519'], $payload['sender'], $payload['senderDevice'])) {
					$newSessions[] = ['roomId' => (string)$payload['content']['room_id'], 'sessionId' => (string)$payload['content']['session_id']];
					$this->cancelKeyRequest($account, $machine, (string)$payload['content']['room_id'], (string)$payload['content']['session_id']);
				}
				break;
			case 'm.forwarded_room_key':
				$forwarder = $this->deviceByCurveKey($machine, $payload['sender'], $payload['senderKey']);
				$trusted = $forwarder !== null && $payload['sender'] === $account->getMxid() && $machine->isOwnCrossSignedDevice($forwarder);
				if (!$trusted && $forwarder !== null && $payload['sender'] === $account->getMxid()) {
					// Own device that verified us via SAS is also fine
					$trusted = $this->store($account)->deviceTrust($forwarder->userId, $forwarder->deviceId) === Trust::VERIFIED;
				}
				if ($machine->receiveForwardedRoomKey($payload['content'], $payload['senderKey'], $trusted)) {
					$newSessions[] = ['roomId' => (string)$payload['content']['room_id'], 'sessionId' => (string)$payload['content']['session_id']];
					$this->cancelKeyRequest($account, $machine, (string)$payload['content']['room_id'], (string)$payload['content']['session_id']);
				} elseif (!$trusted) {
					$this->logger->info('Ignoring forwarded room key from untrusted device ' . $payload['sender'] . '/' . $payload['senderDevice']);
				}
				break;
			case 'm.secret.send':
				// Secrets from a verified device after verification (key backup key etc., phase 4)
				$requestId = (string)($payload['content']['request_id'] ?? '');
				$store = $this->store($account);
				$name = $store->getSecret('secretreq:' . $requestId);
				if ($name !== null && is_string($payload['content']['secret'] ?? null)) {
					$store->setSecret('secret:' . $name, $payload['content']['secret']);
					$store->setSecret('secretreq:' . $requestId, null);
				}
				break;
			default:
				if (str_starts_with($payload['type'], 'm.key.verification.')) {
					$this->handleVerificationEvent($account, $machine, $payload['sender'], $payload['type'], $payload['content']);
				}
		}
	}

	private function deviceByCurveKey(Machine $machine, string $userId, string $curveKey): ?DeviceKeys {
		foreach ($machine->devicesFor([$userId])[$userId] ?? [] as $device) {
			if ($device->curve25519 === $curveKey) {
				return $device;
			}
		}
		return null;
	}

	/**
	 * Decrypt a room event for an account; falls back to other linked accounts'
	 * sessions when the shared-lookup policy allows.
	 *
	 * @param array<string, mixed> $content
	 * @return array{type: string, content: array<string, mixed>, index: int, senderKey: string, sessionId: string, senderVerified: bool}
	 * @throws MissingSessionException
	 */
	public function decryptRoomEvent(Account $account, string $roomId, string $sender, array $content): array {
		$machine = $this->machine($account);
		try {
			$decrypted = $machine->decryptRoomEvent($roomId, $content);
		} catch (MissingSessionException $e) {
			if (!$this->config->isE2eeSharedLookupEnabled()) {
				throw $e;
			}
			$other = $this->store($account)->loadInboundGroupSessionFromAnyAccount($roomId, $e->sessionId);
			if ($other === null || $other['accountId'] === $account->getId()) {
				throw $e;
			}
			$session = InboundSession::unpickle($other['pickle']);
			$result = \Nextcloud\Matrix\Crypto\MegolmEnvelope::decrypt($session, $content, $roomId);
			$decrypted = $result + ['senderKey' => (string)($content['sender_key'] ?? ''), 'sessionId' => $e->sessionId];
			// Adopt the session for this account so future messages decrypt directly
			$this->store($account)->saveInboundGroupSession($roomId, $e->sessionId, $decrypted['senderKey'], $session->pickle(), $session->getFirstKnownIndex(), [], 'shared');
		}
		$decrypted['senderVerified'] = $this->deviceByCurveKey($machine, $sender, $decrypted['senderKey']) !== null;
		return $decrypted;
	}

	/**
	 * Encrypt an outgoing room event.
	 *
	 * @param array<string, mixed> $content
	 * @param list<string> $memberUserIds
	 * @return array<string, mixed> m.room.encrypted content
	 */
	public function encryptRoomEvent(Account $account, string $roomId, string $type, array $content, array $memberUserIds, ?int $rotationPeriodMs, ?int $rotationMsgs): array {
		$result = $this->machine($account)->encryptRoomEvent($roomId, $type, $content, $memberUserIds, $this->timeFactory->getTime(), $rotationPeriodMs, $rotationMsgs, $this->config->isE2eeVerifiedOnly());
		if ($result['unreachable'] > 0) {
			$this->logger->info('Matrix room key for ' . $roomId . ' could not be shared with ' . $result['unreachable'] . ' device(s)');
		}
		return $result['content'];
	}

	/**
	 * Ask for a missing session (rate limited per session), from our own devices and the sender's.
	 */
	public function requestMissingKey(Account $account, string $roomId, string $sessionId, string $sender): void {
		$store = $this->store($account);
		$name = self::KEY_REQUEST_PREFIX . $sessionId;
		$state = json_decode((string)$store->getSecret($name), true) ?: ['count' => 0, 'last' => 0, 'id' => null];
		$now = $this->timeFactory->getTime();
		if ($state['count'] >= self::KEY_REQUEST_MAX || ($now - (int)$state['last']) < self::KEY_REQUEST_RETRY_SECONDS) {
			return;
		}
		$machine = $this->machine($account);
		$requestId = $state['id'] ?? ('nc-' . $this->random->generate(16, ISecureRandom::CHAR_ALPHANUMERIC));
		$targets = [];
		foreach ($machine->devicesFor(array_unique([$account->getMxid(), $sender])) as $userId => $devices) {
			foreach ($devices as $device) {
				if ($userId === $account->getMxid() && $device->deviceId === $account->getDeviceId()) {
					continue;
				}
				$targets[] = $device;
			}
		}
		try {
			$machine->requestRoomKey($roomId, $sessionId, $requestId, $targets);
			$store->setSecret($name, json_encode(['count' => $state['count'] + 1, 'last' => $now, 'id' => $requestId]));
		} catch (MatrixException $e) {
			$this->logger->info('Key request failed: ' . $e->getMessage());
		}
	}

	private function cancelKeyRequest(Account $account, Machine $machine, string $roomId, string $sessionId): void {
		$store = $this->store($account);
		$name = self::KEY_REQUEST_PREFIX . $sessionId;
		$state = json_decode((string)$store->getSecret($name), true);
		if (!is_array($state) || empty($state['id'])) {
			return;
		}
		$targets = [];
		foreach ($machine->devicesFor([$account->getMxid()])[$account->getMxid()] ?? [] as $device) {
			if ($device->deviceId !== $account->getDeviceId()) {
				$targets[] = $device;
			}
		}
		try {
			$machine->requestRoomKey($roomId, $sessionId, (string)$state['id'], $targets, true);
		} catch (MatrixException) {
		}
		$store->setSecret($name, null);
	}

	/**
	 * Another of our own devices asks for a key: forward it if that device is cross-signed or verified.
	 * @param array<string, mixed> $content
	 */
	private function handleKeyRequest(Account $account, Machine $machine, string $sender, array $content): void {
		if ($sender !== $account->getMxid() || ($content['action'] ?? '') !== 'request') {
			return;
		}
		$deviceId = (string)($content['requesting_device_id'] ?? '');
		$body = is_array($content['body'] ?? null) ? $content['body'] : [];
		$roomId = (string)($body['room_id'] ?? '');
		$sessionId = (string)($body['session_id'] ?? '');
		if ($deviceId === '' || $roomId === '' || $sessionId === '' || $deviceId === $account->getDeviceId()) {
			return;
		}
		$device = $machine->devicesFor([$sender])[$sender][$deviceId] ?? null;
		if ($device === null) {
			return;
		}
		$trust = $this->store($account)->deviceTrust($sender, $deviceId);
		if ($trust !== Trust::CROSS_SIGNED && $trust !== Trust::VERIFIED) {
			$this->logger->info('Not forwarding room key to unverified own device ' . $deviceId);
			return;
		}
		$senderKey = $this->sessionSenderKey($account, $roomId, $sessionId);
		if ($senderKey === null) {
			return;
		}
		$machine->forwardRoomKey($roomId, $sessionId, $device, $senderKey['senderKey'], $senderKey['claimedEd25519'], $senderKey['chain']);
	}

	/** @return array{senderKey: string, claimedEd25519: string, chain: list<string>}|null */
	private function sessionSenderKey(Account $account, string $roomId, string $sessionId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('sender_key', 'forwarding_chains')
			->from('talk_matrix_megolm_in')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($account->getId(), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($roomId)))
			->andWhere($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId)));
		$row = $qb->executeQuery()->fetch();
		if ($row === false) {
			return null;
		}
		$chain = json_decode((string)$row['forwarding_chains'], true);
		// The claimed Ed25519 key of the original sender: look it up among known devices
		$claimed = '';
		foreach ($this->machine($account)->devicesFor([$account->getMxid()]) as $devices) {
			foreach ($devices as $device) {
				if ($device->curve25519 === (string)$row['sender_key']) {
					$claimed = $device->ed25519;
				}
			}
		}
		return ['senderKey' => (string)$row['sender_key'], 'claimedEd25519' => $claimed, 'chain' => is_array($chain) ? $chain : []];
	}

	// ---- Verification -------------------------------------------------------------

	/**
	 * Start verifying this device from the user's other clients (they show the request).
	 * @return array{transactionId: string, state: string}
	 */
	public function startVerification(Account $account): array {
		$machine = $this->machine($account);
		$store = $this->store($account);
		foreach ($store->secretNames(self::VERIFICATION_PREFIX) as $name) {
			$store->setSecret($name, null); // one at a time
		}
		$txn = 'nc-' . $this->random->generate(20, ISecureRandom::CHAR_ALPHANUMERIC);
		$verification = SasVerification::request($txn, $account->getMxid(), $account->getDeviceId(), $machine->getSigningKey(), $this->timeFactory->getTime());
		$this->sendVerificationEvents($account, $machine, $verification, true);
		$store->setSecret(self::VERIFICATION_PREFIX . $txn, $verification->pickle());
		return ['transactionId' => $txn, 'state' => $verification->state];
	}

	/**
	 * @return array{transactionId: string, state: string, theirDeviceId: ?string, emoji: list<array{emoji: string, name: string}>, decimal: ?array{0: int, 1: int, 2: int}, reason: ?string}|null
	 */
	public function verificationStatus(Account $account): ?array {
		$store = $this->store($account);
		$names = $store->secretNames(self::VERIFICATION_PREFIX);
		if ($names === []) {
			return null;
		}
		$verification = SasVerification::unpickle((string)$store->getSecret($names[0]));
		$showSas = $verification->state === SasVerification::STATE_KEYS_EXCHANGED || $verification->state === SasVerification::STATE_MAC_SENT;
		return [
			'transactionId' => $verification->transactionId,
			'state' => $verification->state,
			'theirDeviceId' => $verification->theirDeviceId,
			'emoji' => $showSas ? $verification->emoji() : [],
			'decimal' => $showSas ? $verification->decimal() : null,
			'reason' => $verification->cancelReason,
		];
	}

	public function confirmVerification(Account $account, bool $matches): ?array {
		$store = $this->store($account);
		$names = $store->secretNames(self::VERIFICATION_PREFIX);
		if ($names === []) {
			return null;
		}
		$verification = SasVerification::unpickle((string)$store->getSecret($names[0]));
		$machine = $this->machine($account);
		if ($matches) {
			$verification->confirm();
		} else {
			$verification->reject();
		}
		$this->afterVerificationStep($account, $machine, $verification);
		$store->setSecret($names[0], $verification->isFinished() && $verification->state === SasVerification::STATE_CANCELLED ? null : $verification->pickle());
		if ($verification->state === SasVerification::STATE_DONE) {
			$store->setSecret($names[0], null);
		}
		return $this->verificationStatus($account) ?? ['transactionId' => $verification->transactionId, 'state' => $verification->state, 'theirDeviceId' => $verification->theirDeviceId, 'emoji' => [], 'decimal' => null, 'reason' => $verification->cancelReason];
	}

	public function cancelVerification(Account $account): void {
		$store = $this->store($account);
		foreach ($store->secretNames(self::VERIFICATION_PREFIX) as $name) {
			$verification = SasVerification::unpickle((string)$store->getSecret($name));
			$verification->cancel('m.user', 'Cancelled by user');
			$this->sendVerificationEvents($account, $this->machine($account), $verification, false);
			$store->setSecret($name, null);
		}
	}

	/** @param array<string, mixed> $content */
	private function handleVerificationEvent(Account $account, Machine $machine, string $sender, string $type, array $content): void {
		if ($sender !== $account->getMxid()) {
			return; // only self-verification
		}
		$txn = (string)($content['transaction_id'] ?? '');
		$store = $this->store($account);
		$pickle = $store->getSecret(self::VERIFICATION_PREFIX . $txn);
		if ($pickle === null) {
			return; // not ours (or an incoming request we do not support yet)
		}
		$verification = SasVerification::unpickle($pickle);
		$verification->handle($type, $content, function (string $deviceId) use ($machine, $account): ?string {
			return $machine->devicesFor([$account->getMxid()])[$account->getMxid()][$deviceId]?->ed25519;
		});
		$this->afterVerificationStep($account, $machine, $verification);
		$store->setSecret(self::VERIFICATION_PREFIX . $txn, $verification->pickle());
	}

	private function afterVerificationStep(Account $account, Machine $machine, SasVerification $verification): void {
		$this->sendVerificationEvents($account, $machine, $verification, false);
		if ($verification->state === SasVerification::STATE_DONE && $verification->theirDeviceId !== null) {
			$this->store($account)->setDeviceTrust($account->getMxid(), $verification->theirDeviceId, Trust::VERIFIED);
			$this->store($account)->setSecret('verified_at', (string)$this->timeFactory->getTime());
			if ($verification->theirMasterKey !== null) {
				$this->store($account)->setSecret('trusted_master_key', $verification->theirMasterKey);
			}
			// Ask the verified device for the backup key so history can be restored (phase 4 consumer)
			$this->requestSecret($account, $machine, $verification->theirDeviceId, 'm.megolm_backup.v1');
			// …and re-request every key we are still missing: verified devices now answer us
			$this->rerequestMissingKeys($account);
		}
	}

	/**
	 * Send key requests for all sessions with undecryptable events (request
	 * counters are reset so previously exhausted sessions are asked again).
	 */
	public function rerequestMissingKeys(Account $account, int $limit = 200): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct(['matrix_room_id', 'session_id', 'sender'])
			->from('talk_matrix_events')
			->where($qb->expr()->eq('decrypt_state', $qb->createNamedParameter(\OCA\Talk\Matrix\Model\EventMap::DECRYPT_MISSING_SESSION, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('session_id'))
			->setMaxResults($limit);
		$store = $this->store($account);
		$count = 0;
		foreach ($qb->executeQuery()->fetchAll() as $row) {
			// Only rooms this account is in
			try {
				\OCP\Server::get(\OCA\Talk\Matrix\Model\MatrixMemberMapper::class)->get((string)$row['matrix_room_id'], $account->getMxid());
			} catch (\OCP\AppFramework\Db\DoesNotExistException) {
				continue;
			}
			$store->setSecret(self::KEY_REQUEST_PREFIX . $row['session_id'], null);
			$this->requestMissingKey($account, (string)$row['matrix_room_id'], (string)$row['session_id'], (string)$row['sender']);
			$count++;
		}
		return $count;
	}

	/**
	 * Verification events go to the other device unencrypted (request: to all our devices).
	 */
	private function sendVerificationEvents(Account $account, Machine $machine, SasVerification $verification, bool $broadcast): void {
		$client = $this->clientFactory->forAccount($account, 20);
		foreach ($verification->takeOutgoing() as $event) {
			$target = $broadcast || $verification->theirDeviceId === null ? '*' : $verification->theirDeviceId;
			try {
				$client->sendToDevice($event['type'], [$account->getMxid() => [$target => $event['content']]], $machine->txnId());
			} catch (MatrixException $e) {
				$this->logger->warning('Sending verification event ' . $event['type'] . ' failed: ' . $e->getMessage());
			}
		}
	}

	private function requestSecret(Account $account, Machine $machine, string $deviceId, string $name): void {
		$requestId = 'nc-' . $this->random->generate(16, ISecureRandom::CHAR_ALPHANUMERIC);
		$this->store($account)->setSecret('secretreq:' . $requestId, $name);
		try {
			$this->clientFactory->forAccount($account, 20)->sendToDevice('m.secret.request', [$account->getMxid() => [$deviceId => [
				'name' => $name,
				'action' => 'request',
				'requesting_device_id' => $account->getDeviceId(),
				'request_id' => $requestId,
			]]], $machine->txnId());
		} catch (MatrixException $e) {
			$this->logger->info('Secret request failed: ' . $e->getMessage());
		}
	}

	// ---- Key backup ---------------------------------------------------------------

	/**
	 * Restore room keys from the server-side key backup. The backup key comes
	 * either from a verified device (m.secret.send after verification) or from
	 * the recovery key the user types in (SSSS).
	 *
	 * @return array{imported: int, sessions: int, decrypted: int}
	 * @throws \InvalidArgumentException 'no-backup' | 'no-key' | 'recovery-key' | 'key-mismatch'
	 * @throws MatrixException
	 */
	public function restoreBackup(Account $account, ?string $recoveryKey = null): array {
		$client = $this->clientFactory->forAccount($account, 60);
		$store = $this->store($account);
		$machine = $this->machine($account);

		$version = $client->getRoomKeysVersion();
		if ($version === null || ($version['algorithm'] ?? '') !== \Nextcloud\Matrix\Crypto\Backup::ALGORITHM) {
			throw new \InvalidArgumentException('no-backup');
		}
		$backupPublic = (string)($version['auth_data']['public_key'] ?? '');

		$privateKey = null;
		if ($recoveryKey !== null && trim($recoveryKey) !== '') {
			try {
				$ssss = \Nextcloud\Matrix\Crypto\Backup::decodeRecoveryKey($recoveryKey);
			} catch (CryptoException) {
				throw new \InvalidArgumentException('recovery-key');
			}
			$privateKey = $this->backupKeyFromSecretStorage($client, $account, $ssss);
			$store->setSecret('secret:' . \Nextcloud\Matrix\Crypto\Backup::SECRET_NAME, \Nextcloud\Matrix\Crypto\Base64::encode($privateKey));
		} else {
			$stored = $store->getSecret('secret:' . \Nextcloud\Matrix\Crypto\Backup::SECRET_NAME);
			if ($stored === null) {
				throw new \InvalidArgumentException('no-key');
			}
			$privateKey = \Nextcloud\Matrix\Crypto\Base64::decode($stored);
		}
		if ($backupPublic !== '' && !hash_equals(\Nextcloud\Matrix\Crypto\Base64::decode($backupPublic), sodium_crypto_scalarmult_base($privateKey))) {
			throw new \InvalidArgumentException('key-mismatch');
		}

		$keys = $client->getRoomKeys((string)$version['version']);
		$imported = 0;
		$sessions = 0;
		$touched = [];
		foreach ((is_array($keys['rooms'] ?? null) ? $keys['rooms'] : []) as $roomId => $roomData) {
			foreach ((is_array($roomData['sessions'] ?? null) ? $roomData['sessions'] : []) as $sessionId => $sessionInfo) {
				$sessions++;
				if (!is_array($sessionInfo['session_data'] ?? null)) {
					continue;
				}
				try {
					$data = \Nextcloud\Matrix\Crypto\Backup::decryptSessionData($privateKey, $sessionInfo['session_data']);
					if ($machine->importSession((string)$roomId, (string)$data['session_key'], (string)($data['sender_key'] ?? ''), 'backup')) {
						$imported++;
						$touched[(string)$roomId][] = (string)$sessionId;
					}
				} catch (CryptoException $e) {
					$this->logger->info('Backup session ' . $sessionId . ' skipped: ' . $e->getMessage());
				}
			}
		}
		$machine->flush();

		// Replace placeholders for everything the new keys unlock
		$decrypted = 0;
		$eventMapper = \OCP\Server::get(\OCA\Talk\Matrix\Mapping\EventMapper::class);
		$applier = \OCP\Server::get(\OCA\Talk\Matrix\Sync\RoomStateApplier::class);
		foreach ($touched as $roomId => $sessionIds) {
			$matrixRoom = $applier->findMatrixRoom($roomId);
			$room = $matrixRoom !== null ? $applier->findTalkRoom($matrixRoom) : null;
			if ($room === null) {
				continue;
			}
			foreach (array_unique($sessionIds) as $sessionId) {
				$decrypted += $eventMapper->retryUndecryptable($room, $matrixRoom, $account, $sessionId);
			}
		}
		$store->setSecret('backup_restored_at', (string)$this->timeFactory->getTime());
		return ['imported' => $imported, 'sessions' => $sessions, 'decrypted' => $decrypted];
	}

	/**
	 * Recovery key → SSSS key → decrypt the `m.megolm_backup.v1` secret from account data.
	 * @throws \InvalidArgumentException 'key-mismatch' | 'no-key'
	 */
	private function backupKeyFromSecretStorage(\Nextcloud\Matrix\Client $client, Account $account, string $ssssKey): string {
		try {
			$defaultKey = $client->getAccountData($account->getMxid(), 'm.secret_storage.default_key');
			$keyId = (string)($defaultKey['key'] ?? '');
		} catch (\Nextcloud\Matrix\Exception\NotFoundException) {
			$keyId = '';
		}
		if ($keyId !== '') {
			try {
				$keyInfo = $client->getAccountData($account->getMxid(), 'm.secret_storage.key.' . $keyId);
				if (!\Nextcloud\Matrix\Crypto\Backup::checkKeyAgainstDescription($ssssKey, $keyInfo)) {
					throw new \InvalidArgumentException('key-mismatch');
				}
			} catch (\Nextcloud\Matrix\Exception\NotFoundException) {
			}
		}
		try {
			$secret = $client->getAccountData($account->getMxid(), \Nextcloud\Matrix\Crypto\Backup::SECRET_NAME);
		} catch (\Nextcloud\Matrix\Exception\NotFoundException) {
			throw new \InvalidArgumentException('no-key');
		}
		$encrypted = is_array($secret['encrypted'] ?? null) ? $secret['encrypted'] : [];
		$entry = $keyId !== '' && isset($encrypted[$keyId]) ? $encrypted[$keyId] : (reset($encrypted) ?: null);
		if (!is_array($entry)) {
			throw new \InvalidArgumentException('no-key');
		}
		try {
			$decoded = \Nextcloud\Matrix\Crypto\Backup::decryptSecret($ssssKey, \Nextcloud\Matrix\Crypto\Backup::SECRET_NAME, $entry);
		} catch (CryptoException) {
			throw new \InvalidArgumentException('key-mismatch');
		}
		$private = \Nextcloud\Matrix\Crypto\Base64::decode(trim($decoded));
		if (strlen($private) !== 32) {
			throw new \InvalidArgumentException('no-key');
		}
		return $private;
	}

	public function hasBackupKey(Account $account): bool {
		return $this->store($account)->getSecret('secret:' . \Nextcloud\Matrix\Crypto\Backup::SECRET_NAME) !== null;
	}

	/** Summary for the personal settings UI. @return array{deviceId: string, ed25519: ?string, curve25519: ?string, verified: bool, crossSigned: bool, hasBackupKey: bool, backupRestoredAt: ?int} */
	public function deviceStatus(Account $account): array {
		$store = $this->store($account);
		$machine = $this->machine($account);
		$ownDevices = $machine->devicesFor([$account->getMxid()])[$account->getMxid()] ?? [];
		$own = $ownDevices[$account->getDeviceId()] ?? null;
		$cross = $store->loadCrossSigning($account->getMxid());
		return [
			'deviceId' => $account->getDeviceId(),
			'ed25519' => $machine->getSigningKey(),
			'curve25519' => $machine->getIdentityKey(),
			'verified' => $store->getSecret('verified_at') !== null,
			'crossSigned' => $own !== null && $cross !== null && $cross->signsDevice($own),
			'hasBackupKey' => $this->hasBackupKey($account),
			'backupRestoredAt' => $store->getSecret('backup_restored_at') !== null ? (int)$store->getSecret('backup_restored_at') : null,
		];
	}
}
