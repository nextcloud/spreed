<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

use Nextcloud\Matrix\Crypto\Megolm\InboundSession;
use Nextcloud\Matrix\Crypto\Megolm\OutboundSession;
use Nextcloud\Matrix\Crypto\Olm\Account;

/**
 * `m.room.encrypted` with algorithm `m.megolm.v1.aes-sha2` – room messages –
 * plus the `m.room_key` / `m.forwarded_room_key` / `m.room_key_request`
 * content builders that go with it.
 */
final class MegolmEnvelope {
	public const ALGORITHM = 'm.megolm.v1.aes-sha2';

	/**
	 * @param array<string, mixed> $content
	 * @return array<string, mixed> encrypted event content
	 */
	public static function encrypt(OutboundSession $session, Account $ourAccount, string $ourDeviceId, string $roomId, string $type, array $content): array {
		$payload = json_encode(['type' => $type, 'content' => $content, 'room_id' => $roomId], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		return [
			'algorithm' => self::ALGORITHM,
			'sender_key' => $ourAccount->getIdentityKeyBase64(),
			'ciphertext' => $session->encrypt($payload),
			'session_id' => $session->getId(),
			'device_id' => $ourDeviceId,
		];
	}

	/**
	 * @param array<string, mixed> $content `m.room.encrypted` content
	 * @return array{type: string, content: array<string, mixed>, index: int}
	 */
	public static function decrypt(InboundSession $session, array $content, string $expectedRoomId): array {
		if (($content['algorithm'] ?? null) !== self::ALGORITHM || !is_string($content['ciphertext'] ?? null)) {
			throw new CryptoException('Not a Megolm event');
		}
		$result = $session->decrypt($content['ciphertext']);
		$payload = json_decode($result['plaintext'], true);
		if (!is_array($payload) || !is_string($payload['type'] ?? null) || !is_array($payload['content'] ?? null)) {
			throw new CryptoException('Megolm payload malformed');
		}
		if (($payload['room_id'] ?? null) !== $expectedRoomId) {
			throw new CryptoException('Megolm payload room mismatch');
		}
		return ['type' => $payload['type'], 'content' => $payload['content'], 'index' => $result['index']];
	}

	/** @return array<string, mixed> m.room_key content */
	public static function roomKeyContent(OutboundSession $session, string $roomId): array {
		return [
			'algorithm' => self::ALGORITHM,
			'room_id' => $roomId,
			'session_id' => $session->getId(),
			'session_key' => $session->sessionKey(),
		];
	}

	/**
	 * @param list<string> $forwardingChain
	 * @return array<string, mixed> m.forwarded_room_key content
	 */
	public static function forwardedRoomKeyContent(InboundSession $session, string $roomId, string $senderKey, string $senderClaimedEd25519, array $forwardingChain): array {
		return [
			'algorithm' => self::ALGORITHM,
			'room_id' => $roomId,
			'sender_key' => $senderKey,
			'sender_claimed_ed25519_key' => $senderClaimedEd25519,
			'session_id' => $session->getId(),
			'session_key' => $session->export(),
			'forwarding_curve25519_key_chain' => $forwardingChain,
		];
	}

	/** @return array<string, mixed> m.room_key_request content */
	public static function keyRequestContent(string $roomId, string $sessionId, string $requestId, string $ourDeviceId, bool $cancel = false): array {
		$content = [
			'action' => $cancel ? 'request_cancellation' : 'request',
			'requesting_device_id' => $ourDeviceId,
			'request_id' => $requestId,
		];
		if (!$cancel) {
			$content['body'] = ['algorithm' => self::ALGORITHM, 'room_id' => $roomId, 'session_id' => $sessionId];
		}
		return $content;
	}
}
