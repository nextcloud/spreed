<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

/**
 * The protobuf-ish varint / length-delimited encoding used by Olm and Megolm
 * message formats.
 */
final class Wire {
	public static function varint(int $value): string {
		$out = '';
		do {
			$byte = $value & 0x7F;
			$value >>= 7;
			$out .= chr($value > 0 ? $byte | 0x80 : $byte);
		} while ($value > 0);
		return $out;
	}

	/** @return array{0: int, 1: int} value, new offset */
	public static function readVarint(string $data, int $offset): array {
		$value = 0;
		$shift = 0;
		while (true) {
			if ($offset >= strlen($data)) {
				throw new CryptoException('Truncated varint');
			}
			$byte = ord($data[$offset++]);
			$value |= ($byte & 0x7F) << $shift;
			if (($byte & 0x80) === 0) {
				return [$value, $offset];
			}
			$shift += 7;
			if ($shift > 63) {
				throw new CryptoException('Varint too long');
			}
		}
	}

	public static function bytes(int $tag, string $bytes): string {
		return chr($tag) . self::varint(strlen($bytes)) . $bytes;
	}

	public static function int(int $tag, int $value): string {
		return chr($tag) . self::varint($value);
	}

	/**
	 * Parse tag/value fields up to $end.
	 * @return array<int, int|string> tag → value (int for varints, string for bytes)
	 */
	public static function parse(string $data, int $offset, int $end): array {
		$fields = [];
		while ($offset < $end) {
			$tag = ord($data[$offset++]);
			$wireType = $tag & 0x07;
			if ($wireType === 0) {
				[$value, $offset] = self::readVarint($data, $offset);
				$fields[$tag] = $value;
			} elseif ($wireType === 2) {
				[$length, $offset] = self::readVarint($data, $offset);
				if ($offset + $length > $end) {
					throw new CryptoException('Truncated field');
				}
				$fields[$tag] = substr($data, $offset, $length);
				$offset += $length;
			} else {
				throw new CryptoException('Unsupported wire type ' . $wireType);
			}
		}
		return $fields;
	}
}
