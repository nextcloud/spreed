<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto\Megolm;

use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\Kdf;

/**
 * The Megolm ratchet: four 32-byte parts R(i,0..3). Part j rolls every 2^(8*(3-j))
 * steps and re-seeds the parts below it, so advancing by 2^32 steps costs at
 * most 4*255 HMACs. Mirrors libolm's megolm.c (`megolm_advance_to`).
 */
final class Ratchet {
	public const PARTS = 4;
	public const PART_LENGTH = 32;
	public const LENGTH = self::PARTS * self::PART_LENGTH;

	/** @var list<string> */
	private array $data;
	private int $counter;

	/** @param list<string> $parts */
	private function __construct(array $parts, int $counter) {
		$this->data = $parts;
		$this->counter = $counter & 0xFFFFFFFF;
	}

	public static function random(): self {
		return new self([random_bytes(32), random_bytes(32), random_bytes(32), random_bytes(32)], 0);
	}

	public static function fromBytes(string $bytes, int $counter): self {
		if (strlen($bytes) !== self::LENGTH) {
			throw new CryptoException('Megolm ratchet must be 128 bytes');
		}
		return new self(str_split($bytes, self::PART_LENGTH), $counter);
	}

	public function toBytes(): string {
		return implode('', $this->data);
	}

	public function getCounter(): int {
		return $this->counter;
	}

	/** Advance by one step. */
	public function advance(): void {
		$this->advanceTo(($this->counter + 1) & 0xFFFFFFFF);
	}

	/** Advance to $target (32-bit counter; must not be behind the current counter). */
	public function advanceTo(int $target): void {
		$target &= 0xFFFFFFFF;
		if ($target < $this->counter) {
			throw new CryptoException('Cannot rewind Megolm ratchet');
		}
		for ($j = 0; $j < self::PARTS; $j++) {
			$shift = (self::PARTS - $j - 1) * 8;
			$mask = (0xFFFFFFFF << $shift) & 0xFFFFFFFF;
			$steps = (($target >> $shift) - ($this->counter >> $shift)) & 0xFF;
			if ($steps > 0) {
				$this->counter = ($this->counter & $mask) & 0xFFFFFFFF;
				$this->counter = ($this->counter + ($steps << $shift)) & 0xFFFFFFFF;
				for (; $steps > 1; $steps--) {
					$this->data[$j] = $this->rehash($j, $j);
				}
				for ($k = self::PARTS - 1; $k >= $j; $k--) {
					$this->data[$k] = $this->rehash($j, $k);
				}
			}
		}
	}

	private function rehash(int $fromPart, int $toPart): string {
		return Kdf::hmac($this->data[$fromPart], chr($toPart));
	}
}
