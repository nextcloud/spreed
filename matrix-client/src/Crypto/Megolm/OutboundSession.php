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

/** A Megolm session we send with. */
final class OutboundSession {
	public const KEYS_INFO = 'MEGOLM_KEYS';
	public const SESSION_KEY_VERSION = 0x02;

	private function __construct(
		private Ratchet $ratchet,
		/** @var array{public: string, secret: string} */
		private readonly array $signing,
		private readonly int $createdAt,
	) {
	}

	public static function create(int $now): self {
		return new self(Ratchet::random(), Keys::ed25519KeyPair(), $now);
	}

	/** Session id = base64 of the Ed25519 public key. */
	public function getId(): string {
		return Base64::encode($this->signing['public']);
	}

	public function getMessageIndex(): int {
		return $this->ratchet->getCounter();
	}

	public function getCreatedAt(): int {
		return $this->createdAt;
	}

	/**
	 * The key to share with other devices (m.room_key `session_key`):
	 * version || index(4 BE) || ratchet(128) || ed25519 pub(32) || signature(64).
	 */
	public function sessionKey(): string {
		$data = chr(self::SESSION_KEY_VERSION) . pack('N', $this->ratchet->getCounter()) . $this->ratchet->toBytes() . $this->signing['public'];
		return Base64::encode($data . Keys::sign($this->signing['secret'], $data));
	}

	/** @return string unpadded base64 Megolm message */
	public function encrypt(string $plaintext): string {
		[$aesKey, $macKey, $iv] = Kdf::messageKeys($this->ratchet->toBytes(), self::KEYS_INFO);
		$body = MegolmMessage::encodeBody($this->ratchet->getCounter(), Kdf::aesCbcEncrypt($aesKey, $iv, $plaintext));
		$signed = $body . substr(Kdf::hmac($macKey, $body), 0, MegolmMessage::MAC_LENGTH);
		$this->ratchet->advance();
		return Base64::encode($signed . Keys::sign($this->signing['secret'], $signed));
	}

	public function pickle(): string {
		return json_encode([
			'v' => 1,
			'r' => Base64::encode($this->ratchet->toBytes()),
			'i' => $this->ratchet->getCounter(),
			'p' => Base64::encode($this->signing['public']),
			's' => Base64::encode($this->signing['secret']),
			'c' => $this->createdAt,
		], JSON_THROW_ON_ERROR);
	}

	public static function unpickle(string $pickle): self {
		$d = json_decode($pickle, true);
		if (!is_array($d) || ($d['v'] ?? 0) !== 1) {
			throw new CryptoException('Invalid outbound session pickle');
		}
		return new self(Ratchet::fromBytes(Base64::decode($d['r']), (int)$d['i']), ['public' => Base64::decode($d['p']), 'secret' => Base64::decode($d['s'])], (int)$d['c']);
	}
}
