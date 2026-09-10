<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Util;

/**
 * Canonical JSON as defined in the Matrix specification appendices:
 * keys sorted lexicographically by code point, no insignificant whitespace,
 * shortest float representation, no escaping of non-ASCII characters.
 */
final class Canonical {
	public static function encode(mixed $value): string {
		return json_encode(self::sort($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
	}

	private static function sort(mixed $value): mixed {
		if ($value instanceof \JsonSerializable) {
			$value = $value->jsonSerialize();
		}
		if ($value instanceof \stdClass) {
			$value = (array)$value;
		}
		if (!is_array($value)) {
			if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
				// Matrix canonical JSON forbids floats in signed content; integral floats are emitted as ints
				return (int)$value;
			}
			return $value;
		}
		if ($value === []) {
			// PHP cannot tell an empty list from an empty map; empty maps dominate in signed content
			return new \stdClass();
		}
		if (array_is_list($value)) {
			return array_map(self::sort(...), $value);
		}
		ksort($value, SORT_STRING);
		foreach ($value as $k => $v) {
			$value[$k] = self::sort($v);
		}
		return $value;
	}
}
