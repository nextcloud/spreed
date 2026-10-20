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

	/**
	 * Room id or alias from user input: `!id:server`, `#alias:server`,
	 * `https://matrix.to/#/#alias:server?via=server` or `matrix:r/alias:server`
	 *
	 * @return array{0: string, 1: list<string>} Room id or alias, and servers to join through
	 * @throws \InvalidArgumentException
	 */
	public static function parseRoomReference(string $input): array {
		$input = trim($input);
		$query = '';
		if (preg_match('~^https://matrix\.to/#/([^?]+)(?:\?(.*))?$~i', $input, $matches)) {
			$input = rawurldecode($matches[1]);
			$query = $matches[2] ?? '';
		} elseif (preg_match('~^matrix:(r|roomid)/([^?]+)(?:\?(.*))?$~i', $input, $matches)) {
			$input = (strtolower($matches[1]) === 'r' ? '#' : '!') . rawurldecode($matches[2]);
			$query = $matches[3] ?? '';
		}

		if (!preg_match('/^[!#][^:\s]+(:[^\s]+)?$/u', $input) || strlen($input) > 255 || ($input[0] === '#' && !str_contains($input, ':'))) {
			throw new \InvalidArgumentException('Not a Matrix room: ' . $input);
		}

		$servers = [];
		foreach (explode('&', $query) as $parameter) {
			if (str_starts_with($parameter, 'via=') && strlen($parameter) > 4) {
				$servers[] = rawurldecode(substr($parameter, 4));
			}
		}
		return [$input, $servers];
	}
}
