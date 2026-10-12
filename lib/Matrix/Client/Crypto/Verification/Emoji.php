<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Crypto\Verification;

/** The 64-entry SAS emoji table from the specification (§11.12.2.2.7). */
final class Emoji {
	public const TABLE = [
		['🐶', 'Dog'], ['🐱', 'Cat'], ['🦁', 'Lion'], ['🐎', 'Horse'], ['🦄', 'Unicorn'], ['🐷', 'Pig'], ['🐘', 'Elephant'], ['🐰', 'Rabbit'],
		['🐼', 'Panda'], ['🐓', 'Rooster'], ['🐧', 'Penguin'], ['🐢', 'Turtle'], ['🐟', 'Fish'], ['🐙', 'Octopus'], ['🦋', 'Butterfly'], ['🌷', 'Flower'],
		['🌳', 'Tree'], ['🌵', 'Cactus'], ['🍄', 'Mushroom'], ['🌏', 'Globe'], ['🌙', 'Moon'], ['☁️', 'Cloud'], ['🔥', 'Fire'], ['🍌', 'Banana'],
		['🍎', 'Apple'], ['🍓', 'Strawberry'], ['🌽', 'Corn'], ['🍕', 'Pizza'], ['🎂', 'Cake'], ['❤️', 'Heart'], ['😀', 'Smiley'], ['🤖', 'Robot'],
		['🎩', 'Hat'], ['👓', 'Glasses'], ['🔧', 'Spanner'], ['🎅', 'Santa'], ['👍', 'Thumbs Up'], ['☂️', 'Umbrella'], ['⌛', 'Hourglass'], ['⏰', 'Clock'],
		['🎁', 'Gift'], ['💡', 'Light Bulb'], ['📕', 'Book'], ['✏️', 'Pencil'], ['📎', 'Paperclip'], ['✂️', 'Scissors'], ['🔒', 'Lock'], ['🔑', 'Key'],
		['🔨', 'Hammer'], ['☎️', 'Telephone'], ['🏁', 'Flag'], ['🚂', 'Train'], ['🚲', 'Bicycle'], ['✈️', 'Aeroplane'], ['🚀', 'Rocket'], ['🏆', 'Trophy'],
		['⚽', 'Ball'], ['🎸', 'Guitar'], ['🎺', 'Trumpet'], ['🔔', 'Bell'], ['⚓', 'Anchor'], ['🎧', 'Headphones'], ['📁', 'Folder'], ['📌', 'Pin'],
	];

	/**
	 * Seven emoji from the first 42 bits of the SAS bytes.
	 * @return list<array{emoji: string, name: string}>
	 */
	public static function fromSasBytes(string $bytes): array {
		if (strlen($bytes) < 6) {
			throw new \InvalidArgumentException('Need at least 6 SAS bytes');
		}
		$bits = '';
		for ($i = 0; $i < 6; $i++) {
			$bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
		}
		$out = [];
		for ($i = 0; $i < 7; $i++) {
			$index = (int)bindec(substr($bits, $i * 6, 6));
			$out[] = ['emoji' => self::TABLE[$index][0], 'name' => self::TABLE[$index][1]];
		}
		return $out;
	}
}
