<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Util;

/**
 * Parsing and validation of Matrix identifiers and URIs.
 */
final class Identifier {
	public const TYPE_USER = 'user';
	public const TYPE_ROOM = 'room';
	public const TYPE_ALIAS = 'alias';
	public const TYPE_EVENT = 'event';

	public static function isUserId(string $id): bool {
		return (bool)preg_match('/^@[^:\s]+:[^\s]+$/u', $id) && strlen($id) <= 255;
	}

	public static function isRoomId(string $id): bool {
		return (bool)preg_match('/^![^:\s]+(:[^\s]+)?$/u', $id) && strlen($id) <= 255;
	}

	public static function isRoomAlias(string $id): bool {
		return (bool)preg_match('/^#[^:\s]+:[^\s]+$/u', $id) && strlen($id) <= 255;
	}

	public static function isEventId(string $id): bool {
		return str_starts_with($id, '$') && strlen($id) > 1 && strlen($id) <= 255;
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
		return ltrim($local, '@!#$');
	}

	/**
	 * Build a full user id from user input: accepts `@alice:example.org`,
	 * `alice:example.org`, `alice` (+ default server) and `alice@example.org`.
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
	 * Parse a room reference in any common form: `!id:server`, `#alias:server`,
	 * `https://matrix.to/#/#alias:server?via=x`, `matrix:r/alias:server`,
	 * `matrix:roomid/id:server?via=x`.
	 *
	 * @return array{type: string, id: string, via: list<string>}
	 */
	public static function parseRoomReference(string $input): array {
		$input = trim($input);
		$via = [];

		if (preg_match('~^https?://matrix\.to/#/([^?]+)(?:\?(.*))?$~i', $input, $m)) {
			$input = rawurldecode($m[1]);
			$via = self::parseVia($m[2] ?? '');
		} elseif (preg_match('~^matrix:(r|roomid|u)/([^?]+)(?:\?(.*))?$~i', $input, $m)) {
			$sigil = ['r' => '#', 'roomid' => '!', 'u' => '@'][strtolower($m[1])];
			$input = $sigil . rawurldecode($m[2]);
			$via = self::parseVia($m[3] ?? '');
		}

		if (self::isRoomId($input)) {
			return ['type' => self::TYPE_ROOM, 'id' => $input, 'via' => $via];
		}
		if (self::isRoomAlias($input)) {
			return ['type' => self::TYPE_ALIAS, 'id' => $input, 'via' => $via];
		}
		if (self::isUserId($input)) {
			return ['type' => self::TYPE_USER, 'id' => $input, 'via' => $via];
		}
		throw new \InvalidArgumentException('Not a Matrix room reference: ' . $input);
	}

	/** @return list<string> */
	private static function parseVia(string $query): array {
		$via = [];
		foreach (explode('&', $query) as $pair) {
			if ($pair === '') {
				continue;
			}
			[$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
			if ($k === 'via' && $v !== '') {
				$via[] = rawurldecode($v);
			}
		}
		return $via;
	}

	public static function matrixToUrl(string $id): string {
		return 'https://matrix.to/#/' . rawurlencode($id);
	}
}
