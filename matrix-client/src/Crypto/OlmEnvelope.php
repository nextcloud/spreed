<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

use Nextcloud\Matrix\Crypto\Olm\Account;
use Nextcloud\Matrix\Crypto\Olm\Session;

/**
 * `m.room.encrypted` with algorithm `m.olm.v1.curve25519-aes-sha2` – the
 * to-device envelope used to transport room keys and verification events.
 */
final class OlmEnvelope {
	public const ALGORITHM = 'm.olm.v1.curve25519-aes-sha2';

	/**
	 * Build the plaintext payload (spec §11.12.2.1) and encrypt it for one device.
	 *
	 * @param array<string, mixed> $content event content
	 * @return array<string, mixed> `m.room.encrypted` content for this recipient
	 */
	public static function encrypt(Account $ourAccount, string $ourUserId, string $ourDeviceId, Session $session, DeviceKeys $recipient, string $type, array $content): array {
		$payload = [
			'type' => $type,
			'content' => $content,
			'sender' => $ourUserId,
			'sender_device' => $ourDeviceId,
			'keys' => ['ed25519' => $ourAccount->getSigningKeyBase64()],
			'recipient' => $recipient->userId,
			'recipient_keys' => ['ed25519' => $recipient->ed25519],
		];
		$encrypted = $session->encrypt(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
		return [
			'algorithm' => self::ALGORITHM,
			'sender_key' => $ourAccount->getIdentityKeyBase64(),
			'ciphertext' => [
				$recipient->curve25519 => ['type' => $encrypted['type'], 'body' => $encrypted['body']],
			],
		];
	}

	/**
	 * Pick our part of the ciphertext map.
	 * @param array<string, mixed> $content
	 * @return array{type: int, body: string}|null
	 */
	public static function ourCiphertext(array $content, Account $ourAccount): ?array {
		$part = $content['ciphertext'][$ourAccount->getIdentityKeyBase64()] ?? null;
		if (!is_array($part) || !isset($part['type'], $part['body'])) {
			return null;
		}
		return ['type' => (int)$part['type'], 'body' => (string)$part['body']];
	}

	/**
	 * Validate a decrypted Olm payload against the envelope and our identity
	 * (spec: recipient, recipient_keys, sender must match; keys.ed25519 is the
	 * claimed sender signing key, to be checked against the sender's device).
	 *
	 * @return array{type: string, content: array<string, mixed>, sender: string, senderDevice: string, claimedEd25519: string}
	 */
	public static function validatePayload(string $plaintext, string $eventSender, Account $ourAccount, string $ourUserId): array {
		$payload = json_decode($plaintext, true);
		if (!is_array($payload)) {
			throw new CryptoException('Olm payload is not JSON');
		}
		if (($payload['recipient'] ?? null) !== $ourUserId) {
			throw new CryptoException('Olm payload recipient mismatch');
		}
		if (($payload['recipient_keys']['ed25519'] ?? null) !== $ourAccount->getSigningKeyBase64()) {
			throw new CryptoException('Olm payload recipient key mismatch');
		}
		if (($payload['sender'] ?? null) !== $eventSender) {
			throw new CryptoException('Olm payload sender mismatch');
		}
		$claimed = $payload['keys']['ed25519'] ?? null;
		if (!is_string($claimed) || !is_string($payload['type'] ?? null) || !is_array($payload['content'] ?? null)) {
			throw new CryptoException('Olm payload incomplete');
		}
		return [
			'type' => $payload['type'],
			'content' => $payload['content'],
			'sender' => $eventSender,
			'senderDevice' => (string)($payload['sender_device'] ?? ''),
			'claimedEd25519' => $claimed,
		];
	}
}
