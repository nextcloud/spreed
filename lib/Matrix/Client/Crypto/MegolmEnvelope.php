<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Crypto;

use OCA\Talk\Matrix\Client\Crypto\Megolm\InboundSession;
use OCA\Talk\Matrix\Client\Crypto\Megolm\OutboundSession;
use OCA\Talk\Matrix\Client\Crypto\Olm\Account;

/**
 * `m.room.encrypted` with algorithm `m.megolm.v1.aes-sha2` – room messages –
 * plus the `m.room_key` content to share the session. Relations stay
 * unencrypted, so the homeserver can aggregate edits, reactions and threads.
 */
final class MegolmEnvelope {
	public const ALGORITHM = 'm.megolm.v1.aes-sha2';

	/**
	 * @param array<string, mixed> $content
	 * @return array<string, mixed> encrypted event content
	 */
	public static function encrypt(OutboundSession $session, Account $ourAccount, string $ourDeviceId, string $roomId, string $type, array $content): array {
		$relation = $content['m.relates_to'] ?? null;
		unset($content['m.relates_to']);
		$payload = json_encode(['type' => $type, 'content' => $content, 'room_id' => $roomId], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		$encrypted = [
			'algorithm' => self::ALGORITHM,
			'sender_key' => $ourAccount->getIdentityKeyBase64(),
			'ciphertext' => $session->encrypt($payload),
			'session_id' => $session->getId(),
			'device_id' => $ourDeviceId,
		];
		if ($relation !== null) {
			$encrypted['m.relates_to'] = $relation;
		}
		return $encrypted;
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
		if (is_array($content['m.relates_to'] ?? null) && !isset($payload['content']['m.relates_to'])) {
			$payload['content']['m.relates_to'] = $content['m.relates_to'];
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
}
