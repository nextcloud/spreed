<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Model;

final class LoginResult {
	public function __construct(
		public readonly string $userId,
		#[\SensitiveParameter]
		public readonly string $accessToken,
		public readonly string $deviceId,
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(array $raw): self {
		return new self(
			(string)($raw['user_id'] ?? ''),
			(string)($raw['access_token'] ?? ''),
			(string)($raw['device_id'] ?? ''),
		);
	}
}
