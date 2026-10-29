<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Config;

use OCP\Config\Lexicon\Entry;
use OCP\Config\ValueType;

/**
 * Lexicon entry with the Talk specific details used for the X-Nextcloud-Talk-Hash and the documentation
 */
class AppConfigEntry {
	/**
	 * @param bool $hash Changing the value changes the X-Nextcloud-Talk-Hash so clients refresh the capabilities
	 * @param bool $adminSetting The value can be set in the admin settings UI
	 * @param list<class-string> $occCommands Dedicated occ commands to set the value
	 * @param ?string $arrayType Psalm style type of the content for {@see ValueType::ARRAY} entries, e.g. `list<string>`
	 * @param ?string $enumType Allowed values of {@see ValueType::STRING} and {@see ValueType::INT} entries separated by `|`, e.g. `split|unified`
	 */
	public function __construct(
		public readonly Entry $entry,
		public readonly bool $hash = false,
		public readonly bool $adminSetting = false,
		public readonly array $occCommands = [],
		public readonly ?string $arrayType = null,
		public readonly ?string $enumType = null,
	) {
	}
}
