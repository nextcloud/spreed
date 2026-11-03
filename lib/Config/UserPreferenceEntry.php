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
 * Lexicon entry for typed User Configs in an App
 */
class UserPreferenceEntry {
	/**
	 * @param bool $provisionable Whether the value can be set in the provisioning API
	 * @param list<class-string> $occCommands Dedicated occ commands to set the value
	 * @param ?string $arrayType Psalm style type of the content for {@see ValueType::ARRAY} entries, e.g. `list<string>`
	 * @param ?string $enumType Allowed values of {@see ValueType::STRING} and {@see ValueType::INT} entries separated by `|`, e.g. `split|unified`
	 */
	public function __construct(
		public readonly Entry $entry,
		public readonly bool $provisionable = false,
		public readonly array $occCommands = [],
		public readonly ?string $enumType = null,
	) {
	}
}
