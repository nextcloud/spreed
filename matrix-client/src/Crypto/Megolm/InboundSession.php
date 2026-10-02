<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto\Megolm;

use Nextcloud\Matrix\Crypto\Base64;
use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\Kdf;
use Nextcloud\Matrix\Crypto\Keys;

/**
 * A Megolm session we can decrypt with. Holds the ratchet at the earliest
 * known index (so older messages stay decryptable) and advances a copy for
 * each message.
 */
final class InboundSession {
	public const EXPORT_VERSION = 0x01;

	private function __construct(
		private readonly Ratchet $initialRatchet,
		private readonly string $signingKey,
		private readonly bool $signatureVerified,
	) {
	}

	/** From an m.room_key `session_key` (signed, version 2). */
	public static function fromSessionKey(string $sessionKey): self {
		$data = Base64::decode($sessionKey);
		if (strlen($data) !== 1 + 4 + Ratchet::LENGTH + 32 + 64 || ord($data[0]) !== OutboundSession::SESSION_KEY_VERSION) {
			throw new CryptoException('Invalid Megolm session key');
		}
		$index = unpack('N', substr($data, 1, 4))[1];
		$ratchet = substr($data, 5, Ratchet::LENGTH);
		$public = substr($data, 5 + Ratchet::LENGTH, 32);
		$signature = substr($data, 5 + Ratchet::LENGTH + 32);
		if (!Keys::verify($public, substr($data, 0, 5 + Ratchet::LENGTH + 32), $signature)) {
			throw new CryptoException('Megolm session key signature invalid');
		}
		return new self(Ratchet::fromBytes($ratchet, $index), $public, true);
	}

	/** From an exported/forwarded key (version 1, unsigned – trust comes from the forwarding chain). */
	public static function fromExportedKey(string $exported): self {
		$data = Base64::decode($exported);
		if (strlen($data) !== 1 + 4 + Ratchet::LENGTH + 32 || ord($data[0]) !== self::EXPORT_VERSION) {
			throw new CryptoException('Invalid exported Megolm session');
		}
		$index = unpack('N', substr($data, 1, 4))[1];
		return new self(Ratchet::fromBytes(substr($data, 5, Ratchet::LENGTH), $index), substr($data, 5 + Ratchet::LENGTH, 32), false);
	}

	public function getId(): string {
		return Base64::encode($this->signingKey);
	}

	public function getFirstKnownIndex(): int {
		return $this->initialRatchet->getCounter();
	}

	public function isSignatureVerified(): bool {
		return $this->signatureVerified;
	}

	/**
	 * @return array{plaintext: string, index: int}
	 */
	public function decrypt(string $base64Message): array {
		$message = MegolmMessage::decode(Base64::decode($base64Message));
		if (!Keys::verify($this->signingKey, $message['signed'], $message['signature'])) {
			throw new CryptoException('Megolm signature invalid');
		}
		if ($message['index'] < $this->initialRatchet->getCounter()) {
			throw new CryptoException('Message index ' . $message['index'] . ' is before the first known index ' . $this->initialRatchet->getCounter());
		}
		$ratchet = Ratchet::fromBytes($this->initialRatchet->toBytes(), $this->initialRatchet->getCounter());
		$ratchet->advanceTo($message['index']);
		[$aesKey, $macKey, $iv] = Kdf::messageKeys($ratchet->toBytes(), OutboundSession::KEYS_INFO);
		if (!hash_equals(substr(Kdf::hmac($macKey, $message['body']), 0, MegolmMessage::MAC_LENGTH), $message['mac'])) {
			throw new CryptoException('Megolm MAC mismatch');
		}
		return ['plaintext' => Kdf::aesCbcDecrypt($aesKey, $iv, $message['ciphertext']), 'index' => $message['index']];
	}

	/** Export at the first known index (for forwarding / backup, version 1). */
	public function export(): string {
		return Base64::encode(chr(self::EXPORT_VERSION) . pack('N', $this->initialRatchet->getCounter()) . $this->initialRatchet->toBytes() . $this->signingKey);
	}

	public function pickle(): string {
		return json_encode([
			'v' => 1,
			'r' => Base64::encode($this->initialRatchet->toBytes()),
			'i' => $this->initialRatchet->getCounter(),
			'p' => Base64::encode($this->signingKey),
			'sv' => $this->signatureVerified,
		], JSON_THROW_ON_ERROR);
	}

	public static function unpickle(string $pickle): self {
		$d = json_decode($pickle, true);
		if (!is_array($d) || ($d['v'] ?? 0) !== 1) {
			throw new CryptoException('Invalid inbound session pickle');
		}
		return new self(Ratchet::fromBytes(Base64::decode($d['r']), (int)$d['i']), Base64::decode($d['p']), (bool)($d['sv'] ?? false));
	}
}
