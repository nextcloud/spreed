<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Util;

/**
 * Parsing and validation of Matrix identifiers.
 */
final class Identifier {
	public static function isUserId(string $id): bool {
		return (bool)preg_match('/^@[^:\s]+:[^\s]+$/u', $id) && strlen($id) <= 255;
	}

	/** `@user:server` → `server` */
	public static function serverName(string $id): string {
		$pos = strpos($id, ':');
		return $pos === false ? '' : substr($id, $pos + 1);
	}

	/** `@user:server` → `user` */
	public static function localpart(string $id): string {
		$pos = strpos($id, ':');
		$local = $pos === false ? $id : substr($id, 0, $pos);
		return ltrim($local, '@');
	}

	/**
	 * Build a full user id from user input: accepts `@alice:example.org`,
	 * `alice:example.org`, `alice` (+ default server) and `alice@example.org`.
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function normalizeUserId(string $input, string $defaultServer): string {
		$input = trim($input);
		if ($input === '') {
			throw new \InvalidArgumentException('Empty user id');
		}
		if ($input[0] !== '@') {
			if (str_contains($input, ':')) {
				$input = '@' . $input;
			} elseif (str_contains($input, '@')) {
				[$local, $server] = explode('@', $input, 2);
				$input = '@' . $local . ':' . $server;
			} else {
				$input = '@' . $input . ':' . $defaultServer;
			}
		}
		if (!self::isUserId($input)) {
			throw new \InvalidArgumentException('Invalid Matrix user id: ' . $input);
		}
		return $input;
	}
}
