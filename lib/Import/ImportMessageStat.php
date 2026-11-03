<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

/**
 * What Talk holds for one imported message ref
 */
final class ImportMessageStat {
	/**
	 * @param string $sha256 canonical hash of the message as stored now (only when asked for the hash)
	 * @param bool $changed edited or deleted in Talk since the import wrote it
	 */
	public function __construct(
		public readonly string $ref,
		public readonly bool $exists,
		public readonly int $messageId = 0,
		public readonly string $sha256 = '',
		public readonly bool $changed = false,
	) {
	}
}
