<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Model;

final class LoginResult {
	public function __construct(
		public readonly string $userId,
		public readonly string $accessToken,
		public readonly string $deviceId,
		public readonly ?string $refreshToken = null,
		public readonly ?int $expiresInMs = null,
		public readonly ?string $wellKnownBaseUrl = null,
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(array $raw): self {
		return new self(
			(string)($raw['user_id'] ?? ''),
			(string)($raw['access_token'] ?? ''),
			(string)($raw['device_id'] ?? ''),
			isset($raw['refresh_token']) ? (string)$raw['refresh_token'] : null,
			isset($raw['expires_in_ms']) ? (int)$raw['expires_in_ms'] : null,
			isset($raw['well_known']['m.homeserver']['base_url']) ? (string)$raw['well_known']['m.homeserver']['base_url'] : null,
		);
	}
}
