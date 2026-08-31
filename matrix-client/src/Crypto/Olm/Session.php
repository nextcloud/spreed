<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto\Olm;

use Nextcloud\Matrix\Crypto\Base64;
use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\Kdf;
use Nextcloud\Matrix\Crypto\Keys;

/**
 * An Olm double-ratchet session between two devices (Olm spec, version 3).
 */
final class Session {
	private const ROOT_INFO = 'OLM_ROOT';
	private const RATCHET_INFO = 'OLM_RATCHET';
	private const KEYS_INFO = 'OLM_KEYS';
	private const MAX_SKIPPED = 40;
	private const MAX_RECEIVER_CHAINS = 5;

	private string $rootKey;
	/** @var array{key: array{public: string, secret: string}, chainKey: string, index: int}|null */
	private ?array $senderChain = null;
	/** @var list<array{ratchetKey: string, chainKey: string, index: int}> */
	private array $receiverChains = [];
	/** @var list<array{ratchetKey: string, index: int, messageKey: string}> */
	private array $skippedKeys = [];
	/** Pre-key data we keep sending until the other side has answered (our first messages). */
	private ?string $pendingOneTimeKey = null;
	private ?string $pendingBaseKey = null;
	private ?string $pendingIdentityKey = null;
	/** Stable session id: base64(sha256(identity || base || one-time key)) like libolm. */
	private string $sessionId;

	private function __construct() {
	}

	/**
	 * Alice: create an outbound session to Bob's device.
	 */
	public static function createOutbound(Account $ourAccount, string $theirIdentityKey, string $theirOneTimeKey): self {
		$base = Keys::curve25519KeyPair();
		$secret = Keys::ecdh($ourAccount->getIdentitySecret(), $theirOneTimeKey)
			. Keys::ecdh($base['secret'], $theirIdentityKey)
			. Keys::ecdh($base['secret'], $theirOneTimeKey);
		$session = new self();
		$session->initialiseAsAlice($secret);
		$session->pendingOneTimeKey = $theirOneTimeKey;
		$session->pendingBaseKey = $base['public'];
		$session->pendingIdentityKey = $ourAccount->getIdentityKey();
		$session->sessionId = self::computeSessionId($ourAccount->getIdentityKey(), $base['public'], $theirOneTimeKey);
		return $session;
	}

	/**
	 * Bob: create an inbound session from Alice's pre-key message. The account
	 * must still hold the one-time key referenced by the message.
	 *
	 * @param string $preKeyMessage raw pre-key message bytes
	 * @param string|null $expectedIdentityKey when known (sender_key of the event), must match the message
	 */
	public static function createInbound(Account $ourAccount, string $preKeyMessage, ?string $expectedIdentityKey = null): self {
		$fields = Message::decodePreKey($preKeyMessage);
		if ($expectedIdentityKey !== null && !hash_equals($expectedIdentityKey, $fields['identityKey'])) {
			throw new CryptoException('Pre-key message identity key does not match sender');
		}
		$oneTime = $ourAccount->findKeyByPublic($fields['oneTimeKey']);
		if ($oneTime === null) {
			throw new CryptoException('Unknown one-time key in pre-key message');
		}
		$secret = Keys::ecdh($oneTime['secret'], $fields['identityKey'])
			. Keys::ecdh($ourAccount->getIdentitySecret(), $fields['baseKey'])
			. Keys::ecdh($oneTime['secret'], $fields['baseKey']);
		$session = new self();
		$inner = Message::decode($fields['message']);
		$session->initialiseAsBob($secret, $inner['ratchetKey']);
		$session->sessionId = self::computeSessionId($fields['identityKey'], $fields['baseKey'], $fields['oneTimeKey']);
		if ($oneTime['oneTimeId'] !== null) {
			$ourAccount->removeOneTimeKey($oneTime['oneTimeId']);
		}
		return $session;
	}

	/** Whether this pre-key message belongs to this session (same one-time/base/identity keys). */
	public function matchesPreKeyMessage(string $preKeyMessage): bool {
		try {
			$fields = Message::decodePreKey($preKeyMessage);
		} catch (CryptoException) {
			return false;
		}
		return self::computeSessionId($fields['identityKey'], $fields['baseKey'], $fields['oneTimeKey']) === $this->sessionId;
	}

	public function getId(): string {
		return $this->sessionId;
	}

	/** True while we still have to send pre-key messages (other side never replied). */
	public function hasReceivedMessage(): bool {
		return $this->pendingOneTimeKey === null;
	}

	/**
	 * @return array{type: int, body: string} message type (0 pre-key / 1 normal) and unpadded base64 body
	 */
	public function encrypt(string $plaintext): array {
		if ($this->senderChain === null) {
			// Bob's first message (or after receiving a new ratchet key): advance the root ratchet
			$this->advanceSenderChain();
		}
		$chain = &$this->senderChain;
		$messageKey = Kdf::hmac($chain['chainKey'], "\x01");
		[$aesKey, $macKey, $iv] = Kdf::messageKeys($messageKey, self::KEYS_INFO);
		$ciphertext = Kdf::aesCbcEncrypt($aesKey, $iv, $plaintext);
		$body = Message::encodeBody($chain['key']['public'], $chain['index'], $ciphertext);
		$message = $body . substr(Kdf::hmac($macKey, $body), 0, Message::MAC_LENGTH);
		$chain['chainKey'] = Kdf::hmac($chain['chainKey'], "\x02");
		$chain['index']++;

		if ($this->pendingOneTimeKey !== null) {
			return ['type' => Message::TYPE_PREKEY, 'body' => Base64::encode(Message::encodePreKey($this->pendingOneTimeKey, (string)$this->pendingBaseKey, (string)$this->pendingIdentityKey, $message))];
		}
		return ['type' => Message::TYPE_NORMAL, 'body' => Base64::encode($message)];
	}

	public function decrypt(int $type, string $base64Body): string {
		$bytes = Base64::decode($base64Body);
		if ($type === Message::TYPE_PREKEY) {
			$bytes = Message::decodePreKey($bytes)['message'];
		}
		$message = Message::decode($bytes);

		// Try skipped keys first
		foreach ($this->skippedKeys as $i => $skipped) {
			if ($skipped['index'] === $message['chainIndex'] && hash_equals($skipped['ratchetKey'], $message['ratchetKey'])) {
				$plaintext = $this->decryptWithMessageKey($skipped['messageKey'], $message);
				unset($this->skippedKeys[$i]);
				$this->skippedKeys = array_values($this->skippedKeys);
				$this->pendingOneTimeKey = $this->pendingBaseKey = $this->pendingIdentityKey = null;
				return $plaintext;
			}
		}

		$chainIndex = null;
		foreach ($this->receiverChains as $i => $chain) {
			if (hash_equals($chain['ratchetKey'], $message['ratchetKey'])) {
				$chainIndex = $i;
				break;
			}
		}
		if ($chainIndex === null) {
			// New ratchet key from the other side: derive a new receiving chain and drop our sender chain
			if ($this->senderChain === null) {
				throw new CryptoException('Cannot advance ratchet without a sender chain');
			}
			$shared = Keys::ecdh($this->senderChain['key']['secret'], $message['ratchetKey']);
			$derived = Kdf::hkdf($shared, 64, self::RATCHET_INFO, $this->rootKey);
			$this->rootKey = substr($derived, 0, 32);
			array_unshift($this->receiverChains, ['ratchetKey' => $message['ratchetKey'], 'chainKey' => substr($derived, 32), 'index' => 0]);
			$this->receiverChains = array_slice($this->receiverChains, 0, self::MAX_RECEIVER_CHAINS);
			$this->senderChain = null;
			$chainIndex = 0;
		}

		$chain = $this->receiverChains[$chainIndex];
		if ($message['chainIndex'] < $chain['index']) {
			throw new CryptoException('Message key already used (index ' . $message['chainIndex'] . ' < ' . $chain['index'] . ')');
		}
		if ($message['chainIndex'] - $chain['index'] > self::MAX_SKIPPED) {
			throw new CryptoException('Too many skipped messages');
		}
		// Skip forward, remembering the skipped keys
		while ($chain['index'] < $message['chainIndex']) {
			$this->skippedKeys[] = ['ratchetKey' => $chain['ratchetKey'], 'index' => $chain['index'], 'messageKey' => Kdf::hmac($chain['chainKey'], "\x01")];
			$chain['chainKey'] = Kdf::hmac($chain['chainKey'], "\x02");
			$chain['index']++;
		}
		if (count($this->skippedKeys) > self::MAX_SKIPPED) {
			$this->skippedKeys = array_slice($this->skippedKeys, -self::MAX_SKIPPED);
		}
		$messageKey = Kdf::hmac($chain['chainKey'], "\x01");
		$plaintext = $this->decryptWithMessageKey($messageKey, $message);
		// Only commit the ratchet state after successful authentication
		$chain['chainKey'] = Kdf::hmac($chain['chainKey'], "\x02");
		$chain['index']++;
		$this->receiverChains[$chainIndex] = $chain;
		$this->pendingOneTimeKey = $this->pendingBaseKey = $this->pendingIdentityKey = null;
		return $plaintext;
	}

	/** @param array{body: string, mac: string, ciphertext: string} $message */
	private function decryptWithMessageKey(string $messageKey, array $message): string {
		[$aesKey, $macKey, $iv] = Kdf::messageKeys($messageKey, self::KEYS_INFO);
		$expected = substr(Kdf::hmac($macKey, $message['body']), 0, Message::MAC_LENGTH);
		if (!hash_equals($expected, $message['mac'])) {
			throw new CryptoException('Olm MAC mismatch');
		}
		return Kdf::aesCbcDecrypt($aesKey, $iv, $message['ciphertext']);
	}

	private function initialiseAsAlice(string $secret): void {
		$derived = Kdf::hkdf($secret, 64, self::ROOT_INFO);
		$this->rootKey = substr($derived, 0, 32);
		$this->senderChain = ['key' => Keys::curve25519KeyPair(), 'chainKey' => substr($derived, 32), 'index' => 0];
	}

	private function initialiseAsBob(string $secret, string $theirRatchetKey): void {
		$derived = Kdf::hkdf($secret, 64, self::ROOT_INFO);
		$this->rootKey = substr($derived, 0, 32);
		$this->receiverChains = [['ratchetKey' => $theirRatchetKey, 'chainKey' => substr($derived, 32), 'index' => 0]];
	}

	private function advanceSenderChain(): void {
		if ($this->receiverChains === []) {
			throw new CryptoException('No receiver chain to ratchet from');
		}
		$key = Keys::curve25519KeyPair();
		$shared = Keys::ecdh($key['secret'], $this->receiverChains[0]['ratchetKey']);
		$derived = Kdf::hkdf($shared, 64, self::RATCHET_INFO, $this->rootKey);
		$this->rootKey = substr($derived, 0, 32);
		$this->senderChain = ['key' => $key, 'chainKey' => substr($derived, 32), 'index' => 0];
	}

	private static function computeSessionId(string $identityKey, string $baseKey, string $oneTimeKey): string {
		return Base64::encode(hash('sha256', $identityKey . $baseKey . $oneTimeKey, true));
	}

	public function pickle(): string {
		$b = Base64::encode(...);
		return json_encode([
			'v' => 1,
			'id' => $this->sessionId,
			'root' => $b($this->rootKey),
			'sender' => $this->senderChain === null ? null : ['p' => $b($this->senderChain['key']['public']), 's' => $b($this->senderChain['key']['secret']), 'c' => $b($this->senderChain['chainKey']), 'i' => $this->senderChain['index']],
			'receivers' => array_map(static fn (array $c) => ['r' => $b($c['ratchetKey']), 'c' => $b($c['chainKey']), 'i' => $c['index']], $this->receiverChains),
			'skipped' => array_map(static fn (array $s) => ['r' => $b($s['ratchetKey']), 'i' => $s['index'], 'k' => $b($s['messageKey'])], $this->skippedKeys),
			'pending' => $this->pendingOneTimeKey === null ? null : ['o' => $b($this->pendingOneTimeKey), 'b' => $b((string)$this->pendingBaseKey), 'i' => $b((string)$this->pendingIdentityKey)],
		], JSON_THROW_ON_ERROR);
	}

	public static function unpickle(string $pickle): self {
		$d = json_decode($pickle, true);
		if (!is_array($d) || ($d['v'] ?? 0) !== 1) {
			throw new CryptoException('Invalid session pickle');
		}
		$u = Base64::decode(...);
		$s = new self();
		$s->sessionId = (string)$d['id'];
		$s->rootKey = $u($d['root']);
		$s->senderChain = $d['sender'] === null ? null : ['key' => ['public' => $u($d['sender']['p']), 'secret' => $u($d['sender']['s'])], 'chainKey' => $u($d['sender']['c']), 'index' => (int)$d['sender']['i']];
		$s->receiverChains = array_map(static fn (array $c) => ['ratchetKey' => $u($c['r']), 'chainKey' => $u($c['c']), 'index' => (int)$c['i']], $d['receivers'] ?? []);
		$s->skippedKeys = array_map(static fn (array $k) => ['ratchetKey' => $u($k['r']), 'index' => (int)$k['i'], 'messageKey' => $u($k['k'])], $d['skipped'] ?? []);
		if (isset($d['pending'])) {
			$s->pendingOneTimeKey = $u($d['pending']['o']);
			$s->pendingBaseKey = $u($d['pending']['b']);
			$s->pendingIdentityKey = $u($d['pending']['i']);
		}
		return $s;
	}
}
